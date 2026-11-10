<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcDispositionException;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\RiskAssessment;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RfcChangeManagerReviewService
{
    /**
     * @var array<string, string>
     */
    private const VALUE_MAP = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
        'emergency' => 'Emergency',
        'normal' => 'Normal',
        'standard' => 'Standard Change',
    ];

    /**
     * @var array<string, int>
     */
    private const SCORE_MAP = [
        'Rendah' => 1,
        'Sedang' => 2,
        'Normal' => 2,
        'Tinggi' => 3,
        'Kritis' => 4,
        'Emergency' => 4,
    ];

    public function __construct(private readonly RfcDispositionService $dispositionService) {}

    /**
     * Simpan analisis risiko, keputusan, dan (bila disetujui) disposisi Tim
     * Rilis dalam satu transaksi atomik.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function submit(
        ChangeRequest $rfc,
        User $actor,
        array $assessment,
        string $decisionStatus,
        string $notes,
        ?int $memberId,
        ?string $instruction,
    ): void {
        $status = DecisionStatus::tryFrom($decisionStatus);

        if ($status === null || ! in_array($status, DecisionStatus::formOptions(), true)) {
            throw new RfcDispositionException('Pilihan keputusan tidak valid untuk alur baru.');
        }

        $targetStatus = match ($status) {
            DecisionStatus::APPROVED => RfcStatus::APPROVED,
            DecisionStatus::REJECTED => RfcStatus::REJECTED,
            DecisionStatus::REVISION_REQUIRED => RfcStatus::NEEDS_REVISION,
            default => null,
        };

        if ($targetStatus === null) {
            throw new RfcDispositionException('Pilihan keputusan tidak valid untuk alur baru.');
        }

        $notes = trim($notes);

        if ($notes === '' || mb_strlen($notes) > 1000) {
            throw new RfcDispositionException('Catatan keputusan wajib diisi maksimal 1000 karakter.');
        }

        $rfcId = $rfc->getKey();
        $hasDisposition = false;

        DB::transaction(function () use ($rfcId, $actor, $assessment, $status, $targetStatus, $notes, $memberId, $instruction, &$hasDisposition): void {
            $lockedRfc = ChangeRequest::withTrashed()
                ->whereKey($rfcId)
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc || $lockedRfc->trashed()) {
                throw new RfcDispositionException('RFC tidak ditemukan atau sudah dihapus.');
            }

            if (RfcStatus::fromValue($lockedRfc->status) !== RfcStatus::TICKET_CREATED) {
                throw new InvalidRfcStatusTransitionException(
                    'Analisis risiko dan keputusan hanya dapat dilakukan saat RFC berstatus sedang ditinjau.'
                );
            }

            $this->guardActor($lockedRfc, $actor, $status);

            $this->saveRiskAssessment($lockedRfc, $actor, $assessment);
            $this->recordDecision($lockedRfc, $actor, $status, $targetStatus, $notes);

            if ($status !== DecisionStatus::APPROVED) {
                return;
            }

            if ($memberId === null) {
                throw new RfcDispositionException('Pilih tepat satu anggota Tim Rilis sebagai penerima disposisi.');
            }

            if ($instruction === null) {
                throw new RfcDispositionException('Instruksi disposisi wajib diisi.');
            }

            $this->dispositionService->createDispositionTasks($lockedRfc, $actor, [$memberId], $instruction);

            $hasDisposition = true;
        });

        // Notifikasi dikirim setelah transaksi selesai. Kegagalan kanal
        // ditangani di dalam notifier dan tidak membatalkan data yang tersimpan.
        if ($hasDisposition) {
            app(RfcNotifier::class)->dispositionAssigned($rfc->fresh() ?? $rfc);
        }
    }

    private function guardActor(ChangeRequest $rfc, User $actor, DecisionStatus $decisionStatus): void
    {
        // Aturan Segregation of Duties: pengaju tidak boleh menilai risiko
        // maupun memutuskan RFC-nya sendiri.
        if ($rfc->submitted_by_id === $actor->id) {
            throw new RfcDispositionException(
                'Pengaju RFC tidak boleh menilai risiko atau mencatat keputusan atas RFC-nya sendiri.'
            );
        }

        if (! $actor->hasPermissionTo('rfc.assess_risk')) {
            throw new RfcDispositionException('Anda tidak memiliki izin menilai risiko RFC.');
        }

        if (! $actor->hasPermissionTo('rfc.record_decision')) {
            throw new RfcDispositionException('Anda tidak memiliki izin mencatat keputusan RFC.');
        }

        if ($decisionStatus === DecisionStatus::APPROVED && ! $actor->hasPermissionTo('rfc.assign_release')) {
            throw new RfcDispositionException('Anda tidak memiliki izin memberikan disposisi ke Tim Rilis.');
        }
    }

    /**
     * @param  array<string, mixed>  $assessment
     */
    private function saveRiskAssessment(ChangeRequest $rfc, User $actor, array $assessment): void
    {
        $riskLevel = $this->normalizeValue($assessment['risk_level'] ?? null, 'Rendah');
        $impactLevel = $this->normalizeValue($assessment['impact_level'] ?? null, 'Sedang');
        $urgencyLevel = $this->normalizeValue($assessment['urgency_level'] ?? null, 'Normal');
        $classification = (string) ($assessment['classification'] ?? 'Normal Change');
        $analysisNotes = trim((string) ($assessment['analysis_notes'] ?? ''));

        if (! in_array($riskLevel, ['Rendah', 'Sedang', 'Tinggi', 'Kritis'], true)) {
            throw new RfcDispositionException('Tingkat risiko tidak valid.');
        }

        if (! in_array($impactLevel, ['Rendah', 'Sedang', 'Tinggi', 'Kritis'], true)) {
            throw new RfcDispositionException('Tingkat dampak tidak valid.');
        }

        if (! in_array($urgencyLevel, ['Rendah', 'Normal', 'Tinggi', 'Emergency'], true)) {
            throw new RfcDispositionException('Tingkat urgensi tidak valid.');
        }

        if (! in_array($classification, ['Standard Change', 'Normal Change', 'Emergency Change'], true)) {
            throw new RfcDispositionException('Klasifikasi perubahan tidak valid.');
        }

        if ($analysisNotes === '' || mb_strlen($analysisNotes) > 1000) {
            throw new RfcDispositionException('Catatan analisis wajib diisi maksimal 1000 karakter.');
        }

        $riskScore = self::SCORE_MAP[$riskLevel] ?? 1;
        $impactScore = self::SCORE_MAP[$impactLevel] ?? 1;
        $urgencyScore = self::SCORE_MAP[$urgencyLevel] ?? 1;
        $rollbackScore = 1;

        $totalScore = $riskScore + $impactScore + $urgencyScore + $rollbackScore;

        $assessmentData = [
            'assessed_by_id' => $actor->id,
            'risk_level' => $riskLevel,
            'impact_level' => $impactLevel,
            'urgency_level' => $urgencyLevel,
            'classification' => $classification,
            'analysis_notes' => $analysisNotes,
        ];

        // Kolom opsional hanya diisi bila benar-benar ada pada skema.
        $optionalColumns = [
            'system_criticality_score' => $riskScore,
            'impact_scope_score' => $impactScore,
            'urgency_score' => $urgencyScore,
            'rollback_readiness_score' => $rollbackScore,
            'total_score' => $totalScore,
            'overall_risk_score' => $riskScore * $impactScore,
            'risk_score' => $riskScore * $impactScore,
            'mitigation_plan' => $analysisNotes,
            'rollback_plan' => 'TBD',
            'testing_plan' => 'TBD',
        ];

        foreach ($optionalColumns as $column => $value) {
            if (Schema::hasColumn('risk_assessments', $column)) {
                $assessmentData[$column] = $value;
            }
        }

        $emergencyWasUnconfirmed = (bool) $rfc->emergency_unconfirmed;
        $emergencyDescription = null;

        if ($emergencyWasUnconfirmed) {
            $emergencyDescription = $classification === 'Emergency Change'
                ? 'Kategori Emergency dikonfirmasi oleh Change Manager'
                : 'Usulan Emergency diubah Change Manager menjadi '.$classification;
        }

        RiskAssessment::updateOrCreate(
            ['change_request_id' => $rfc->id],
            $assessmentData
        );

        activity('rfc')
            ->performedOn($rfc)
            ->causedBy($actor)
            ->withProperties([
                'event' => 'risk_assessment',
                'risk_level' => $riskLevel,
                'impact_level' => $impactLevel,
                'classification' => $classification,
            ])
            ->log('Analisis risiko oleh Change Manager');

        if (! $emergencyWasUnconfirmed) {
            return;
        }

        $rfc->update(['emergency_unconfirmed' => false]);

        activity('rfc')
            ->performedOn($rfc)
            ->causedBy($actor)
            ->withProperties([
                'event' => 'emergency_classification_review',
                'submitted_change_type' => $rfc->change_type,
                'proposed_category' => 'Emergency',
                'classification' => $classification,
                'emergency_unconfirmed_before' => true,
                'emergency_unconfirmed_after' => false,
            ])
            ->log($emergencyDescription);
    }

    private function recordDecision(
        ChangeRequest $rfc,
        User $actor,
        DecisionStatus $decisionStatus,
        RfcStatus $targetStatus,
        string $notes,
    ): void {
        Decision::create([
            'change_request_id' => $rfc->id,
            'authority_id' => null,
            'recorded_by_id' => $actor->id,
            'snapshot_authority_name' => $actor->name,
            'snapshot_authority_nip' => filled($actor->nip) ? $actor->nip : null,
            'snapshot_authority_position' => filled($actor->position) ? $actor->position : 'Change Manager',
            'decision_status' => $decisionStatus->value,
            'decision_date' => now(),
            'reason' => $notes,
        ]);

        if ($decisionStatus === DecisionStatus::REVISION_REQUIRED) {
            $rfc->update(['rejection_note' => $notes]);
        } elseif ($decisionStatus === DecisionStatus::APPROVED) {
            $rfc->update(['rejection_note' => null]);
        }

        RfcStatusTransition::transitionTo($rfc, $targetStatus, $actor, $notes);
    }

    private function normalizeValue(mixed $value, string $default): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        $lower = strtolower(trim($value));

        return self::VALUE_MAP[$lower] ?? $value;
    }
}
