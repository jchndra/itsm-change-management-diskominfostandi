<?php

namespace App\Livewire\ChangeRequests;

use App\Models\ChangeRequest;
use Livewire\Component;

class ShowChangeRequest extends Component
{
    public ChangeRequest $rfc;

    public function mount(ChangeRequest $rfc)
    {
        // Otorisasi detail RFC mengikuti ChangeRequestPolicy::view().
        $this->authorize('view', $rfc);

        // Muat relasi yang dibutuhkan untuk halaman detail
        $this->rfc = $rfc->load([
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'riskAssessment',
            'decisions.authority',
            'implementation',
            'evidences',
        ]);
    }

    public function render()
    {
        return view('livewire.change-requests.show-change-request')->title('Detail RFC - ' . $this->rfc->rfc_number);
    }
}
