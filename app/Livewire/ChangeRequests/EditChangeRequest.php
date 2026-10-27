<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Livewire\ChangeRequests\Concerns\ValidatesChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\User;
use App\Services\RfcAttachmentService;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

#[Layout('layouts.app')]
class EditChangeRequest extends Component
{
    use ValidatesChangeRequest;
    use WithFileUploads;

    public ChangeRequest $rfc;

    public string $title = '';

    public string $application_id = '';

    public string $change_type = '';

    public string $description = '';

    public string $reason_for_change = '';

    public string $impact_description = '';

    public string $target_implementation_date = '';

    public bool $is_physical_letter = false;

    public string $physical_letter_number = '';

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    public ?UploadedFile $approval_letter = null;

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('update', $rfc);

        $this->rfc = $rfc;
        $this->title = (string) $rfc->title;
        $this->application_id = (string) $rfc->application_id;
        $this->change_type = (string) ($rfc->change_type ?? '');
        $this->description = (string) ($rfc->description ?? '');
        $this->reason_for_change = (string) $rfc->reason_for_change;
        $this->impact_description = (string) ($rfc->impact_description ?? '');
        $this->target_implementation_date = $rfc->target_implementation_date?->format('Y-m-d\TH:i') ?? '';
        $this->is_physical_letter = (bool) $rfc->is_physical_letter;
        $this->physical_letter_number = (string) ($rfc->physical_letter_number ?? '');
    }

    public function saveChanges(): void
    {
        $this->authorize('update', $this->rfc);
        $this->validateChangeRequest($this->existingAttachmentCount());

        $newAttachments = [];

        try {
            DB::transaction(function () use (&$newAttachments): void {
                $this->persistChanges();

                $newAttachments = $this->storeAttachments();

                $approvalLetter = $this->storeApprovalLetter();

                if ($approvalLetter !== null) {
                    $newAttachments[] = $approvalLetter;
                }
            });
        } catch (Throwable $exception) {
            app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

            throw $exception;
        }

        $this->attachments = [];
        $this->approval_letter = null;

        session()->flash('message', 'Perubahan RFC berhasil disimpan.');

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function submit(): void
    {
        $this->authorize('update', $this->rfc);
        $this->validateChangeRequest($this->existingAttachmentCount());
        $this->validatePhysicalLetterAttachment($this->existingAttachmentCount());
        $this->validateApprovalLetterForSubmission(
            hasExisting: $this->hasExistingApprovalLetter($this->rfc),
            hasPending: $this->approval_letter !== null,
        );

        $oldStatus = RfcStatus::fromValue($this->rfc->status);

        if (! $oldStatus) {
            $this->addError('status', 'Status RFC saat ini tidak dikenali.');

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $newAttachments = [];

        try {
            DB::transaction(function () use ($user, &$oldStatus, &$newAttachments): void {
                // Kunci baris agar dua klik submit tidak menerbitkan nomor ganda.
                $this->rfc = ChangeRequest::query()
                    ->whereKey($this->rfc->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentStatus = RfcStatus::fromValue($this->rfc->status);

                if ($currentStatus === null) {
                    throw new InvalidRfcStatusTransitionException('Status RFC saat ini tidak dikenali.');
                }

                $oldStatus = $currentStatus;

                $this->persistChanges();
                $newAttachments = $this->storeAttachments();

                $approvalLetter = $this->storeApprovalLetter();

                if ($approvalLetter !== null) {
                    $newAttachments[] = $approvalLetter;
                }

                if ($currentStatus === RfcStatus::NEEDS_REVISION) {
                    // Pastikan alasan revisi sudah tercatat sebelum rejection_note dikosongkan.
                    $this->preserveRevisionReason();
                }

                RfcStatusTransition::transitionTo($this->rfc, RfcStatus::SUBMITTED, $user);

                if ($currentStatus === RfcStatus::NEEDS_REVISION) {
                    $this->rfc->update(['rejection_note' => null]);
                }
            });
        } catch (InvalidRfcStatusTransitionException $exception) {
            app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);
            $this->rfc->refresh();
            $this->addError('status', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

            throw $exception;
        }

        $this->attachments = [];
        $this->approval_letter = null;

        session()->flash(
            'message',
            $oldStatus === RfcStatus::NEEDS_REVISION
                ? "Revisi {$this->rfc->displayIdentifier()} berhasil dikirim."
                : "{$this->rfc->displayIdentifier()} berhasil diajukan."
        );

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $this->authorize('update', $this->rfc);

        /** @var ChangeRequestAttachment $attachment */
        $attachment = $this->rfc->attachments()->findOrFail($attachmentId);

        if ($attachment->isApprovalLetter()) {
            $this->addError('attachment', 'Surat persetujuan bertanda tangan tidak dapat dihapus.');

            return;
        }

        try {
            app(RfcAttachmentService::class)->delete($attachment, Auth::user());
        } catch (RuntimeException $exception) {
            $this->addError('attachment', $exception->getMessage());

            return;
        }

        $this->rfc->load('attachments');

        session()->flash('message', 'Lampiran berhasil dihapus.');
    }

    public function render(): View
    {
        $this->rfc->loadMissing(['submittedBy.opd', 'attachments', 'approvalLetters', 'latestApprovalLetter']);

        $isRevision = RfcStatus::fromValue($this->rfc->status) === RfcStatus::NEEDS_REVISION;
        $applications = Application::with('opd')->orderBy('name')->get();

        return view('livewire.change-requests.edit-change-request', [
            'applications' => $applications,
            'isRevision' => $isRevision,
            'revisionReason' => $isRevision ? $this->revisionReason() : null,
            'requester' => $this->rfc->submittedBy,
            'submittedAt' => $this->rfc->created_at,
        ])->title(($isRevision ? 'Revisi RFC - ' : 'Edit RFC - ').$this->rfc->displayIdentifier());
    }

    /**
     * @return array<int, ChangeRequestAttachment>
     */
    private function storeAttachments(): array
    {
        if ($this->attachments === []) {
            return [];
        }

        return app(RfcAttachmentService::class)->storeMany($this->rfc, $this->attachments, Auth::user());
    }

    private function storeApprovalLetter(): ?ChangeRequestAttachment
    {
        if ($this->approval_letter === null) {
            return null;
        }

        return app(RfcAttachmentService::class)->store(
            $this->rfc,
            $this->approval_letter,
            Auth::user(),
            ChangeRequestAttachment::TYPE_APPROVAL_LETTER,
        );
    }

    private function existingAttachmentCount(): int
    {
        return $this->rfc->supportingAttachments()->count();
    }

    /**
     * Simpan perubahan data RFC dan catat field yang benar-benar berubah.
     *
     * @return array<string, array{label: string, old: string, new: string}>
     */
    private function persistChanges(): array
    {
        $fields = array_keys($this->changeLabels());
        $before = $this->rfc->only($fields);

        $this->rfc->update([
            'title' => $this->title,
            'application_id' => $this->application_id,
            'change_type' => $this->change_type,
            'description' => $this->description,
            'reason_for_change' => $this->reason_for_change,
            'impact_description' => filled($this->impact_description) ? $this->impact_description : null,
            'target_implementation_date' => $this->target_implementation_date,
            'is_physical_letter' => $this->is_physical_letter,
            'physical_letter_number' => $this->is_physical_letter ? $this->physical_letter_number : null,
            'emergency_unconfirmed' => $this->change_type === 'emergency',
        ]);

        $after = $this->rfc->only($fields);
        $changes = $this->buildChanges($before, $after);

        if ($changes !== []) {
            activity('rfc')
                ->performedOn($this->rfc)
                ->causedBy(Auth::user())
                ->withProperties([
                    'event' => 'rfc_revised',
                    'changes' => $changes,
                ])
                ->log('RFC direvisi oleh Pemohon');
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{label: string, old: string, new: string}>
     */
    private function buildChanges(array $before, array $after): array
    {
        $applicationIds = collect([
            $before['application_id'] ?? null,
            $after['application_id'] ?? null,
        ])->filter()->unique()->values();

        $applicationNames = Application::whereIn('id', $applicationIds)
            ->pluck('name', 'id')
            ->all();

        $changes = [];

        foreach ($this->changeLabels() as $field => $label) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($field === 'is_physical_letter') {
                $old = (bool) $old;
                $new = (bool) $new;
            }

            if ($field === 'application_id') {
                if ((int) $old === (int) $new) {
                    continue;
                }
            } elseif ((string) $old === (string) $new) {
                continue;
            }

            $changes[$field] = [
                'label' => $label,
                'old' => $this->displayChangeValue($field, $old, $applicationNames),
                'new' => $this->displayChangeValue($field, $new, $applicationNames),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, string>  $applicationNames
     */
    private function displayChangeValue(string $field, mixed $value, array $applicationNames): string
    {
        return match ($field) {
            'application_id' => $applicationNames[(int) $value] ?? (filled($value) ? '#'.$value : '-'),
            'change_type' => match ((string) $value) {
                'standard' => 'Standar',
                'normal' => 'Normal',
                'emergency' => 'Emergency',
                default => filled($value) ? (string) $value : '-',
            },
            'is_physical_letter' => $value ? 'Ya' : 'Tidak',
            'target_implementation_date' => filled($value)
                ? Carbon::parse($value)->format('d/m/Y H:i')
                : '-',
            'physical_letter_number' => filled($value) ? (string) $value : '-',
            default => filled($value) ? (string) $value : '-',
        };
    }

    /**
     * @return array<string, string>
     */
    private function changeLabels(): array
    {
        return [
            'title' => 'Judul',
            'application_id' => 'Sistem / Aplikasi',
            'change_type' => 'Kategori Perubahan',
            'description' => 'Deskripsi Perubahan',
            'reason_for_change' => 'Alasan Bisnis',
            'impact_description' => 'Estimasi Dampak',
            'target_implementation_date' => 'Usulan Tanggal dan Jam',
            'is_physical_letter' => 'Surat Fisik',
            'physical_letter_number' => 'Nomor Surat',
        ];
    }

    /**
     * Alasan revisi diambil dari rejection_note, dengan fallback activity lama.
     */
    private function revisionReason(): ?string
    {
        if (filled($this->rfc->rejection_note)) {
            return trim((string) $this->rfc->rejection_note);
        }

        $activity = Activity::query()
            ->where('subject_type', $this->rfc->getMorphClass())
            ->where('subject_id', $this->rfc->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::NEEDS_REVISION->value
                && filled(data_get($activity->properties, 'note'))
            );

        return $activity ? trim((string) data_get($activity->properties, 'note')) : null;
    }

    /**
     * Jaga agar alasan revisi Admin tetap terbaca setelah rejection_note dikosongkan.
     */
    private function preserveRevisionReason(): void
    {
        $reason = trim((string) $this->rfc->rejection_note);

        if ($reason === '') {
            return;
        }

        $activities = Activity::query()
            ->where('subject_type', $this->rfc->getMorphClass())
            ->where('subject_id', $this->rfc->id)
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $revisionActivities = $activities->filter(
            fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::NEEDS_REVISION->value
        );

        $hasRecordedReason = $revisionActivities->contains(
            fn (Activity $activity): bool => trim((string) data_get($activity->properties, 'note')) === $reason
        );

        if ($hasRecordedReason) {
            return;
        }

        $previousRevision = $revisionActivities->first();

        $activity = activity('rfc')
            ->performedOn($this->rfc)
            ->withProperties([
                'event' => 'revision_reason_backfill',
                'old_status' => RfcStatus::NEEDS_REVISION->value,
                'new_status' => RfcStatus::NEEDS_REVISION->value,
                'note' => $reason,
                'source' => 'revision_note',
            ]);

        if ($previousRevision?->causer) {
            $activity->causedBy($previousRevision->causer);
        }

        $activity->log('Alasan permintaan revisi dicatat.');
    }
}
