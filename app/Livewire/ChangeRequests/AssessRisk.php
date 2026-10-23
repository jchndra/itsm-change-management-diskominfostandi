<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\RiskAssessment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AssessRisk extends Component
{
    public ChangeRequest $rfc;

    public string $risk_level = 'Rendah';

    public string $impact_level = 'Sedang';

    public string $urgency_level = 'Normal';

    public string $classification = 'Normal Change';

    public string $analysis_notes = '';

    private array $valueMap = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
        'emergency' => 'Emergency',
        'normal' => 'Normal',
        'standard' => 'Standard Change',
    ];

    private function normalizeValue(?string $value, string $default): string
    {
        if (! $value) {
            return $default;
        }

        $lower = strtolower(trim($value));

        return $this->valueMap[$lower] ?? $value;
    }

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('assessRisk', $rfc);

        $this->rfc = $rfc->load(['submittedBy', 'application.opd', 'riskAssessment']);

        if ($this->rfc->riskAssessment) {
            $this->risk_level = $this->normalizeValue($this->rfc->riskAssessment->risk_level, 'Rendah');
            $this->impact_level = $this->normalizeValue($this->rfc->riskAssessment->impact_level, 'Sedang');
            $this->urgency_level = $this->normalizeValue($this->rfc->riskAssessment->urgency_level, 'Normal');
            $this->classification = $this->rfc->riskAssessment->classification ?? 'Normal Change';
            $this->analysis_notes = $this->rfc->riskAssessment->analysis_notes ?? '';
        }
    }

    public function submitAssessment(): void
    {
        $this->authorize('assessRisk', $this->rfc);

        if (RfcStatus::fromValue($this->rfc->status) !== RfcStatus::TICKET_CREATED) {
            $this->addError('status', 'Analisis risiko hanya dapat dilakukan saat RFC berstatus sedang ditinjau.');

            return;
        }

        $this->risk_level = $this->normalizeValue($this->risk_level, 'Rendah');
        $this->impact_level = $this->normalizeValue($this->impact_level, 'Sedang');
        $this->urgency_level = $this->normalizeValue($this->urgency_level, 'Normal');

        $this->validate([
            'risk_level' => 'required|string|in:Rendah,Sedang,Tinggi,Kritis',
            'impact_level' => 'required|string|in:Rendah,Sedang,Tinggi,Kritis',
            'urgency_level' => 'required|string|in:Rendah,Normal,Tinggi,Emergency',
            'classification' => 'required|string|in:Standard Change,Normal Change,Emergency Change',
            'analysis_notes' => 'required|string|max:1000',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $scoreMap = [
            'Rendah' => 1,
            'Sedang' => 2,
            'Normal' => 2,
            'Tinggi' => 3,
            'Kritis' => 4,
            'Emergency' => 4,
        ];

        $riskScore = $scoreMap[$this->risk_level] ?? 1;
        $impactScore = $scoreMap[$this->impact_level] ?? 1;
        $urgencyScore = $scoreMap[$this->urgency_level] ?? 1;
        $rollbackScore = 1;

        $totalScore = $riskScore + $impactScore + $urgencyScore + $rollbackScore;

        $assessmentData = [
            'assessed_by_id' => $user->id,
            'risk_level' => $this->risk_level,
            'impact_level' => $this->impact_level,
            'urgency_level' => $this->urgency_level,
            'classification' => $this->classification,
            'analysis_notes' => $this->analysis_notes,
        ];

        // Daftar kolom yang mungkin dibutuhkan oleh tabel berdasarkan error sebelumnya dan kemungkinan skema
        $optionalColumns = [
            'system_criticality_score' => $riskScore,
            'impact_scope_score' => $impactScore,
            'urgency_score' => $urgencyScore,
            'rollback_readiness_score' => $rollbackScore,
            'total_score' => $totalScore,
            'overall_risk_score' => $riskScore * $impactScore,
            'risk_score' => $riskScore * $impactScore,
            'mitigation_plan' => $this->analysis_notes, // Menyalin isi catat analisis ke mitigation plan
            'rollback_plan' => 'TBD', // Jika diwajibkan
            'testing_plan' => 'TBD',  // Jika diwajibkan
        ];

        // Memeriksa satu per satu apakah kolom ada di dalam skema sebelum di-insert
        foreach ($optionalColumns as $column => $value) {
            if (Schema::hasColumn('risk_assessments', $column)) {
                $assessmentData[$column] = $value;
            }
        }

        RiskAssessment::updateOrCreate(
            ['change_request_id' => $this->rfc->id],
            $assessmentData
        );

        activity('rfc')
            ->performedOn($this->rfc)
            ->causedBy($user)
            ->withProperties([
                'event' => 'risk_assessment',
                'risk_level' => $this->risk_level,
                'impact_level' => $this->impact_level,
                'classification' => $this->classification,
            ])
            ->log('Analisis risiko oleh Change Manager');

        session()->flash('message', 'Penilaian risiko dan klasifikasi RFC berhasil disimpan.');
        $this->redirectRoute('change-requests.index');
    }

    public function render()
    {
        return view('livewire.change-requests.assess-risk');
    }
}
