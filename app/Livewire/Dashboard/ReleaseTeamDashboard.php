<?php

namespace App\Livewire\Dashboard;

use App\Exceptions\ReleaseTaskProgressException;
use App\Models\ReleaseTask;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\ReleaseTaskProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ReleaseTeamDashboard extends Component
{
    #[Url(as: 'filter')]
    public string $filter = 'all';

    public string $actionError = '';

    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        Gate::authorize('rfc.view_assigned');

        abort_unless(Auth::user()?->isReleaseTeam() === true, 403);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $overview = app(DashboardMetricsService::class)->releaseTeamOverview($user);
        $tasks = $overview['tasks'];

        $visibleTasks = match ($this->filter) {
            'late' => array_values(array_filter($tasks, fn (array $task): bool => $task['state'] === 'late')),
            'doing' => array_values(array_filter($tasks, fn (array $task): bool => $task['state'] === 'doing')),
            'pending' => array_values(array_filter($tasks, fn (array $task): bool => $task['state'] === 'pending')),
            default => $tasks,
        };

        return view('livewire.dashboard.release-team-dashboard', [
            'user' => $user,
            'teamName' => $user->releaseTeamMember?->name ?? $user->name,
            'dateTime' => now()->timezone(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y'),
            'overview' => $overview,
            'visibleTasks' => $visibleTasks,
            'filterCounts' => [
                'all' => count($tasks),
                'late' => $overview['counts']['late'],
                'doing' => $overview['counts']['doing'],
                'pending' => $overview['counts']['pending'],
            ],
        ])->title('Tugas Saya');
    }

    public function startTask(int $taskId): void
    {
        $this->actionError = '';

        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user !== null && $user->isReleaseTeam() && $user->can('rfc.view_assigned'), 403);

        $task = ReleaseTask::query()
            ->whereKey($taskId)
            ->whereHas('releaseTeamMember', function ($member) use ($user): void {
                $member->where('user_id', $user->id)
                    ->where('is_active', true);
            })
            ->whereHas('changeRequest')
            ->first();

        abort_if($task === null, 403);

        try {
            app(ReleaseTaskProgressService::class)->start($task, $user);
        } catch (ReleaseTaskProgressException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }
}
