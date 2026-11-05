<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
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
     * Mode Tim Rilis menyembunyikan nomor tiket, catatan keputusan internal,
     * evaluasi, dan penutupan tanpa mengubah tampilan Pemohon atau staf.
     * Instruksi disposisi hanya dibuka untuk penerima disposisi pada RFC ini.
     *
     * @return array{events: array<int, array<string, mixed>>, next_steps: array<int, array<string, string>>}
     */
    public function build(
        ChangeRequest $rfc,
        bool $forStaff = false,
        bool $forReleaseTeam = false,
        ?bool $isDispositionRecipient = null,
    ): array {
        if ($isDispositionRecipient === null) {
            $viewer = auth()->user();

            $isDispositionRecipient = $forReleaseTeam
                && $viewer !== null
                && $viewer->isReleaseTeam()
                && $rfc->hasReleaseTaskFor($viewer);
        }

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

        $pairedTicketTransitions = [];

        foreach ($activities as $ticketActivity) {
            if (data_get($ticketActivity->properties, 'event') !== 'ticket_created') {
                continue;
            }

            $transition = $activities
                ->filter(fn (Activity $candidate): bool => $candidate->getKey() < $ticketActivity->getKey()
                    && ! in_array($candidate->getKey(), $pairedTicketTransitions, true)
                    && data_get($candidate->properties, 'event') === null
                    && data_get($candidate->properties, 'new_status') === RfcStatus::TICKET_CREATED->value)
                ->sortByDesc('id')
                ->first();

            if ($transition) {
                $pairedTicketTransitions[] = $transition->getKey();
            }
        }

        foreach ($activities as $activity) {
            $sequence++;

            $newStatusRaw = data_get($activity->properties, 'new_status');
            $oldStatusRaw = data_get($activity->properties, 'old_status');
            $note = data_get($activity->properties, 'note');
            $eventType = data_get($activity->properties, 'event');

            if ($eventType === 'ticket_created') {
                $ticketNumber = (string) data_get($activity->properties, 'ticket_number', '');
                $priority = TicketPriority::tryFrom((string) data_get($activity->properties, 'priority'));
                $category = ServiceCategory::tryFrom((string) data_get($activity->properties, 'category'));
                $adminNote = data_get($activity->properties, 'admin_note');

                $staffNote = implode("\n", array_filter([
                    filled($priority) ? 'Prioritas: '.$priority->label() : null,
                    filled($category) ? 'Kategori layanan: '.$category->label() : null,
                    filled($adminNote) ? 'Catatan: '.trim((string) $adminNote) : null,
                ]));

                $events[] = [
                    'key' => RfcStatus::TICKET_CREATED->value,
                    'label' => $forStaff
                        ? "Tiket Change {$ticketNumber} dibuat dan diteruskan ke Change Manager"
                        : 'Diverifikasi Admin dan diteruskan ke Change Manager',
                    'note' => $forStaff
                        ? ($staffNote !== '' ? $staffNote : null)
                        : (! $forReleaseTeam && filled($ticketNumber) ? "Nomor tiket: {$ticketNumber}" : null),
                    'time' => $activity->created_at,
                    'tone' => 'success',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => 'Admin',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

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
                    'actor_role' => $this->actorRole($activity->causer, null, RfcStatus::NEEDS_REVISION) ?? 'Change Manager',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if (in_array($eventType, ['attachment_added', 'attachment_deleted'], true)) {
                $fileName = data_get($activity->properties, 'file_name');
                $attachmentId = (int) data_get($activity->properties, 'attachment_id');

                // Tautan lampiran hanya diberikan kepada staf dan Tim Rilis yang
                // melihat RFC ini. Lampiran yang sudah dihapus tidak lagi bisa
                // dibuka, dan Pemohon tetap memakai daftar lampiran di halaman
                // detail, bukan tautan pada riwayat.
                $attachmentUrl = null;

                if ($eventType === 'attachment_added'
                    && $attachmentId > 0
                    && ($forStaff || $forReleaseTeam)
                    && $rfc->attachments()->whereKey($attachmentId)->exists()) {
                    $attachmentUrl = route('change-requests.attachments.show', [
                        'rfc' => $rfc,
                        'attachment' => $attachmentId,
                    ]);
                }

                $events[] = [
                    'key' => $eventType,
                    'label' => $eventType === 'attachment_added' ? 'Lampiran ditambahkan' : 'Lampiran dihapus',
                    'note' => filled($fileName) ? 'Berkas: '.$fileName : null,
                    'time' => $activity->created_at,
                    'tone' => $eventType === 'attachment_added' ? 'info' : 'warning',
                    'actor_name' => $forStaff ? $activity->causer?->name : null,
                    'actor_role' => 'Pemohon',
                    'attachment_url' => $attachmentUrl,
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if ($eventType === 'evaluation_recorded') {
                // Evaluasi adalah catatan internal staf. Jangan tampilkan
                // kepada Pemohon dan jangan dianggap sebagai perubahan status.
                if (! $forStaff) {
                    continue;
                }

                $result = ImplementationResult::labelFor(
                    (string) data_get($activity->properties, 'implementation_result')
                );
                $evaluationNotes = trim((string) data_get($activity->properties, 'evaluation_notes', ''));

                $events[] = [
                    'key' => 'evaluation_recorded',
                    'label' => 'Evaluasi dicatat',
                    'note' => implode("\n", array_filter([
                        'Hasil implementasi: '.$result,
                        filled($evaluationNotes) ? 'Catatan evaluasi: '.$evaluationNotes : null,
                    ])),
                    'time' => $activity->created_at,
                    'tone' => 'info',
                    'actor_name' => $activity->causer?->name,
                    'actor_role' => 'Change Manager',
                    'legacy' => false,
                    'order' => $sequence,
                ];

                continue;
            }

            if ($eventType === 'release_disposition') {
                $recipientNames = data_get($activity->properties, 'recipient_names', []);
                $recipientNames = is_array($recipientNames)
                    ? array_values(array_filter(array_map(
                        fn (mixed $name): string => trim((string) $name),
                        $recipientNames,
                    )))
                    : [];
                $instruction = trim((string) data_get($activity->properties, 'instruction', ''));

                // Instruksi dan penerima adalah perintah kerja untuk penerima
                // disposisi. Pemohon hanya diberi tahu bahwa RFC sudah diteruskan.
                $canSeeDispositionDetails = $forStaff || $isDispositionRecipient;
                $dispositionNote = $canSeeDispositionDetails
                    ? implode("\n", array_filter([
                        'Penerima: '.($recipientNames !== [] ? implode(', ', $recipientNames) : '-'),
                        filled($instruction) ? 'Instruksi: '.$instruction : null,
                    ]))
                    : null;

                $events[] = [
                    'key' => 'release_disposition',
                    'label' => $canSeeDispositionDetails ? 'Disposisi ke Tim Rilis' : 'Diteruskan ke Tim Rilis',
                    'note' => $dispositionNote,
                    'time' => $activity->created_at,
                    'tone' => 'info',
                    'actor_name' => $canSeeDispositionDetails ? $activity->causer?->name : null,
                    'actor_role' => 'Change Manager',
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

            // Kejadian tiket baru dibangun dari activity audit khusus agar
            // nomor tiket, prioritas, dan catatan Admin tersedia tanpa duplikasi.
            // Hanya transisi yang berpasangan dengan activity khusus yang
            // dilewati, sehingga data transisi lama tetap tampil di riwayat.
            if ($newStatus === RfcStatus::TICKET_CREATED
                && in_array($activity->getKey(), $pairedTicketTransitions, true)) {
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

        $emergencyClassificationNote = null;

        if ($forStaff) {
            $emergencyActivity = $activities->last(
                fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'emergency_classification_review'
            );

            $emergencyClassificationNote = $emergencyActivity
                ? trim((string) $emergencyActivity->description)
                : null;
        }

        if ($rfc->riskAssessment) {
            $risk = $rfc->riskAssessment;
            $riskDetails = trim(implode(' • ', array_filter([
                filled($risk->risk_level) ? 'Risiko: '.$risk->risk_level : null,
                filled($risk->impact_level) ? 'Dampak: '.$risk->impact_level : null,
                filled($risk->classification) ? 'Klasifikasi: '.$risk->classification : null,
            ])));
            $riskNote = $forStaff
                ? trim(implode("\n", array_filter([$riskDetails, $emergencyClassificationNote])))
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
            $this->mergeOrAddDecision($events, $decision, $forStaff, $forReleaseTeam, $sequence);
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
                // Kejadian implementasi sudah disusun dalam versi aman untuk
                // Pemohon (hanya hasil dan ringkasan), jadi catatannya dipertahankan.
                if (($event['implementation'] ?? false) === true) {
                    $event['actor_name'] = null;

                    continue;
                }

                $isVisibleDispositionEvent = $isDispositionRecipient
                    && $event['key'] === 'release_disposition';

                if (! $isVisibleDispositionEvent && ! in_array($event['key'], [
                    RfcStatus::REJECTED->value,
                    RfcStatus::NEEDS_REVISION->value,
                    RfcStatus::FAILED->value,
                    RfcStatus::CANCELLED->value,
                    RfcStatus::TICKET_CREATED->value,
                    'rfc_revised',
                    'revision_reason',
                ], true)) {
                    $event['note'] = null;
                }

                // Pelaku pembatalan boleh terlihat oleh Pemohon hanya jika pelakunya
                // sendiri, bukan staf yang membatalkan lewat jalur lain.
                if (! $isVisibleDispositionEvent
                    && ($event['key'] !== RfcStatus::CANCELLED->value
                        || ($event['actor_role'] ?? null) !== 'Pemohon')) {
                    $event['actor_name'] = null;
                }

                // Tim Rilis hanya menerima status dan ringkasan pelaksanaan;
                // seluruh catatan keputusan, evaluasi, dan penutupan internal
                // tidak boleh ikut terkirim ke halaman detail.
                if ($forReleaseTeam && ! $isVisibleDispositionEvent) {
                    $event['actor_name'] = null;
                    $event['note'] = null;
                }
            }
            unset($event);
        }

        return [
            'events' => $events,
            'next_steps' => $this->nextSteps($rfc),
        ];
    }

    public function cancellationReason(ChangeRequest $rfc): ?string
    {
        $activity = Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::CANCELLED->value
                && filled(data_get($activity->properties, 'note'))
            );

        return $activity ? trim((string) data_get($activity->properties, 'note')) : null;
    }

    /**
     * Langkah mendatang hanya untuk RFC yang masih berjalan. FAILED bukan
     * status akhir karena masih dapat ditutup setelah evaluasi.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function nextSteps(ChangeRequest $rfc): array
    {
        $status = RfcStatus::fromValue($rfc->status);

        if (! $status) {
            return [];
        }

        if (in_array($status, [
            RfcStatus::REJECTED,
            RfcStatus::CANCELLED,
            RfcStatus::CLOSED,
        ], true)) {
            return [];
        }

        $submission = ['key' => 'submission', 'label' => 'Diajukan'];
        $cmReview = ['key' => 'cm_review', 'label' => 'Ditinjau Change Manager'];
        $implementation = ['key' => 'implementation', 'label' => 'Dikerjakan Tim Rilis'];
        $evaluation = ['key' => 'evaluation', 'label' => 'Evaluasi'];
        $closure = ['key' => 'closure', 'label' => 'Ditutup'];

        return match ($status) {
            RfcStatus::APPROVED, RfcStatus::IN_PROGRESS => [$implementation, $evaluation, $closure],
            RfcStatus::IMPLEMENTED, RfcStatus::FAILED => $rfc->implementation?->evaluated_at !== null
                ? [$closure]
                : [$evaluation, $closure],
            RfcStatus::DRAFT => [$submission, $cmReview, $implementation, $evaluation, $closure],
            RfcStatus::NEEDS_REVISION => [
                ['key' => 'revision', 'label' => 'Kirim Revisi'],
                $cmReview,
                $implementation,
                $evaluation,
                $closure,
            ],
            default => [$cmReview, $implementation, $evaluation, $closure],
        };
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

        $submittedEvent = $lastEvent([RfcStatus::SUBMITTED->value, 'draft_created'])
            ?? $lastEvent([RfcStatus::TICKET_CREATED->value]);
        $cmEvent = $lastEvent([
            'risk_assessment',
            RfcStatus::TICKET_CREATED->value,
            RfcStatus::NEEDS_REVISION->value,
            RfcStatus::RETURNED_TO_ADMIN->value,
            RfcStatus::APPROVED->value,
            RfcStatus::REJECTED->value,
        ]);
        $workEvent = $lastEvent([
            RfcStatus::IN_PROGRESS->value,
            RfcStatus::IMPLEMENTED->value,
            RfcStatus::FAILED->value,
        ]);
        $evaluationEvent = $lastEvent(['evaluation_recorded']);
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
                'key' => 'cm',
                'label' => 'Ditinjau Change Manager',
                'description' => 'Menunggu review Change Manager',
                'time' => $cmEvent['time'] ?? null,
                'state' => 'pending',
            ],
            [
                'key' => 'work',
                'label' => 'Dikerjakan Tim Rilis',
                'description' => 'Menunggu pelaksanaan',
                'time' => $workEvent['time'] ?? null,
                'state' => 'pending',
            ],
            [
                'key' => 'evaluation',
                'label' => 'Evaluasi',
                'description' => 'Menunggu evaluasi',
                'time' => $evaluationEvent['time'] ?? $rfc->implementation?->evaluated_at,
                'state' => 'pending',
            ],
            [
                'key' => 'closed',
                // Label hasil keputusan: closed mengikuti hasil implementasi,
                // status lain tampil netral sampai penutupan terjadi.
                'label' => $status === RfcStatus::CLOSED ? $rfc->displayStatusLabel() : 'Ditutup',
                'description' => 'Menunggu penutupan',
                'time' => $doneEvent['time'] ?? null,
                'state' => 'pending',
            ],
        ];

        $this->applyDashboardStates($steps, $status, $events, $rfc);

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
        $canShowActor = $forStaff
            || ($status === RfcStatus::CANCELLED && $actorRole === 'Pemohon');
        $actorName = $canShowActor ? $activity->causer?->name : null;
        $safeNote = filled($note) ? trim((string) $note) : null;
        $eventType = data_get($activity->properties, 'event');
        $implementationResult = data_get($activity->properties, 'implementation_result');
        $isImplementation = filled($implementationResult);

        if ($eventType === 'closure_recorded') {
            $safeNote = $forStaff ? $this->closureEventNote($activity) : null;
        } elseif ($isImplementation) {
            $safeNote = $this->implementationEventNote($activity, $forStaff);
        }

        if ($eventType === 'legacy_forwarded') {
            $actorRole = 'Sistem';
        }

        $ticketCreatedLabel = match (true) {
            $eventType === 'legacy_forwarded' => 'Diteruskan ke Change Manager (penyesuaian alur)',
            in_array($oldStatus, [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true) => 'RFC diajukan dan diteruskan ke Change Manager',
            default => 'Diverifikasi Admin dan diteruskan ke Change Manager',
        };

        [$label, $tone, $key] = match ($status) {
            RfcStatus::DRAFT => ['Disimpan sebagai draft', 'neutral', 'draft_created'],
            RfcStatus::SUBMITTED => ['RFC diajukan', 'info', RfcStatus::SUBMITTED->value],
            RfcStatus::TICKET_CREATED => [$ticketCreatedLabel, 'success', RfcStatus::TICKET_CREATED->value],
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
            RfcStatus::IMPLEMENTED => ['Implementasi berhasil dicatat — menunggu evaluasi', 'success', RfcStatus::IMPLEMENTED->value],
            RfcStatus::FAILED => ['Implementasi gagal atau rollback — menunggu evaluasi', 'danger', RfcStatus::FAILED->value],
            RfcStatus::CLOSED => [$this->closureEventLabel($activity), $this->closureEventTone($activity), RfcStatus::CLOSED->value],
            RfcStatus::CANCELLED => ['Dibatalkan', 'danger', RfcStatus::CANCELLED->value],
        };

        if (! $forStaff && in_array($status, [RfcStatus::RETURNED_TO_ADMIN, RfcStatus::APPROVED], true)) {
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
            'implementation' => $isImplementation,
            'order' => $order,
        ];
    }

    private function closureEventNote(Activity $activity): ?string
    {
        $result = ImplementationResult::labelFor(
            (string) data_get($activity->properties, 'implementation_result')
        );
        $outcome = (string) data_get($activity->properties, 'outcome');
        $outcomeLabel = match ($outcome) {
            'successful' => 'Berhasil',
            'unsuccessful' => 'Tidak berhasil',
            default => null,
        };
        $closureNotes = trim((string) data_get($activity->properties, 'closure_notes', ''));
        $evaluationNotes = trim((string) data_get($activity->properties, 'evaluation_notes', ''));

        return implode("\n", array_filter([
            'Hasil implementasi: '.$result,
            $outcomeLabel !== null ? 'Outcome: '.$outcomeLabel : null,
            $closureNotes !== '' ? 'Catatan penutupan: '.$closureNotes : null,
            $evaluationNotes !== '' ? 'Ringkasan evaluasi: '.$evaluationNotes : null,
        ]));
    }

    /**
     * Outcome penutupan dari activity: kolom outcome lebih dahulu, dengan
     * hasil implementasi sebagai cadangan; RFC lama tanpa data tetap null.
     */
    private function activityClosureOutcome(Activity $activity): ?string
    {
        $outcome = data_get($activity->properties, 'outcome');

        if (in_array($outcome, ['successful', 'unsuccessful'], true)) {
            return $outcome;
        }

        return match ((string) data_get($activity->properties, 'implementation_result')) {
            ImplementationResult::SUCCESS->value => 'successful',
            ImplementationResult::FAILED->value,
            ImplementationResult::ROLLED_BACK->value => 'unsuccessful',
            default => null,
        };
    }

    private function closureEventLabel(Activity $activity): string
    {
        return match ($this->activityClosureOutcome($activity)) {
            'successful' => 'RFC ditutup — berhasil',
            'unsuccessful' => 'RFC ditutup — tidak berhasil',
            default => 'RFC ditutup',
        };
    }

    private function closureEventTone(Activity $activity): string
    {
        return match ($this->activityClosureOutcome($activity)) {
            'successful' => 'success',
            'unsuccessful' => 'danger',
            default => 'neutral',
        };
    }

    private function implementationEventNote(Activity $activity, bool $forStaff): ?string
    {
        $result = ImplementationResult::labelFor((string) data_get($activity->properties, 'implementation_result'));
        $summary = trim((string) data_get($activity->properties, 'summary', ''));

        if (! $forStaff) {
            return implode("\n", array_filter([
                'Hasil: '.$result,
                filled($summary) ? 'Ringkasan: '.$summary : null,
            ]));
        }

        $startedAt = $this->activityDate(data_get($activity->properties, 'started_at'));
        $finishedAt = $this->activityDate(data_get($activity->properties, 'finished_at'));
        $executorName = trim((string) data_get($activity->properties, 'executor_name', ''));
        $reportSource = trim((string) data_get($activity->properties, 'report_source', ''));
        $testResults = trim((string) data_get($activity->properties, 'test_results', ''));
        $failureNotes = trim((string) data_get($activity->properties, 'failure_notes', ''));
        $followUpAction = data_get($activity->properties, 'follow_up_action');
        $followUpNotes = trim((string) data_get($activity->properties, 'follow_up_notes', ''));
        $evidenceFiles = data_get($activity->properties, 'evidence_files', []);
        $evidenceFiles = is_array($evidenceFiles) ? $evidenceFiles : [];

        $lines = [
            'Hasil: '.$result,
            'Pelaksana: '.($executorName !== '' ? $executorName : '-'),
            ($startedAt !== null || $finishedAt !== null)
                ? 'Waktu pelaksanaan: '.($startedAt ?? '-').' s.d. '.($finishedAt ?? '-')
                : null,
            'Sumber laporan: '.($reportSource !== '' ? $reportSource : '-'),
            'Ringkasan: '.($summary !== '' ? $summary : '-'),
            'Hasil pengujian: '.($testResults !== '' ? $testResults : '-'),
        ];

        if ($failureNotes !== '') {
            $lines[] = 'Catatan kegagalan/rollback: '.$failureNotes;
        }

        if (filled($followUpAction)) {
            $followUp = 'Tindak lanjut: '.FollowUpAction::labelFor($followUpAction);

            if ($followUpNotes !== '') {
                $followUp .= ' — '.$followUpNotes;
            }

            $lines[] = $followUp;
        }

        $lines[] = 'Jumlah bukti: '.count($evidenceFiles).' file';

        foreach ($evidenceFiles as $file) {
            if (! is_array($file)) {
                continue;
            }

            $lines[] = sprintf(
                'Bukti: %s (%s, %s, SHA-256 %s)',
                (string) ($file['name'] ?? '-'),
                $this->formatFileSize((int) ($file['size'] ?? 0)),
                (string) ($file['mime'] ?? '-'),
                (string) ($file['checksum'] ?? '-'),
            );
        }

        return implode("\n", array_filter($lines));
    }

    private function activityDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->copy()->locale('id')->translatedFormat('d M Y, H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, ',', '.').' MB';
        }

        return number_format($bytes / 1024, 0, ',', '.').' KB';
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

    private function mergeOrAddDecision(
        array &$events,
        Decision $decision,
        bool $forStaff,
        bool $forReleaseTeam,
        int $order,
    ): void {
        $decisionTime = Carbon::parse($decision->decision_date ?? $decision->created_at ?? now());
        $status = DecisionStatus::tryFrom((string) $decision->decision_status);
        $reason = filled($decision->reason) ? trim((string) $decision->reason) : null;

        // Nama pejabat diambil dari snapshot agar keputusan lama tetap tampil
        // benar dan keputusan baru menyebut nama Change Manager yang mencatat.
        $actorName = filled($decision->snapshot_authority_name)
            ? trim((string) $decision->snapshot_authority_name)
            : $decision->recordedBy?->name;

        $key = $status === DecisionStatus::REVISION_REQUIRED
            ? RfcStatus::NEEDS_REVISION->value
            : $status?->value;
        $baseLabel = match ($status) {
            DecisionStatus::APPROVED => 'Disetujui oleh Change Manager',
            DecisionStatus::REJECTED => 'Ditolak oleh Change Manager',
            DecisionStatus::REVISION_REQUIRED => $forStaff ? 'Perlu revisi oleh Change Manager' : 'Perlu revisi',
            DecisionStatus::RETURNED_TO_ADMIN => $forStaff ? 'Dikembalikan ke Admin' : 'Sedang ditinjau',
            default => 'Keputusan Change Manager (legacy)',
        };
        $label = $forStaff && filled($actorName)
            ? $baseLabel.' — '.$actorName
            : $baseLabel;
        $tone = match ($status) {
            DecisionStatus::APPROVED => 'success',
            DecisionStatus::REJECTED => 'danger',
            DecisionStatus::REVISION_REQUIRED, DecisionStatus::RETURNED_TO_ADMIN => 'warning',
            default => 'neutral',
        };

        $noteVisibleToRequester = in_array($status, [
            DecisionStatus::REJECTED,
            DecisionStatus::REVISION_REQUIRED,
        ], true);
        $noteVisible = $forStaff || (! $forReleaseTeam && $noteVisibleToRequester);

        if ($key) {
            foreach ($events as $index => $event) {
                if ($event['key'] !== $key) {
                    continue;
                }

                if (abs($event['time']->getTimestamp() - $decisionTime->getTimestamp()) <= 300) {
                    $events[$index]['label'] = $label;
                    $events[$index]['actor_name'] = $forStaff ? $actorName : null;
                    $events[$index]['actor_role'] = 'Change Manager';

                    if ($reason && $noteVisible) {
                        $events[$index]['note'] = $events[$index]['note'] ?: $reason;
                    }

                    return;
                }
            }
        }

        $events[] = [
            'key' => $key ?? 'legacy_decision',
            'label' => $label,
            'note' => $reason && $noteVisible ? $reason : null,
            'time' => $decisionTime,
            'tone' => $tone,
            'actor_name' => $forStaff ? $actorName : null,
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

            // Pengajuan langsung ke Change Manager sudah menjadi kejadian
            // pengajuan; jangan tambahkan kejadian sintetis kedua.
            if ($event['key'] === RfcStatus::TICKET_CREATED->value
                && ($event['actor_role'] ?? null) === 'Pemohon') {
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
            RfcStatus::IMPLEMENTED => 'Implementasi berhasil dicatat — menunggu evaluasi',
            RfcStatus::FAILED => 'Implementasi gagal atau rollback — menunggu evaluasi',
            RfcStatus::CLOSED => match ($rfc->implementationOutcome()) {
                'successful' => 'RFC ditutup — berhasil',
                'unsuccessful' => 'RFC ditutup — tidak berhasil',
                default => 'RFC ditutup',
            },
            null => 'Pembaruan status (legacy): '.RfcStatus::labelFor($rfc->status),
            default => 'Status saat ini: '.$status->label(),
        };

        $tone = match ($status) {
            RfcStatus::REJECTED, RfcStatus::CANCELLED, RfcStatus::FAILED => 'danger',
            RfcStatus::IMPLEMENTED => 'success',
            RfcStatus::CLOSED => match ($rfc->implementationOutcome()) {
                'successful' => 'success',
                'unsuccessful' => 'danger',
                default => 'neutral',
            },
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
            $note = $rfc->rejection_note
                ?: $this->decisionReason($rfc, DecisionStatus::REVISION_REQUIRED->value);

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
            RfcStatus::SUBMITTED => 'Pemohon',
            RfcStatus::TICKET_CREATED => in_array($oldStatus, [
                RfcStatus::DRAFT,
                RfcStatus::NEEDS_REVISION,
            ], true) ? 'Pemohon' : 'Admin',
            RfcStatus::NEEDS_REVISION => $oldStatus === RfcStatus::TICKET_CREATED
                ? 'Change Manager'
                : 'Admin',
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
            RfcStatus::CANCELLED => 'Pemohon',
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

    private function applyDashboardStates(array &$steps, RfcStatus $status, array $events, ChangeRequest $rfc): void
    {
        $hasEvent = fn (array $keys): bool => collect($events)->contains(
            fn (array $event): bool => in_array($event['key'], $keys, true)
        );

        if ($status === RfcStatus::DRAFT) {
            $steps[0]['state'] = 'current';
            $steps[0]['description'] = 'Menunggu pengajuan';
        } else {
            $steps[0]['state'] = 'done';
        }

        if (in_array($status, [RfcStatus::SUBMITTED, RfcStatus::TICKET_CREATED], true)) {
            $steps[1]['state'] = 'current';
            $steps[1]['description'] = 'Menunggu review Change Manager';
        }

        if ($status === RfcStatus::NEEDS_REVISION) {
            $steps[1]['state'] = 'warning';
            $steps[1]['description'] = 'Perlu revisi dari Pemohon';
        }

        if ($status === RfcStatus::RETURNED_TO_ADMIN) {
            $steps[1]['state'] = 'warning';
            $steps[1]['description'] = 'Menunggu tindak lanjut';
        }

        if ($hasEvent(['risk_assessment'])
            || in_array($status, [
                RfcStatus::APPROVED,
                RfcStatus::IN_PROGRESS,
                RfcStatus::IMPLEMENTED,
                RfcStatus::FAILED,
                RfcStatus::CLOSED,
            ], true)) {
            $steps[1]['state'] = 'done';
            $steps[1]['description'] = 'Review selesai';
        }

        if ($status === RfcStatus::TICKET_CREATED) {
            $steps[1]['state'] = 'current';
            $steps[1]['description'] = 'Menunggu keputusan Change Manager';
        }

        if ($status === RfcStatus::APPROVED) {
            $steps[2]['state'] = 'current';
            $steps[2]['description'] = 'Menunggu pelaksanaan';
        }

        if ($status === RfcStatus::IN_PROGRESS) {
            $steps[2]['state'] = 'current';
            $steps[2]['description'] = 'Sedang diimplementasikan';
        }

        if (in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)) {
            // Dikerjakan selesai: hijau untuk berhasil, merah untuk gagal atau
            // rollback; langkah berikutnya adalah evaluasi sebelum penutupan.
            $steps[2]['state'] = $status === RfcStatus::FAILED ? 'danger' : 'done';
            $steps[2]['description'] = $status === RfcStatus::FAILED
                ? 'Implementasi gagal'
                : 'Implementasi selesai';

            if ($rfc->implementation?->evaluated_at !== null) {
                // Evaluasi sudah tercatat: langkah berikutnya hanya penutupan.
                $steps[3]['state'] = 'done';
                $steps[3]['description'] = 'Evaluasi tercatat';
                $steps[4]['state'] = 'current';
                $steps[4]['description'] = 'Menunggu penutupan';
            } else {
                $steps[3]['state'] = 'current';
                $steps[3]['description'] = 'Menunggu evaluasi';
            }
        }

        if ($status === RfcStatus::CLOSED) {
            $outcome = $rfc->implementationOutcome();

            $steps[2]['state'] = $outcome === 'unsuccessful' ? 'danger' : 'done';
            $steps[2]['description'] = $outcome === 'unsuccessful'
                ? 'Implementasi gagal'
                : 'Implementasi selesai';
            $steps[3]['state'] = 'done';
            $steps[3]['description'] = 'Evaluasi tercatat';
            $steps[4]['state'] = $outcome === 'unsuccessful' ? 'danger' : 'done';
            $steps[4]['description'] = 'RFC telah ditutup';
        }

        if ($status === RfcStatus::REJECTED) {
            $steps[1]['state'] = 'danger';
            $steps[1]['description'] = 'Ditolak';
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
        // FAILED dan CLOSED memakai seluruh linimasa: FAILED masih menunggu
        // evaluasi dan penutupan, sedangkan CLOSED adalah status akhir yang
        // menandai hasilnya di langkah penutupan.
        if (! in_array($status, [RfcStatus::REJECTED, RfcStatus::CANCELLED], true)) {
            return $steps;
        }

        $limit = match ($status) {
            RfcStatus::CANCELLED => 1,
            RfcStatus::REJECTED => 2,
            default => count($steps),
        };

        return array_slice($steps, 0, $limit);
    }
}
