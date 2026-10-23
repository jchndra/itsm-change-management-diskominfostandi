<?php

namespace App\Livewire\ChangeRequests;

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CancelRfc extends Component
{
    public ChangeRequest $rfc;

    public bool $showModal = false;

    public string $reason = '';

    public function mount(ChangeRequest $rfc): void
    {
        $this->rfc = $rfc;
    }

    public function openModal(): void
    {
        $this->authorize('cancel', $this->rfc);

        $this->reset('reason');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('reason');
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->rfc);

        $this->reason = trim($this->reason);

        $validated = $this->validate([
            'reason' => 'required|string|min:10|max:500',
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 10 karakter.',
            'reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            DB::transaction(function () use ($user, $validated): void {
                RfcStatusTransition::transitionTo(
                    $this->rfc,
                    RfcStatus::CANCELLED,
                    $user,
                    $validated['reason'],
                );
            });
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->rfc->refresh();
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->reset('reason');

        session()->flash('message', 'RFC berhasil dibatalkan');

        $this->redirectRoute('change-requests.show', ['rfc' => $this->rfc], navigate: true);
    }

    public function render()
    {
        return view('livewire.change-requests.cancel-rfc');
    }
}
