<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Livewire\ChangeRequests\Concerns\ValidatesChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
class EditChangeRequest extends Component
{
    use ValidatesChangeRequest;

    public ChangeRequest $rfc;

    public string $title = '';

    public string $application_id = '';

    public string $reason_for_change = '';

    public string $impact_description = '';

    public string $target_implementation_date = '';

    public bool $is_physical_letter = false;

    public string $physical_letter_number = '';

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('update', $rfc);

        $this->rfc = $rfc;
        $this->title = (string) $rfc->title;
        $this->application_id = (string) $rfc->application_id;
        $this->reason_for_change = (string) $rfc->reason_for_change;
        $this->impact_description = (string) $rfc->impact_description;
        $this->target_implementation_date = $rfc->target_implementation_date?->format('Y-m-d\TH:i') ?? '';
        $this->is_physical_letter = (bool) $rfc->is_physical_letter;
        $this->physical_letter_number = (string) ($rfc->physical_letter_number ?? '');
    }

    public function saveChanges(): void
    {
        $this->authorize('update', $this->rfc);
        $this->validateChangeRequest();

        DB::transaction(fn () => $this->persistChanges());

        session()->flash('message', 'Perubahan RFC berhasil disimpan.');

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function submit(): void
    {
        $this->authorize('update', $this->rfc);
        $this->validateChangeRequest();

        $oldStatus = RfcStatus::fromValue($this->rfc->status);

        if (! $oldStatus) {
            $this->addError('status', 'Status RFC saat ini tidak dikenali.');

            return;
        }

        /** @var User $user */
        $user = Auth::user();

        try {
            DB::transaction(function () use ($oldStatus, $user): void {
                $this->persistChanges();

                if ($oldStatus === RfcStatus::NEEDS_REVISION) {
                    // Pastikan alasan revisi sudah tercatat sebelum rejection_note dikosongkan.
                    $this->preserveRevisionReason();
                }

                RfcStatusTransition::transitionTo($this->rfc, RfcStatus::SUBMITTED, $user);

                if ($oldStatus === RfcStatus::NEEDS_REVISION) {
                    $this->rfc->update(['rejection_note' => null]);
                }
            });
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->rfc->refresh();
            $this->addError('status', $exception->getMessage());

            return;
        }

        session()->flash(
            'message',
            $oldStatus === RfcStatus::NEEDS_REVISION
                ? "Revisi {$this->rfc->rfc_number} berhasil dikirim."
                : "{$this->rfc->rfc_number} berhasil diajukan."
        );

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function render(): View
    {
        $isRevision = RfcStatus::fromValue($this->rfc->status) === RfcStatus::NEEDS_REVISION;
        $applications = Application::with('opd')->orderBy('name')->get();

        return view('livewire.change-requests.edit-change-request', [
            'applications' => $applications,
            'isRevision' => $isRevision,
            'revisionReason' => $isRevision ? $this->revisionReason() : null,
        ])->title(($isRevision ? 'Revisi RFC - ' : 'Edit RFC - ').$this->rfc->rfc_number);
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
            'reason_for_change' => $this->reason_for_change,
            'impact_description' => $this->impact_description,
            'target_implementation_date' => $this->target_implementation_date,
            'is_physical_letter' => $this->is_physical_letter,
            'physical_letter_number' => $this->is_physical_letter ? $this->physical_letter_number : null,
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
            'reason_for_change' => 'Alasan Perubahan',
            'impact_description' => 'Estimasi Dampak',
            'target_implementation_date' => 'Target Pelaksanaan',
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
