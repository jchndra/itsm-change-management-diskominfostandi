<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
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

    public string $legal_basis = '';

    public ?string $risk_event = null;

    public string $reason_for_change = '';

    public string $change_risk = '';

    public string $impact_description = '';

    public bool $head_of_department_approved = false;

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('update', $rfc);

        $this->rfc = $rfc;
        $this->title = (string) $rfc->title;
        $this->application_id = (string) $rfc->application_id;
        $this->change_type = (string) ($rfc->change_type ?? '');
        $this->description = (string) ($rfc->description ?? '');
        $this->legal_basis = (string) ($rfc->legal_basis ?? '');
        $this->risk_event = $rfc->risk_event instanceof RiskEvent ? $rfc->risk_event->value : null;
        $this->reason_for_change = (string) $rfc->reason_for_change;
        $this->change_risk = (string) ($rfc->change_risk ?? '');
        $this->impact_description = (string) ($rfc->impact_description ?? '');
        $this->head_of_department_approved = (bool) $rfc->head_of_department_approved;
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
            });
        } catch (Throwable $exception) {
            app(RfcAttachmentService::class)->deleteStoredFiles($newAttachments);

            throw $exception;
        }

        $this->attachments = [];

        session()->flash('message', 'Perubahan RFC berhasil disimpan.');

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function submit(): void
    {
        $this->authorize('update', $this->rfc);
        $this->validateChangeRequest($this->existingAttachmentCount());
        $this->validateForSubmission($this->existingAttachmentCount());

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

                if ($currentStatus === RfcStatus::NEEDS_REVISION) {
                    // Pastikan alasan revisi sudah tercatat sebelum rejection_note dikosongkan.
                    $this->preserveRevisionReason();
                }

                RfcStatusTransition::transitionTo($this->rfc, RfcStatus::TICKET_CREATED, $user);

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

        session()->flash(
            'message',
            $oldStatus === RfcStatus::NEEDS_REVISION
                ? "Revisi {$this->rfc->displayIdentifier()} berhasil dikirim dan diteruskan ke Change Manager."
                : "{$this->rfc->displayIdentifier()} berhasil diajukan dan diteruskan ke Change Manager."
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
     * Semua unggahan baru disimpan sebagai lampiran pendukung.
     *
     * @return array<int, ChangeRequestAttachment>
     */
    private function storeAttachments(): array
    {
        if ($this->attachments === []) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();

        return app(RfcAttachmentService::class)->storeMany($this->rfc, $this->attachments, $user);
    }

    private function existingAttachmentCount(): int
    {
        // Hitung seluruh lampiran (termasuk surat persetujuan) agar batas 5 file
        // dan syarat minimal 1 lampiran saat mengajukan mencakup semua jenis dokumen.
        return $this->rfc->attachments()->count();
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
            'legal_basis' => $this->legal_basis,
            'risk_event' => filled($this->risk_event) ? $this->risk_event : null,
            'reason_for_change' => $this->reason_for_change,
            'change_risk' => $this->change_risk,
            'head_of_department_approved' => $this->head_of_department_approved,
            'head_of_department_approved_at' => $this->headOfDepartmentApprovedAt(),
            'impact_description' => filled($this->impact_description) ? $this->impact_description : null,
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
     * Waktu persetujuan Pimpinan (Eselon II) hanya dimutakhirkan saat status perubahannya.
     * Persetujuan lama dipertahankan agar tidak ter-reset tiap kali Pemohon menyimpan revisi.
     */
    private function headOfDepartmentApprovedAt(): ?Carbon
    {
        if (! $this->head_of_department_approved) {
            return null;
        }

        if ($this->rfc->head_of_department_approved) {
            return $this->rfc->head_of_department_approved_at;
        }

        return now();
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

            if ($field === 'risk_event') {
                $old = $old instanceof RiskEvent ? $old->value : $old;
                $new = $new instanceof RiskEvent ? $new->value : $new;
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
            'risk_event' => RiskEvent::tryFrom((string) $value)?->optionLabel() ?? '-',
            'change_type' => match ((string) $value) {
                'standard' => 'Standar',
                'normal' => 'Normal',
                'emergency' => 'Emergency',
                default => filled($value) ? (string) $value : '-',
            },
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
            'application_id' => 'Sistem / Layanan',
            'change_type' => 'Kategori Perubahan',
            'description' => 'Detail Perubahan',
            'legal_basis' => 'Dasar Hukum',
            'risk_event' => 'Register Risiko',
            'reason_for_change' => 'Alasan Perubahan',
            'change_risk' => 'Risiko Perubahan',
            'impact_description' => 'Keterangan',
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
