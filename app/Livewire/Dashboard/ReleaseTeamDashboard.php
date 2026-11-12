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

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $tab = 'active';

    public string $actionError = '';

    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        Gate::authorize('rfc.view_assigned');

        abort_unless(Auth::user()?->isReleaseTeam() === true, 403);
    }

    /**
     * Jaga nilai tab dari URL tetap pada pilihan yang dikenal.
     */
    public function mount(): void
    {
        if (! in_array($this->tab, ['active', 'history'], true)) {
            $this->tab = 'active';
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $overview = app(DashboardMetricsService::class)->releaseTeamOverview($user);
        $tasks = $overview['tasks'];

        $keyword = trim($this->search);
        $matchingTasks = $keyword === ''
            ? $tasks
            : array_values(array_filter($tasks, function (array $task) use ($keyword): bool {
                foreach (['rfc_number', 'title', 'application_name', 'requester_unit'] as $field) {
                    $value = (string) ($task[$field] ?? '');

                    if ($value !== '' && mb_stripos($value, $keyword) !== false) {
                        return true;
                    }
                }

                return false;
            }));

        $visibleTasks = match ($this->filter) {
            'late' => array_values(array_filter($matchingTasks, fn (array $task): bool => $task['state'] === 'late')),
            'doing' => array_values(array_filter($matchingTasks, fn (array $task): bool => $task['state'] === 'doing')),
            'pending' => array_values(array_filter($matchingTasks, fn (array $task): bool => $task['state'] === 'pending')),
            default => $matchingTasks,
        };

        return view('livewire.dashboard.release-team-dashboard', [
            'user' => $user,
            'teamName' => $user->releaseTeamMember?->name ?? $user->name,
            'dateTime' => now()->timezone(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y'),
            'overview' => $overview,
            'visibleTasks' => $visibleTasks,
            'searchKeyword' => $keyword,
            'history' => $this->tab === 'history'
                ? app(DashboardMetricsService::class)->releaseTeamHistory($user)
                : [],
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
