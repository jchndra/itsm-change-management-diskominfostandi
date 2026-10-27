<?php

namespace App\Livewire\Disposition;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'waiting';

    public function mount(): void
    {
        $this->authorize('viewDisposition');

        $this->tab = request()->query('tab') === 'assigned' ? 'assigned' : 'waiting';
    }

    public function setTab(string $tab): void
    {
        $this->authorize('viewDisposition');

        $this->tab = $tab === 'assigned' ? 'assigned' : 'waiting';
        $this->resetPage('waitingPage');
        $this->resetPage('assignedPage');
    }

    public function render(): View
    {
        // Otorisasi diulang agar request Livewire lanjutan tetap diperiksa.
        $this->authorize('viewDisposition');

        $waitingRfcs = ChangeRequest::query()
            ->where('status', RfcStatus::APPROVED->value)
            ->whereDoesntHave('releaseTasks')
            ->with([
                'submittedBy.opd',
                'decisions' => fn ($query) => $query
                    ->where('decision_status', DecisionStatus::APPROVED->value)
                    ->orderByDesc('decision_date'),
            ])
            ->oldest('change_requests.created_at')
            ->paginate(10, ['*'], 'waitingPage');

        $assignedRfcs = ChangeRequest::query()
            ->whereHas('releaseTasks')
            ->with([
                'releaseTasks' => fn ($query) => $query
                    ->with('releaseTeamMember')
                    ->orderBy('release_tasks.id'),
            ])
            ->withMax('releaseTasks as latest_disposition_at', 'created_at')
            ->orderByDesc('latest_disposition_at')
            ->paginate(10, ['*'], 'assignedPage');

        return view('livewire.disposition.index', [
            'waitingRfcs' => $waitingRfcs,
            'assignedRfcs' => $assignedRfcs,
            'waitingCount' => $waitingRfcs->total(),
            'assignedCount' => $assignedRfcs->total(),
        ])->title('Disposisi RFC');
    }
}
