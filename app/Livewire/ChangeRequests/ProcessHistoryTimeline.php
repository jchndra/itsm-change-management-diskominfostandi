<?php

namespace App\Livewire\ChangeRequests;

use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Services\RfcHistoryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProcessHistoryTimeline extends Component
{
    public ChangeRequest $rfc;

    public function mount(ChangeRequest $rfc): void
    {
        // Otorisasi tetap mengikuti ChangeRequestPolicy::view().
        $this->authorize('view', $rfc);

        $this->rfc = $rfc;
    }

    /**
     * Dipanggil oleh wire:poll.30s.visible dan event dari panel progres.
     */
    #[On('release-progress-updated')]
    public function refreshProcessHistory(): void
    {
        $this->authorize('view', $this->rfc);

        $this->rfc->refresh();
    }

    public function render(): View
    {
        $this->authorize('view', $this->rfc);

        $user = auth()->user();
        $isStaff = $user?->can('rfc.view_all') ?? false;
        $isReleaseTeamView = ($user?->isReleaseTeam() ?? false) && ! $isStaff;

        $this->rfc->loadMissing('releaseTasks.releaseTeamMember');

        $releaseTeamDispositionTask = $isReleaseTeamView && $this->rfc->relationLoaded('releaseTasks')
            ? $this->rfc->releaseTasks->first(
                fn (ReleaseTask $task): bool => $task->releaseTeamMember?->user_id === auth()->id()
            )
            : null;

        $history = app(RfcHistoryService::class)->build(
            $this->rfc,
            $isStaff,
            $isReleaseTeamView,
            $releaseTeamDispositionTask !== null,
        );

        return view('livewire.change-requests.process-history-timeline', [
            'events' => $history['events'],
            'nextSteps' => $history['next_steps'],
        ]);
    }
}
