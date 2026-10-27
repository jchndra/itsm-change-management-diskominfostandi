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
        $relations = [
            'submittedBy.opd',
            'application.opd',
            'attachments',
            'riskAssessment',
            'decisions.authority',
            'implementation.evaluatedBy',
            'evidences',
            'closedBy',
        ];

        // Disposisi adalah catatan internal staf. Untuk Pemohon relasinya tidak
        // dimuat agar nama penerima dan instruksi tidak ikut ke tampilan.
        if (auth()->user()?->can('rfc.view_all')) {
            $relations[] = 'releaseTasks.releaseTeamMember';
            $relations[] = 'releaseTasks.assignedBy';
        }

        $this->rfc = $rfc->load($relations);
    }

    /**
     * Dipanggil oleh wire:poll agar riwayat proses mengikuti aksi role lain.
     */
    public function refreshProcessHistory(): void
    {
        $this->authorize('view', $this->rfc);

        $this->rfc->refresh();
    }

    public function render()
    {
        return view('livewire.change-requests.show-change-request')->title('Detail RFC - '.$this->rfc->displayIdentifier());
    }
}
