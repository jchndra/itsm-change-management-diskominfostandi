<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RecordDecision extends Component
{
    public ChangeRequest $rfc;

    public string $decisionStatus = DecisionStatus::APPROVED->value;

    public string $notes = '';

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('recordDecision', $rfc);
        $this->rfc = $rfc->load(['submittedBy', 'application.opd', 'riskAssessment']);
    }

    public function saveDecision(): void
    {
        $this->authorize('recordDecision', $this->rfc);

        $this->validate([
            'decisionStatus' => 'required|in:'.implode(',', DecisionStatus::values()),
            'notes' => 'required|string|max:1000',
        ]);

        if (! $this->rfc->riskAssessment) {
            $this->addError('decisionStatus', 'Keputusan tidak dapat direkam sebelum analisis risiko dilakukan.');

            return;
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $decisionStatus = DecisionStatus::from($this->decisionStatus);

        $targetStatus = match ($decisionStatus) {
            DecisionStatus::APPROVED => RfcStatus::APPROVED,
            DecisionStatus::REJECTED => RfcStatus::REJECTED,
            DecisionStatus::RETURNED_TO_ADMIN => RfcStatus::RETURNED_TO_ADMIN,
        };

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
        ]);
    }
}
