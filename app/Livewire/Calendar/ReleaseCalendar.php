<?php

namespace App\Livewire\Calendar;

use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReleaseCalendar extends Component
{
    #[Url(as: 'bulan')]
    public string $month = '';

    #[Url(as: 'tim')]
    public string $team = '';

    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        $user = $this->currentUser();

        abort_unless($this->canAccess($user), 403);

        if (! $this->isChangeManager($user)) {
            $this->team = '';
        }
    }

    public function mount(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normalizeMonth($this->month);
    }

    public function updatedTeam(): void
    {
        if (! $this->isChangeManager($this->currentUser())) {
            $this->team = '';
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonthNoOverflow()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        $user = $this->currentUser();
        $isChangeManager = $this->isChangeManager($user);

        $items = $this->calendarItems($user, $isChangeManager);
        $monthStart = $this->monthStart();

        return view('livewire.calendar.release-calendar', [
            'monthStart' => $monthStart,
            'monthLabel' => $monthStart->copy()->locale('id')->translatedFormat('F Y'),
            'weeks' => $this->buildWeeks($items, $monthStart),
            'agenda' => $this->buildAgenda($items, $monthStart),
            'hasTasks' => $items->isNotEmpty(),
            'isChangeManager' => $isChangeManager,
            'teamOptions' => $isChangeManager ? $this->teamOptions() : collect(),
            'dateTime' => now()->locale('id')->translatedFormat('l, d F Y'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function canAccess(User $user): bool
    {
        return $user->isReleaseTeam() || $this->isChangeManager($user);
    }

    private function isChangeManager(User $user): bool
    {
        return $user->hasRole('Change Manager');
    }

    private function normalizeMonth(string $month): string
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }

        return now()->format('Y-m');
    }

    private function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->normalizeMonth($this->month))->startOfMonth();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function calendarItems(User $user, bool $isChangeManager): Collection
    {
        $member = $user->releaseTeamMember;

        if (! $isChangeManager && ($member === null || ! $member->is_active)) {
            return collect();
        }

        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->addMonthNoOverflow();

        $tasks = ReleaseTask::query()
            ->with('changeRequest')
            ->whereIn('execution_status', ['pending', 'in_progress', 'successful', 'rolled_back'])
            ->whereHas('changeRequest')
            ->when(! $isChangeManager, function (Builder $query) use ($user): void {
                $query->whereHas('releaseTeamMember', function (Builder $member) use ($user): void {
                    $member->where('user_id', $user->id)
                        ->where('is_active', true);
                });
            })
            ->when($isChangeManager && $this->team !== '', fn ($query) => $query->where('release_team_member_id', (int) $this->team))
            ->get();

        $items = $tasks
            ->map(function (ReleaseTask $task): ?array {
                $window = $this->windowFor($task);

                if ($window === null) {
                    return null;
                }

                [$start, $end] = $window;
                $displayStart = $start->copy()->startOfDay();
                $displayEnd = $end->copy()->subDay()->startOfDay();

                if ($displayEnd->lt($displayStart)) {
                    $displayEnd = $displayStart->copy();
                }

                return [
                    'task' => $task,
                    'start' => $start,
                    'end' => $end,
                    'display_start' => $displayStart,
                    'display_end' => $displayEnd,
                    'status' => $this->statusMeta($task),
                    'conflict' => false,
                    'conflict_labels' => [],
                ];
            })
            ->filter()
            ->filter(fn (array $item): bool => $item['start']->lt($monthEnd) && $item['end']->gt($monthStart))
            ->values();

        return $this->markConflicts($items);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function windowFor(ReleaseTask $task): ?array
    {
        $window = $task->scheduleWindow();

        if ($window !== null) {
            return $window;
        }

        if (in_array($task->execution_status, ['successful', 'rolled_back'], true)) {
            if ($task->scheduled_start !== null && $task->scheduled_end !== null) {
                return [$task->scheduled_start->copy(), $task->scheduled_end->copy()];
            }

            if ($task->executed_at !== null) {
                return [$task->executed_at->copy(), $task->executed_at->copy()->addDay()];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function markConflicts(Collection $items): Collection
    {
        foreach ($items as $index => $first) {
            foreach ($items as $otherIndex => $second) {
                if ($index >= $otherIndex) {
                    continue;
                }

                $firstApplication = $first['task']->changeRequest?->application_id;
                $secondApplication = $second['task']->changeRequest?->application_id;

                if ($firstApplication === null
                    || $firstApplication !== $secondApplication
                    || $first['task']->change_request_id === $second['task']->change_request_id) {
                    continue;
                }

                if (! $first['start']->lt($second['end']) || ! $first['end']->gt($second['start'])) {
                    continue;
                }

                $firstLabel = $second['task']->changeRequest?->displayIdentifier() ?? 'RFC lain';
                $secondLabel = $first['task']->changeRequest?->displayIdentifier() ?? 'RFC lain';

                $first['conflict'] = true;
                $first['conflict_labels'][] = $firstLabel;
                $second['conflict'] = true;
                $second['conflict_labels'][] = $secondLabel;

                $items[$index] = $first;
                $items[$otherIndex] = $second;
            }
        }

        return $items->values();
    }

    /**
     * @return array{key: string, label: string, bar: string, dot: string}
     */
    private function statusMeta(ReleaseTask $task): array
    {
        if ($task->execution_status === 'rolled_back') {
            return [
                'key' => 'rollback',
                'label' => 'Rollback',
                'bar' => 'bg-rose-500 text-white hover:bg-rose-600',
                'dot' => 'bg-rose-500',
            ];
        }

        if ($task->execution_status === 'successful') {
            return [
                'key' => 'done',
                'label' => 'Selesai',
                'bar' => 'bg-emerald-500 text-white hover:bg-emerald-600',
                'dot' => 'bg-emerald-500',
            ];
        }

        if ($task->execution_status === 'in_progress') {
            if ($task->scheduled_end !== null && $task->scheduled_end->isPast()) {
                return [
                    'key' => 'late',
                    'label' => 'Terlambat',
                    'bar' => 'bg-rose-500 text-white hover:bg-rose-600',
                    'dot' => 'bg-rose-500',
                ];
            }

            return [
                'key' => 'doing',
                'label' => 'Dikerjakan',
                'bar' => 'bg-blue-500 text-white hover:bg-blue-600',
                'dot' => 'bg-blue-500',
            ];
        }

        return [
            'key' => 'pending',
            'label' => 'Belum dimulai',
            'bar' => 'bg-slate-200 text-slate-700 hover:bg-slate-300',
            'dot' => 'bg-slate-400',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildWeeks(Collection $items, Carbon $monthStart): array
    {
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);

            $days = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $day = $weekStart->copy()->addDays($offset);
                $days[] = [
                    'date' => $day,
                    'in_month' => $day->month === $monthStart->month && $day->year === $monthStart->year,
                    'is_today' => $day->isToday(),
                ];
            }

            $segments = [];

            foreach ($items as $item) {
                if ($item['display_start']->gt($weekEnd) || $item['display_end']->lt($weekStart)) {
                    continue;
                }

                $segmentStart = $item['display_start']->lt($weekStart)
                    ? $weekStart->copy()
                    : $item['display_start']->copy();
                $segmentEnd = $item['display_end']->gt($weekEnd)
                    ? $weekEnd->copy()
                    : $item['display_end']->copy();

                $colStart = (int) $weekStart->copy()->startOfDay()->diffInDays($segmentStart->copy()->startOfDay());
                $span = (int) $segmentStart->copy()->startOfDay()->diffInDays($segmentEnd->copy()->startOfDay()) + 1;

                $segments[] = [
                    'item' => $item,
                    'col_start' => $colStart,
                    'span' => max(1, min($span, 7 - $colStart)),
                    'lane' => null,
                ];
            }

            usort($segments, fn (array $first, array $second): int => [$first['col_start'], -$first['span']] <=> [$second['col_start'], -$second['span']]);

            $laneOccupancy = [];
            $visible = [];
            $hiddenSegments = [];

            foreach ($segments as $segment) {
                $placed = false;

                for ($lane = 0; $lane < 3; $lane++) {
                    $isFree = true;

                    foreach ($laneOccupancy[$lane] ?? [] as [$occupiedStart, $occupiedEnd]) {
                        if ($segment['col_start'] <= $occupiedEnd && ($segment['col_start'] + $segment['span'] - 1) >= $occupiedStart) {
                            $isFree = false;
                            break;
                        }
                    }

                    if ($isFree) {
                        $segment['lane'] = $lane;
                        $laneOccupancy[$lane][] = [$segment['col_start'], $segment['col_start'] + $segment['span'] - 1];
                        $visible[] = $segment;
                        $placed = true;
                        break;
                    }
                }

                if (! $placed) {
                    $hiddenSegments[] = $segment;
                }
            }

            $weeks[] = [
                'start' => $weekStart,
                'days' => $days,
                'segments' => $visible,
                'hidden' => count($hiddenSegments),
                'hidden_segments' => $hiddenSegments,
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array{date: Carbon, tasks: Collection<int, array<string, mixed>>}>
     */
    private function buildAgenda(Collection $items, Carbon $monthStart): array
    {
        $agenda = [];
        $day = $monthStart->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        while ($day->lte($monthEnd)) {
            $tasks = $items
                ->filter(fn (array $item): bool => $day->betweenIncluded($item['display_start'], $item['display_end']))
                ->values();

            if ($tasks->isNotEmpty()) {
                $agenda[] = [
                    'date' => $day->copy(),
                    'tasks' => $tasks,
                ];
            }

            $day->addDay();
        }

        return $agenda;
    }

    /**
     * @return Collection<int, ReleaseTeamMember>
     */
    private function teamOptions(): Collection
    {
        return ReleaseTeamMember::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }
}
