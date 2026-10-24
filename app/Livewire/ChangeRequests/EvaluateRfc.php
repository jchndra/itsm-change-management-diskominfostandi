<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcEvaluationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EvaluateRfc extends Component
{
    public ChangeRequest $rfc;

    public string $evaluation_notes = '';

    protected array $rules = [
        'evaluation_notes' => 'required|string|min:20|max:2000',
    ];

    protected array $messages = [
        'evaluation_notes.required' => 'Catatan evaluasi wajib diisi.',
        'evaluation_notes.min' => 'Catatan evaluasi minimal 20 karakter.',
        'evaluation_notes.max' => 'Catatan evaluasi maksimal 2000 karakter.',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('evaluate', $rfc);

        $this->rfc = $rfc->load([
            'application.opd',
            'submittedBy',
            'implementation.recordedBy',
            'evidences',
        ]);
    }

    public function saveEvaluation(): void
    {
        $this->authorize('evaluate', $this->rfc);

        $this->validate();

        /** @var User $user */
        $user = Auth::user();

        try {
            app(RfcEvaluationService::class)->record($this->rfc, $user, $this->evaluation_notes);
        } catch (InvalidRfcStatusTransitionException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        session()->flash('message', "Evaluasi RFC {$this->rfc->rfc_number} berhasil dicatat.");
        $this->redirect(route('change-requests.show', $this->rfc->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.change-requests.evaluate-rfc');
    }
}
