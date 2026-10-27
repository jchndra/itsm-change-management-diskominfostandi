<?php

namespace App\Livewire\ChangeRequests;

use App\Models\ChangeRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShowDeletedChangeRequest extends Component
{
    public ChangeRequest $rfc;

    public function mount(ChangeRequest $rfc): void
    {
        if (! $rfc->trashed()) {
            $this->redirectRoute('change-requests.show', ['rfc' => $rfc->id], navigate: true);

            return;
        }

        $this->authorize('view', $rfc);

        $this->rfc = $rfc->load([
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'riskAssessment',
            'decisions.authority',
        ]);
    }

    public function render(): View
    {
        return view('livewire.change-requests.show-deleted-change-request')
            ->title('Detail RFC Terhapus - '.$this->rfc->displayIdentifier());
    }
}
