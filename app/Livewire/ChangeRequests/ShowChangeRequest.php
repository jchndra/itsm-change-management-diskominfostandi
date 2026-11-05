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

        $user = auth()->user();
        $canViewAll = $user?->can('rfc.view_all') ?? false;
        $isAssignedReleaseTeam = ! $canViewAll
            && ($user?->isReleaseTeam() ?? false)
            && $rfc->hasReleaseTaskFor($user);

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

        // Disposisi hanya dimuat untuk staf dan penerima disposisi pada RFC ini.
        // Pemohon dan Tim Rilis lain tidak boleh menerima instruksi maupun nama
        // penerima melalui properti komponen.
        if ($canViewAll || $isAssignedReleaseTeam) {
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
