<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\Decision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class RfcHistoryService
{
    /**
     * Susun riwayat proses dari kejadian nyata yang tersedia.
     *
     * @return array{events: array<int, array<string, mixed>>, next_steps: array<int, array<string, string>>}
     */
    public function build(ChangeRequest $rfc, bool $forStaff = false): array
    {
        $rfc->loadMissing([
            'riskAssessment.assessedBy',
            'decisions.recordedBy',
            'submittedBy',
        ]);

        $events = [];
        $sequence = 0;

        $activities = Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->with('causer')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($activities as $activity) {
            $sequence++;

            $newStatusRaw = data_get($activity->properties, 'new_status');
            $oldStatusRaw = data_get($activity->properties, 'old_status');
            $note = data_get($activity->properties, 'note');
            $eventType = data_get($activity->properties, 'event');

            if ($eventType === 'rfc_revised') {
                $events[] = [
                    'key' => 'rfc_revised',
                    'label' => 'RFC direvisi oleh Pemohon',
                    'note' => $this->revisionChangesNote(data_get($activity->properties, 'changes', [])),
                    'time' => $activity->created_at,
                    'tone' => 'info',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => 'Pemohon',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if ($eventType === 'revision_reason_backfill') {
                $events[] = [
                    'key' => 'revision_reason',
                    'label' => 'Alasan permintaan revisi',
                    'note' => filled($note) ? trim((string) $note) : null,
                    'time' => $activity->created_at,
                    'tone' => 'warning',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => 'Admin',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if (in_array($eventType, ['attachment_added', 'attachment_deleted'], true)) {
                $fileName = data_get($activity->properties, 'file_name');

                $events[] = [
                    'key' => $eventType,
                    'label' => $eventType === 'attachment_added' ? 'Lampiran ditambahkan' : 'Lampiran dihapus',
                    'note' => filled($fileName) ? 'Berkas: '.$fileName : null,
                    'time' => $activity->created_at,
                    'tone' => $eventType === 'attachment_added' ? 'info' : 'warning',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => 'Pemohon',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if (blank($newStatusRaw)) {
                if ($this->isCreationActivity($activity)) {
                    $isSubmitted = str_contains(strtolower((string) $activity->description), 'diajukan');

                    $events[] = [
                        'key' => $isSubmitted ? RfcStatus::SUBMITTED->value : 'draft_created',
                        'label' => $isSubmitted ? 'RFC diajukan' : 'Draft dibuat',
                        'note' => null,
                        'time' => $activity->created_at,
                        'tone' => $isSubmitted ? 'info' : 'neutral',
                        'actor_name' => $activity->causer?->name,
                        'actor_role' => $isSubmitted ? 'Pemohon' : 'Pemohon',
                        'legacy' => false,
                        'order' => $sequence,
                    ];
                }

                continue;
            }

            // Nilai lama "assessed" adalah penanda selesainya analisis risiko,
            // bukan verifikasi Admin. Risiko asli dibaca dari tabel risk_assessments.
            if ((string) $newStatusRaw === RfcStatus::LEGACY_ASSESSED) {
                if (! $rfc->riskAssessment) {
                    $events[] = $this->makeEvent(
                        key: 'risk_assessment',
                        label: 'Analisis risiko oleh Change Manager',
                        time: $activity->created_at ?? now(),
                        tone: 'success',
                        note: $forStaff ? $note : null,
                        activity: $activity,
                        order: $sequence,
                    );
                }

                continue;
            }

            $newStatus = RfcStatus::fromValue($newStatusRaw);
            $oldStatus = RfcStatus::fromValue($oldStatusRaw);

            if (! $newStatus) {
                $events[] = [
                    'key' => 'legacy',
                    'label' => 'Pembaruan status (legacy): '.RfcStatus::labelFor($newStatusRaw),
                    'note' => $forStaff ? $note : null,
                    'time' => $activity->created_at,
                    'tone' => 'neutral',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => null,
                    'legacy' => true,
                    'order' => $sequence,
                ];

                continue;
            }

            $events[] = $this->statusEvent(
                status: $newStatus,
                oldStatus: $oldStatus,
                note: $note,
                activity: $activity,
                forStaff: $forStaff,
                order: $sequence,
            );
        }

        if ($rfc->riskAssessment) {
            $risk = $rfc->riskAssessment;
            $riskNote = $forStaff
                ? trim(implode(' • ', array_filter([
                    filled($risk->risk_level) ? 'Risiko: '.$risk->risk_level : null,
                    filled($risk->impact_level) ? 'Dampak: '.$risk->impact_level : null,
                    filled($risk->classification) ? 'Klasifikasi: '.$risk->classification : null,
                ])))
                : null;

            $events[] = [
                'key' => 'risk_assessment',
                'label' => 'Analisis risiko oleh Change Manager',
                'note' => $riskNote !== '' ? $riskNote : null,
                'time' => $risk->created_at ?? $rfc->updated_at,
                'tone' => 'success',
                'actor_name' => $forStaff ? ($risk->assessedBy?->name ?? $risk->assessed_by_id) : null,
                'actor_role' => 'Change Manager',
                'legacy' => false,
                'order' => 900000 + $risk->id,
            ];
        }

        $sequence += 1000;
        foreach ($rfc->decisions->sortBy('decision_date') as $decision) {
            $sequence++;
            $this->mergeOrAddDecision($events, $decision, $forStaff, $sequence);
        }

        $this->ensureCreationEvent($events, $rfc, $forStaff);
        $this->ensureCurrentStatusEvent($events, $rfc);
        $this->applyFallbackNotes($events, $rfc, $forStaff);

        usort($events, fn (array $a, array $b): int => [
            $a['time']->getTimestamp(),
            $a['order'],
        ] <=> [
            $b['time']->getTimestamp(),
            $b['order'],
        ]);

        if (! $forStaff) {
            foreach ($events as &$event) {
                if (! in_array($event['key'], [
                    RfcStatus::REJECTED->value,
                    RfcStatus::NEEDS_REVISION->value,
                    RfcStatus::FAILED->value,
                    'rfc_revised',
                    'revision_reason',
                ], true)) {
                    $event['note'] = null;
                }

                $event['actor_name'] = null;
            }
            unset($event);
        }

        return [
            'events' => $events,
            'next_steps' => $this->nextSteps($rfc),
        ];
    }

    /**
     * Langkah mendatang hanya untuk RFC yang masih berjalan.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function nextSteps(ChangeRequest $rfc): array
    {
        $status = RfcStatus::fromValue($rfc->status);

        if (! $status || in_array($status, [
            RfcStatus::REJECTED,
            RfcStatus::CANCELLED,
            RfcStatus::FAILED,
            RfcStatus::CLOSED,
        ], true)) {
            return [];
        }

        $steps = [
            ['key' => 'admin_review', 'label' => 'Diperiksa Admin'],
            ['key' => 'cm_review', 'label' => 'Review Change Manager'],
            ['key' => 'implementation', 'label' => 'Implementasi'],
            ['key' => 'done', 'label' => 'Selesai'],
        ];

        $start = match ($status) {
            RfcStatus::DRAFT,
            RfcStatus::SUBMITTED,
            RfcStatus::RETURNED_TO_ADMIN,
            RfcStatus::NEEDS_REVISION => 0,
            RfcStatus::TICKET_CREATED => 1,
            RfcStatus::APPROVED => 2,
            RfcStatus::IN_PROGRESS,
            RfcStatus::IMPLEMENTED => 3,
            default => 0,
        };

        return array_slice($steps, $start);
    }

    /**
     * Timeline ringkas lima langkah untuk widget dashboard Pemohon.
     *
     * @return array<int, array{key: string, label: string, description: string, state: string, time: ?Carbon}>
     */
    public function dashboardTimeline(ChangeRequest $rfc, bool $forStaff = false): array
    {
        $status = RfcStatus::fromValue($rfc->status);

        if (! $status) {
            return [[
                'key' => 'legacy',
                'label' => 'Status permohonan',
                'description' => 'Status tidak dikenal (legacy): '.RfcStatus::labelFor($rfc->status),
                'state' => 'warning',
                'time' => $rfc->updated_at,
            ]];
        }

        $events = $this->build($rfc, $forStaff)['events'];
        $lastEvent = fn (array $keys): ?array => collect($events)
            ->filter(fn (array $event): bool => in_array($event['key'], $keys, true))
            ->last();

        $submittedEvent = $lastEvent([RfcStatus::SUBMITTED->value, 'draft_created']);
        $adminEvent = $lastEvent([
            RfcStatus::TICKET_CREATED->value,
            RfcStatus::NEEDS_REVISION->value,
            RfcStatus::RETURNED_TO_ADMIN->value,
            RfcStatus::REJECTED->value,
        ]);
        $cmEvent = $lastEvent([
            'risk_assessment',
            RfcStatus::APPROVED->value,
            RfcStatus::RETURNED_TO_ADMIN->value,
            RfcStatus::REJECTED->value,
        ]);
        $workEvent = $lastEvent([
            RfcStatus::IN_PROGRESS->value,
            RfcStatus::IMPLEMENTED->value,
            RfcStatus::FAILED->value,
        ]);
        $doneEvent = $lastEvent([RfcStatus::CLOSED->value]);

        $steps = [
            [
                'key' => 'submitted',
                'label' => 'Diajukan',
                'description' => 'RFC diajukan',
                'time' => $submittedEvent['time'] ?? $rfc->created_at,
                'state' => $status === RfcStatus::DRAFT ? 'current' : 'done',
            ],
            [
                'key' => 'admin',
                'label' => 'Diperiksa Admin',
                'description' => 'Menunggu pemeriksaan Admin',
                'time' => $adminEvent['time'] ?? null,
                'state' => 'pending',
            ],
            [
                'key' => 'cm',
                'label' => 'Review Change Manager',
                'description' => 'Menunggu review Change Manager',
                'time' => $cmEvent['time'] ?? null,
                'state' => 'pending',
            ],
            [
                'key' => 'work',
                'label' => 'Dikerjakan',
                'description' => 'Menunggu pelaksanaan',
                'time' => $workEvent['time'] ?? null,
                'state' => 'pending',
            ],
            [
                'key' => 'done',
                'label' => 'Selesai',
                'description' => 'Menunggu penutupan',
                'time' => $doneEvent['time'] ?? null,
                'state' => 'pending',
            ],
        ];

        $this->applyDashboardStates($steps, $status, $events);

        return $this->truncateDashboardSteps($steps, $status, $events);
    }

    /**
     * @param  array<string, array{label?: string, old?: string, new?: string}>  $changes
     */
    private function revisionChangesNote(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        $lines = [];

        foreach ($changes as $field => $change) {
            if (! is_array($change)) {
                continue;
            }

            $label = filled($change['label'] ?? null)
                ? (string) $change['label']
                : ucfirst(str_replace('_', ' ', (string) $field));

            $lines[] = sprintf(
                '%s: %s → %s',
                $label,
                $this->revisionChangeValue($change['old'] ?? null),
                $this->revisionChangeValue($change['new'] ?? null),
            );
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function revisionChangeValue(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        return Str::limit((string) $value, 180);
    }

    private function statusEvent(
        RfcStatus $status,
        ?RfcStatus $oldStatus,
        ?string $note,
        Activity $activity,
        bool $forStaff,
        int $order,
    ): array {
        $actorRole = $this->actorRole($activity->causer, $oldStatus, $status);
        $actorName = $forStaff ? $activity->causer?->name : null;
        $safeNote = filled($note) ? trim((string) $note) : null;

        [$label, $tone, $key] = match ($status) {
            RfcStatus::DRAFT => ['Disimpan sebagai draft', 'neutral', 'draft_created'],
            RfcStatus::SUBMITTED => ['RFC diajukan', 'info', RfcStatus::SUBMITTED->value],
            RfcStatus::TICKET_CREATED => ['Diverifikasi Admin dan diteruskan ke Change Manager', 'success', RfcStatus::TICKET_CREATED->value],
            RfcStatus::RETURNED_TO_ADMIN => [
                $forStaff ? 'Dikembalikan ke Admin' : 'Sedang ditinjau',
                'warning',
                RfcStatus::RETURNED_TO_ADMIN->value,
            ],
            RfcStatus::NEEDS_REVISION => ['Perlu revisi', 'warning', RfcStatus::NEEDS_REVISION->value],
            RfcStatus::REJECTED => [
                'Ditolak oleh '.$this->rejectionActorLabel($activity->causer, $oldStatus),
                'danger',
                RfcStatus::REJECTED->value,
            ],
            RfcStatus::APPROVED => ['Disetujui oleh Change Manager', 'success', RfcStatus::APPROVED->value],
            RfcStatus::IN_PROGRESS => ['Implementasi dimulai', 'info', RfcStatus::IN_PROGRESS->value],
            RfcStatus::IMPLEMENTED => ['Berhasil diimplementasikan', 'success', RfcStatus::IMPLEMENTED->value],
            RfcStatus::FAILED => ['Gagal dilaksanakan', 'danger', RfcStatus::FAILED->value],
            RfcStatus::CLOSED => ['RFC ditutup', 'neutral', RfcStatus::CLOSED->value],
            RfcStatus::CANCELLED => ['Dibatalkan', 'danger', RfcStatus::CANCELLED->value],
        };

        if (! $forStaff && $status === RfcStatus::RETURNED_TO_ADMIN) {
            $safeNote = null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'note' => $safeNote,
            'time' => $activity->created_at,
            'tone' => $tone,
            'actor_name' => $actorName,
            'actor_role' => $actorRole,
            'legacy' => false,
            'order' => $order,
        ];
    }

    private function makeEvent(
        string $key,
        string $label,
        Carbon $time,
        string $tone,
        ?string $note,
        Activity $activity,
        int $order,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'note' => $note,
            'time' => $time,
            'tone' => $tone,
            'actor_name' => $activity->causer?->name,
            'actor_role' => 'Change Manager',
            'legacy' => false,
            'order' => $order,
        ];
    }

    private function mergeOrAddDecision(array &$events, Decision $decision, bool $forStaff, int $order): void
    {
        $decisionTime = Carbon::parse($decision->decision_date ?? $decision->created_at ?? now());
        $status = DecisionStatus::tryFrom((string) $decision->decision_status);
        $reason = filled($decision->reason) ? trim((string) $decision->reason) : null;

        $key = $status?->value;
        $label = match ($status) {
            DecisionStatus::APPROVED => 'Disetujui oleh Change Manager',
            DecisionStatus::REJECTED => 'Ditolak oleh Change Manager',
            DecisionStatus::RETURNED_TO_ADMIN => $forStaff ? 'Dikembalikan ke Admin' : 'Sedang ditinjau',
            default => 'Keputusan Change Manager (legacy)',
        };
        $tone = match ($status) {
            DecisionStatus::APPROVED => 'success',
            DecisionStatus::REJECTED => 'danger',
            DecisionStatus::RETURNED_TO_ADMIN => 'warning',
            default => 'neutral',
        };

        if ($key) {
            foreach ($events as $index => $event) {
                if ($event['key'] !== $key) {
                    continue;
                }

                if (abs($event['time']->getTimestamp() - $decisionTime->getTimestamp()) <= 300) {
                    $events[$index]['actor_name'] = $forStaff
                        ? ($decision->recordedBy?->name ?? $decision->snapshot_authority_name)
                        : null;
                    $events[$index]['actor_role'] = 'Change Manager';

                    if ($reason && ($forStaff || $status === DecisionStatus::REJECTED)) {
                        $events[$index]['note'] = $events[$index]['note'] ?: $reason;
                    }

                    return;
                }
            }
        }

        $events[] = [
            'key' => $key ?? 'legacy_decision',
            'label' => $label,
            'note' => $reason && ($forStaff || $status === DecisionStatus::REJECTED) ? $reason : null,
            'time' => $decisionTime,
            'tone' => $tone,
            'actor_name' => $forStaff
                ? ($decision->recordedBy?->name ?? $decision->snapshot_authority_name)
                : null,
            'actor_role' => 'Change Manager',
            'legacy' => $status === null,
            'order' => $order,
        ];
    }

    private function ensureCreationEvent(array &$events, ChangeRequest $rfc, bool $forStaff): void
    {
        foreach ($events as $event) {
            if (in_array($event['key'], [RfcStatus::SUBMITTED->value, 'draft_created'], true)) {
                return;
            }
        }

        $isDraft = RfcStatus::fromValue($rfc->status) === RfcStatus::DRAFT;

        $events[] = [
            'key' => $isDraft ? 'draft_created' : RfcStatus::SUBMITTED->value,
            'label' => $isDraft ? 'Draft dibuat' : 'RFC diajukan',
            'note' => null,
            'time' => $rfc->created_at ?? $rfc->updated_at ?? now(),
            'tone' => $isDraft ? 'neutral' : 'info',
            'actor_name' => $forStaff ? $rfc->submittedBy?->name : null,
            'actor_role' => 'Pemohon',
            'legacy' => false,
            'order' => -1,
        ];
    }

    private function ensureCurrentStatusEvent(array &$events, ChangeRequest $rfc): void
    {
        $status = RfcStatus::fromValue($rfc->status);
        $currentKey = $status?->value;
        $hasCurrent = collect($events)->contains(fn (array $event): bool => $event['key'] === $currentKey);

        if ($status === RfcStatus::DRAFT) {
            $hasCurrent = collect($events)->contains(fn (array $event): bool => $event['key'] === 'draft_created');
        }

        if ($status === null) {
            $hasCurrent = collect($events)->contains(fn (array $event): bool => $event['key'] === 'legacy');
        }

        if ($hasCurrent) {
            return;
        }

        $label = match ($status) {
            RfcStatus::REJECTED => 'Ditolak',
            RfcStatus::CANCELLED => 'Dibatalkan',
            RfcStatus::FAILED => 'Gagal dilaksanakan',
            RfcStatus::CLOSED => 'RFC ditutup',
            null => 'Pembaruan status (legacy): '.RfcStatus::labelFor($rfc->status),
            default => 'Status saat ini: '.$status->label(),
        };

        $tone = match ($status) {
            RfcStatus::REJECTED, RfcStatus::CANCELLED, RfcStatus::FAILED => 'danger',
            RfcStatus::CLOSED => 'neutral',
            default => 'neutral',
        };

        $events[] = [
            'key' => $status?->value ?? 'legacy',
            'label' => $label,
            'note' => null,
            'time' => $rfc->updated_at ?? $rfc->created_at ?? now(),
            'tone' => $tone,
            'actor_name' => null,
            'actor_role' => null,
            'legacy' => $status === null,
            'order' => 999999,
        ];
    }

    private function applyFallbackNotes(array &$events, ChangeRequest $rfc, bool $forStaff): void
    {
        $lastIndex = function (string $key) use ($events): ?int {
            $found = null;

            foreach ($events as $index => $event) {
                if ($event['key'] === $key) {
                    $found = $index;
                }
            }

            return $found;
        };

        $rejectedIndex = $lastIndex(RfcStatus::REJECTED->value);
        if ($rejectedIndex !== null && blank($events[$rejectedIndex]['note'])) {
            $decisionReason = $this->decisionReason($rfc, DecisionStatus::REJECTED->value);
            $note = $events[$rejectedIndex]['actor_role'] === 'Admin'
                ? ($rfc->rejection_note ?: $decisionReason)
                : $decisionReason;

            if (filled($note)) {
                $events[$rejectedIndex]['note'] = trim((string) $note);
            }
        }

        $needsRevisionIndex = $lastIndex(RfcStatus::NEEDS_REVISION->value);
        if ($needsRevisionIndex !== null && blank($events[$needsRevisionIndex]['note'])
            && RfcStatus::fromValue($rfc->status) === RfcStatus::NEEDS_REVISION) {
            $note = $rfc->rejection_note;

            if (filled($note)) {
                $events[$needsRevisionIndex]['note'] = trim((string) $note);
            }
        }

        $returnedIndex = $lastIndex(RfcStatus::RETURNED_TO_ADMIN->value);
        if ($returnedIndex !== null && blank($events[$returnedIndex]['note'])) {
            $note = $this->decisionReason($rfc, DecisionStatus::RETURNED_TO_ADMIN->value);

            if ($forStaff && filled($note)) {
                $events[$returnedIndex]['note'] = trim((string) $note);
            }
        }

        $approvedIndex = $lastIndex(RfcStatus::APPROVED->value);
        if ($approvedIndex !== null && blank($events[$approvedIndex]['note']) && $forStaff) {
            $note = $this->decisionReason($rfc, DecisionStatus::APPROVED->value);

            if (filled($note)) {
                $events[$approvedIndex]['note'] = trim((string) $note);
            }
        }
    }

    private function decisionReason(ChangeRequest $rfc, string $status): ?string
    {
        $decision = $rfc->decisions
            ->where('decision_status', $status)
            ->sortBy('decision_date')
            ->last();

        return filled($decision?->reason) ? trim((string) $decision->reason) : null;
    }

    private function isCreationActivity(Activity $activity): bool
    {
        $description = strtolower((string) $activity->description);

        return str_contains($description, 'diajukan')
            || str_contains($description, 'draft');
    }

    private function actorRole(?object $causer, ?RfcStatus $oldStatus, RfcStatus $newStatus): ?string
    {
        if ($causer && method_exists($causer, 'hasRole')) {
            if ($causer->hasRole('Change Manager')) {
                return 'Change Manager';
            }

            if ($causer->hasRole('Admin') || $causer->hasRole('Super Admin')) {
                return 'Admin';
            }
        }

        return match ($newStatus) {
            RfcStatus::TICKET_CREATED, RfcStatus::NEEDS_REVISION => 'Admin',
            RfcStatus::RETURNED_TO_ADMIN,
            RfcStatus::APPROVED,
            RfcStatus::IN_PROGRESS,
            RfcStatus::IMPLEMENTED,
            RfcStatus::FAILED,
            RfcStatus::CLOSED => 'Change Manager',
            RfcStatus::REJECTED => in_array($oldStatus, [
                RfcStatus::SUBMITTED,
                RfcStatus::RETURNED_TO_ADMIN,
            ], true) ? 'Admin' : 'Change Manager',
            default => null,
        };
    }

    private function rejectionActorLabel(?object $causer, ?RfcStatus $oldStatus): string
    {
        if ($causer && method_exists($causer, 'hasRole')) {
            if ($causer->hasRole('Change Manager')) {
                return 'Change Manager';
            }

            if ($causer->hasRole('Admin') || $causer->hasRole('Super Admin')) {
                return 'Admin';
            }
        }

        return in_array($oldStatus, [RfcStatus::SUBMITTED, RfcStatus::RETURNED_TO_ADMIN], true)
            ? 'Admin'
            : 'Change Manager';
    }

    private function applyDashboardStates(array &$steps, RfcStatus $status, array $events): void
    {
        $hasEvent = fn (array $keys): bool => collect($events)->contains(
            fn (array $event): bool => in_array($event['key'], $keys, true)
        );

        $lastEvent = fn (array $keys): ?array => collect($events)
            ->filter(fn (array $event): bool => in_array($event['key'], $keys, true))
            ->last();

        $adminEvent = $lastEvent([
            RfcStatus::TICKET_CREATED->value,
            RfcStatus::NEEDS_REVISION->value,
            RfcStatus::RETURNED_TO_ADMIN->value,
            RfcStatus::REJECTED->value,
        ]);
        if ($adminEvent || in_array($status, [
            RfcStatus::TICKET_CREATED,
            RfcStatus::RETURNED_TO_ADMIN,
            RfcStatus::APPROVED,
            RfcStatus::IN_PROGRESS,
            RfcStatus::IMPLEMENTED,
            RfcStatus::FAILED,
            RfcStatus::CLOSED,
        ], true)) {
            $steps[1]['state'] = 'done';
            $steps[1]['description'] = 'Kelengkapan dokumen diperiksa';
        }

        if ($status === RfcStatus::DRAFT) {
            $steps[0]['state'] = 'current';
            $steps[0]['description'] = 'Menunggu pengajuan';
        } else {
            $steps[0]['state'] = 'done';
        }

        if ($status === RfcStatus::SUBMITTED) {
            $steps[1]['state'] = 'current';
            $steps[1]['description'] = 'Menunggu pemeriksaan Admin';
        }

        if ($status === RfcStatus::NEEDS_REVISION) {
            $steps[1]['state'] = 'warning';
            $steps[1]['description'] = 'Perlu revisi dari Pemohon';
        }

        if ($status === RfcStatus::RETURNED_TO_ADMIN) {
            $steps[1]['state'] = 'current';
            $steps[1]['description'] = 'Menunggu tindak lanjut Admin';
        }

        if ($hasEvent(['risk_assessment'])
            || in_array($status, [
                RfcStatus::APPROVED,
                RfcStatus::IN_PROGRESS,
                RfcStatus::IMPLEMENTED,
                RfcStatus::FAILED,
                RfcStatus::CLOSED,
                RfcStatus::RETURNED_TO_ADMIN,
            ], true)) {
            $steps[2]['state'] = 'done';
            $steps[2]['description'] = 'Review selesai';
        }

        if ($status === RfcStatus::TICKET_CREATED) {
            $steps[2]['state'] = 'current';
            $steps[2]['description'] = 'Menunggu review Change Manager';
        }

        if ($status === RfcStatus::APPROVED) {
            $steps[3]['state'] = 'current';
            $steps[3]['description'] = 'Menunggu pelaksanaan';
        }

        if ($status === RfcStatus::IN_PROGRESS) {
            $steps[3]['state'] = 'current';
            $steps[3]['description'] = 'Sedang diimplementasikan';
        }

        if (in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::CLOSED], true)) {
            $steps[3]['state'] = 'done';
            $steps[3]['description'] = 'Implementasi selesai';
        }

        if ($status === RfcStatus::IMPLEMENTED) {
            $steps[4]['state'] = 'current';
            $steps[4]['description'] = 'Menunggu penutupan';
        }

        if ($status === RfcStatus::CLOSED) {
            $steps[4]['state'] = 'done';
            $steps[4]['description'] = 'RFC telah ditutup';
        }

        if ($status === RfcStatus::REJECTED) {
            $rejectionEvent = $lastEvent([RfcStatus::REJECTED->value]);

            if (($rejectionEvent['actor_role'] ?? null) === 'Admin') {
                $steps[1]['state'] = 'danger';
                $steps[1]['description'] = 'Ditolak oleh Admin';
            } else {
                $steps[2]['state'] = 'danger';
                $steps[2]['description'] = 'Ditolak oleh Change Manager';
            }
        }

        if ($status === RfcStatus::FAILED) {
            $steps[3]['state'] = 'danger';
            $steps[3]['description'] = 'Implementasi gagal';
        }

        if ($status === RfcStatus::CANCELLED) {
            $steps[0]['state'] = 'danger';
            $steps[0]['description'] = 'Dibatalkan';
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function truncateDashboardSteps(array $steps, RfcStatus $status, array $events): array
    {
        if (! in_array($status, [
            RfcStatus::REJECTED,
            RfcStatus::FAILED,
            RfcStatus::CANCELLED,
            RfcStatus::CLOSED,
        ], true)) {
            return $steps;
        }

        if ($status === RfcStatus::CLOSED) {
            return $steps;
        }

        $limit = match ($status) {
            RfcStatus::CANCELLED => 1,
            RfcStatus::FAILED => 4,
            RfcStatus::REJECTED => (function () use ($events): int {
                $rejectionEvent = collect($events)->last(
                    fn (array $event): bool => $event['key'] === RfcStatus::REJECTED->value
                );

                return ($rejectionEvent['actor_role'] ?? null) === 'Admin' ? 2 : 3;
            })(),
            default => 5,
        };

        return array_slice($steps, 0, $limit);
    }
}
