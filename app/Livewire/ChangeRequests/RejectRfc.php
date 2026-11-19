<?php

namespace App\Livewire\ChangeRequests;

use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcRejectionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcRejectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RejectRfc extends Component
{
    public ChangeRequest $rfc;

    public string $reason = '';

    public bool $confirmed = false;

    protected array $rules = [
        'reason' => 'required|string|min:20|max:1000',
        'confirmed' => 'accepted',
    ];

    protected array $messages = [
        'reason.required' => 'Alasan penolakan wajib diisi.',
        'reason.min' => 'Alasan penolakan minimal 20 karakter.',
        'reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        'confirmed.accepted' => 'Konfirmasi penolakan wajib dicentang.',
    ];

    public function mount(ChangeRequest $rfc): void
    {
        $this->authorize('reject', $rfc);

        $this->rfc = $rfc->load([
            'application.opd',
            'submittedBy',
            'releaseTasks.releaseTeamMember',
        ]);
    }

    public function reject(): void
    {
        $this->authorize('reject', $this->rfc);

        $this->validate();

        /** @var User $user */
        $user = Auth::user();

        try {
            app(RfcRejectionService::class)->reject($this->rfc, $user, $this->reason);
        } catch (RfcRejectionException|InvalidRfcStatusTransitionException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        session()->flash('message', "RFC {$this->rfc->displayIdentifier()} ditolak. Pekerjaan terkait telah dihentikan.");
        $this->redirect(route('change-requests.show', $this->rfc->id), navigate: true);
    }

    public function render(): View
    {
        $activeTasks = $this->rfc->releaseTasks
            ->whereIn('execution_status', ['pending', 'in_progress'])
            ->values();

        return view('livewire.change-requests.reject-rfc', [
            'activeTasks' => $activeTasks,
        ]);
    }
}
