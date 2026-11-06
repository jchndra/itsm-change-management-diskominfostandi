<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeManagerTicketNotification;
use App\Services\TicketNumberGenerator;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class ReviewCompleteness extends Component
{
    public ChangeRequest $rfc;

    public string $action = RfcStatus::TICKET_CREATED->value;

    public string $note = '';

    public string $priority = '';

    public string $category = '';

    public string $admin_note = '';

    public bool $data_complete = false;

    public bool $attachments_verified = false;

    public bool $application_verified = false;

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('reviewCompleteness', $rfc);
        $this->rfc = $rfc->load([
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'decisions.recordedBy',
        ]);

        if (! in_array(RfcStatus::fromValue($rfc->status), [
            RfcStatus::SUBMITTED,
            RfcStatus::RETURNED_TO_ADMIN,
        ], true)) {
            session()->flash('error', 'Halaman Buat Tiket Change hanya tersedia untuk RFC yang menunggu verifikasi Admin.');

            $this->redirectRoute('change-requests.show', ['rfc' => $rfc]);

            return;
        }

        $this->priority = $rfc->priority?->value
            ?? ($rfc->emergency_unconfirmed ? TicketPriority::URGENT->value : TicketPriority::MEDIUM->value);
        $this->category = $rfc->category?->value ?? '';
        $this->admin_note = (string) ($rfc->admin_note ?? '');
    }

    public function createTicket(): void
    {
        $this->authorize('reviewCompleteness', $this->rfc);
        $this->action = RfcStatus::TICKET_CREATED->value;

        $this->processTicket();
    }

    public function requestRevision(): void
    {
        $this->authorize('reviewCompleteness', $this->rfc);
        $this->action = RfcStatus::NEEDS_REVISION->value;

        $this->processReturnToRequester();
    }

    public function rejectRequest(): void
    {
        $this->authorize('reviewCompleteness', $this->rfc);
        $this->action = RfcStatus::REJECTED->value;

        $this->processReturnToRequester();
    }

    /**
     * Tetap dipertahankan untuk kompatibilitas pemanggilan lama.
     */
    public function submitReview(): void
    {
        $this->authorize('reviewCompleteness', $this->rfc);

        $this->validate([
            'action' => ['required', Rule::in([
                RfcStatus::TICKET_CREATED->value,
                RfcStatus::NEEDS_REVISION->value,
                RfcStatus::REJECTED->value,
            ])],
        ]);

        if ($this->action === RfcStatus::TICKET_CREATED->value) {
            $this->processTicket();

            return;
        }

        $this->processReturnToRequester();
    }

    private function processTicket(): void
    {
        $validated = $this->validate([
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'data_complete' => ['accepted'],
            'attachments_verified' => ['accepted'],
            'application_verified' => ['accepted'],
        ], [
            'priority.required' => 'Prioritas tiket wajib dipilih.',
            'priority.enum' => 'Prioritas tiket tidak valid.',
            'category.required' => 'Kategori layanan wajib dipilih.',
            'category.enum' => 'Kategori layanan tidak valid.',
            'admin_note.max' => 'Catatan untuk Change Manager maksimal 1000 karakter.',
            'data_complete.accepted' => 'Checklist "Data RFC sudah lengkap" wajib dicentang.',
            'attachments_verified.accepted' => 'Checklist "Lampiran sudah sesuai" wajib dicentang.',
            'application_verified.accepted' => 'Checklist "Sistem/aplikasi sudah sesuai" wajib dicentang.',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $ticketNumber = null;
        $checklist = [
            'data_rfc_lengkap' => (bool) $validated['data_complete'],
            'lampiran_sesuai' => (bool) $validated['attachments_verified'],
            'sistem_aplikasi_sesuai' => (bool) $validated['application_verified'],
        ];

        try {
            DB::transaction(function () use ($user, $checklist, &$ticketNumber): void {
                $lockedRfc = ChangeRequest::query()
                    ->whereKey($this->rfc->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentStatus = RfcStatus::fromValue($lockedRfc->status);

                if (! in_array($currentStatus, [
                    RfcStatus::SUBMITTED,
                    RfcStatus::RETURNED_TO_ADMIN,
                ], true)) {
                    throw new InvalidRfcStatusTransitionException(
                        'Tiket Change hanya dapat dibuat dari RFC berstatus Diajukan atau Dikembalikan ke Admin.'
                    );
                }

                $ticketNumber = $lockedRfc->ticket_number ?: TicketNumberGenerator::generate();

                $lockedRfc->update([
                    'ticket_number' => $ticketNumber,
                    'priority' => $this->priority,
                    'category' => $this->category,
                    'admin_note' => filled($this->admin_note) ? trim($this->admin_note) : null,
                    'ticket_created_at' => now(),
                    'ticket_created_by_id' => $user->id,
                    'rejection_note' => null,
                ]);

                RfcStatusTransition::transitionTo($lockedRfc, RfcStatus::TICKET_CREATED, $user);

                if (function_exists('activity')) {
                    activity('rfc')
                        ->performedOn($lockedRfc)
                        ->causedBy($user)
                        ->withProperties([
                            'event' => 'ticket_created',
                            'ticket_number' => $ticketNumber,
                            'priority' => $this->priority,
                            'category' => $this->category,
                            'admin_note' => filled($this->admin_note) ? trim($this->admin_note) : null,
                            'checklist' => $checklist,
                        ])
                        ->log("Tiket Change {$ticketNumber} dibuat dan diteruskan ke Change Manager.");
                }

                $this->rfc = $lockedRfc;
            });
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        $this->rfc = $this->rfc->fresh([
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'decisions.recordedBy',
        ]) ?? $this->rfc;

        User::role('Change Manager')->get()->each(
            fn (User $changeManager) => $changeManager->notify(
                new ChangeManagerTicketNotification($this->rfc, $ticketNumber)
            )
        );

        session()->flash('message', "Tiket Change {$ticketNumber} dibuat dan dikirim ke Change Manager.");

        $this->redirectRoute('change-requests.index');
    }

    private function processReturnToRequester(): void
    {
        $this->validate([
            'action' => ['required', Rule::in([
                RfcStatus::NEEDS_REVISION->value,
                RfcStatus::REJECTED->value,
            ])],
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'action.in' => 'Aksi verifikasi tidak valid.',
            'note.required' => 'Catatan masukan atau alasan wajib diisi.',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $targetStatus = RfcStatus::fromValue($this->action);

        try {
            DB::transaction(function () use ($user, $targetStatus): void {
                $lockedRfc = ChangeRequest::query()
                    ->whereKey($this->rfc->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedRfc->update(['rejection_note' => $this->note]);
                RfcStatusTransition::transitionTo($lockedRfc, $targetStatus, $user, $this->note);

                $this->rfc = $lockedRfc;
            });

            session()->flash(
                'message',
                match ($this->action) {
                    RfcStatus::NEEDS_REVISION->value => 'Dokumen RFC telah dikembalikan ke Pemohon untuk perbaikan.',
                    default => 'Dokumen RFC telah ditolak.',
                }
            );
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        $this->redirectRoute('change-requests.index');
    }

    public function render()
    {
        $returnNote = null;

        if ($this->rfc->status === RfcStatus::RETURNED_TO_ADMIN->value) {
            $returnNote = $this->rfc->decisions
                ->where('decision_status', DecisionStatus::RETURNED_TO_ADMIN->value)
                ->sortByDesc('decision_date')
                ->first()?->reason;

            if (! filled($returnNote)) {
                $returnActivity = Activity::query()
                    ->where('subject_type', $this->rfc->getMorphClass())
                    ->where('subject_id', $this->rfc->id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->last(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::RETURNED_TO_ADMIN->value
                        && filled(data_get($activity->properties, 'note')));

                if ($returnActivity) {
                    $returnNote = data_get($returnActivity->properties, 'note');
                }
            }
        }

        return view('livewire.change-requests.review-completeness', [
            'returnNote' => $returnNote,
        ])->title('Buat Tiket Change - '.$this->rfc->displayIdentifier());
    }
}
