<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcClosureService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CloseRfc extends Component
{
    public ChangeRequest $rfc;

    public string $closure_notes = '';

    public bool $confirmed = false;

    protected array $rules = [
        'closure_notes' => 'required|string|min:10|max:1000',
        'confirmed' => 'accepted',
    ];

    protected array $messages = [
        'closure_notes.required' => 'Catatan penutupan tiket wajib diisi.',
        'closure_notes.min' => 'Catatan penutupan minimal 10 karakter.',
        'closure_notes.max' => 'Catatan penutupan maksimal 1000 karakter.',
        'confirmed.accepted' => 'Konfirmasi kesiapan penutupan RFC wajib dicentang.',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('close', $rfc);

        $this->rfc = $rfc->load([
            'application.opd',
            'submittedBy',
            'implementation.evaluatedBy',
            'evidences',
            'closedBy',
        ]);
    }

    public function closeTicket(): void
    {
        $this->authorize('close', $this->rfc);

        $this->validate();

        /** @var User $user */
        $user = Auth::user();

        try {
            app(RfcClosureService::class)->close($this->rfc, $user, $this->closure_notes);
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        session()->flash('message', "Tiket RFC {$this->rfc->displayIdentifier()} resmi ditutup (Closed).");
        $this->redirect(route('change-requests.show', $this->rfc->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.change-requests.close-rfc');
    }
}
