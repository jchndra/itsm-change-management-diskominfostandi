<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RecordDecision extends Component
{
    public ChangeRequest $rfc;

    public string $decisionStatus = DecisionStatus::APPROVED->value;

    public string $notes = '';

    public bool $completenessConfirmed = false;

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('recordDecision', $rfc);
        $this->rfc = $rfc->load(['submittedBy.opd', 'application.opd', 'riskAssessment', 'attachments']);
    }

    public function saveDecision(): void
    {
        $this->authorize('recordDecision', $this->rfc);

        $this->validate([
            'decisionStatus' => ['required', Rule::in(DecisionStatus::formValues())],
            'notes' => ['required', 'string', 'max:1000'],
            'completenessConfirmed' => ['accepted_if:decisionStatus,'.DecisionStatus::APPROVED->value],
        ], [
            'notes.required' => 'Catatan keputusan wajib diisi.',
            'completenessConfirmed.accepted_if' => 'Centang pernyataan bahwa dokumen dan lampiran telah diperiksa.',
        ]);

        if (! $this->rfc->riskAssessment) {
            $this->addError('decisionStatus', 'Keputusan tidak dapat direkam sebelum analisis risiko dilakukan.');

            return;
        }

        /** @var User $user */
        $user = Auth::user();

        $decisionStatus = DecisionStatus::from($this->decisionStatus);

        $targetStatus = match ($decisionStatus) {
            DecisionStatus::APPROVED => RfcStatus::APPROVED,
            DecisionStatus::REJECTED => RfcStatus::REJECTED,
            DecisionStatus::REVISION_REQUIRED => RfcStatus::NEEDS_REVISION,
            default => null,
        };

        if ($targetStatus === null) {
            $this->addError('decisionStatus', 'Pilihan keputusan tidak valid untuk alur baru.');

            return;
        }

        try {
            DB::transaction(function () use ($decisionStatus, $targetStatus, $user) {
                Decision::create([
                    'change_request_id' => $this->rfc->id,
                    'authority_id' => null,
                    'recorded_by_id' => $user->id,
                    'snapshot_authority_name' => $user->name,
                    'snapshot_authority_nip' => filled($user->nip) ? $user->nip : null,
                    'snapshot_authority_position' => filled($user->position) ? $user->position : 'Change Manager',
                    'decision_status' => $decisionStatus->value,
                    'decision_date' => now(),
                    'reason' => $this->notes,
                ]);

                if ($decisionStatus === DecisionStatus::REVISION_REQUIRED) {
                    $this->rfc->update(['rejection_note' => $this->notes]);
                } elseif ($decisionStatus === DecisionStatus::APPROVED) {
                    $this->rfc->update(['rejection_note' => null]);
                }

                RfcStatusTransition::transitionTo($this->rfc, $targetStatus, $user, $this->notes);
            });
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        session()->flash('message', 'Keputusan Change Manager berhasil dicatat.');
        $this->redirectRoute('change-requests.index');
    }

    public function render()
    {
        return view('livewire.change-requests.record-decision', [
            'recordedBy' => Auth::user(),
            'decisionOptions' => DecisionStatus::formOptions(),
        ]);
    }
}
