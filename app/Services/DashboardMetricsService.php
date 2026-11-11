<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\RfcSatisfaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardMetricsService
{
    private const PERIOD_DAYS = 30;

    /**
     * Ringkasan data nyata untuk Dashboard Admin.
     *
     * @return array<string, mixed>
     */
    public function adminOverview(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));
        $periodStart = $this->periodStart($now);
        $periodEnd = $now->copy();

        $statusCounts = $this->statusCounts();
        $ticketsCreated = $this->ticketsCreatedMetrics($periodStart, $periodEnd);
        $rejected = $this->rejectedMetrics($periodStart, $periodEnd);

        return [
            'waiting_ticket' => (int) ($statusCounts[RfcStatus::SUBMITTED->value] ?? 0)
                + (int) ($statusCounts[RfcStatus::RETURNED_TO_ADMIN->value] ?? 0),
            'returned_to_admin' => (int) ($statusCounts[RfcStatus::RETURNED_TO_ADMIN->value] ?? 0),
            'needs_revision' => (int) ($statusCounts[RfcStatus::NEEDS_REVISION->value] ?? 0),
            'tickets_created' => $ticketsCreated,
            'rejected' => $rejected,
            'queue' => $this->incomingQueue($now),
            'trend' => $this->trend($periodStart, $periodEnd),
            'distribution' => $this->statusDistribution($statusCounts),
            'activities' => $this->recentActivities(),
            'quality' => $this->ticketNumberQuality(),
        ];
    }

    /**
     * Ringkasan data nyata untuk Dashboard Change Manager.
     *
     * @return array{
     *     pending_review: int,
     *     approved: int,
     *     in_progress: int,
     *     waiting_evaluation: int,
     *     closed: int,
     *     rejected: int,
     *     total: int,
     *     action_required: int,
     *     queue: array<int, array{
     *         id: int,
     *         rfc_number: string,
     *         title: string,
     *         requester: string,
     *         unit: string,
     *         category: string,
     *         wait_days: int,
     *         waiting_since: string,
     *         status: string,
     *         action_label: string,
     *         action_route: string
     *     }>,
     *     trend: array{
     *         labels: array<int, string>,
     *         table_labels: array<int, string>,
     *         tickets: array<int, int>,
     *         decisions: array<int, int>,
     *         start: string,
     *         end: string
     *     },
     *     distribution: array<int, array{status: string, label: string, value: int, is_terminal: bool}>,
     *     release_team_distribution: array<int, array{status: string, label: string, value: int, is_terminal: bool}>,
     *     activities: array<int, array{rfc_number: string, label: string, actor_name: string, occurred_at: Carbon}>,
     *     satisfaction: array{average: ?float, count: int}
     * }
     */
    public function changeManagerOverview(?Carbon $now = null, ?Carbon $trendStart = null, ?Carbon $trendEnd = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));
        $periodStart = $this->periodStart($now);
        $periodEnd = $now->copy();
        $statusCounts = $this->statusCounts();

        $trend = $this->changeManagerTrend($trendStart ?? $periodStart, $trendEnd ?? $periodEnd);
        $trendGranularity = $trend['granularity'];
        unset($trend['granularity']);

        $pendingReview = (int) ($statusCounts[RfcStatus::TICKET_CREATED->value] ?? 0);
        $approved = (int) ($statusCounts[RfcStatus::APPROVED->value] ?? 0);
        $inProgress = (int) ($statusCounts[RfcStatus::IN_PROGRESS->value] ?? 0);
        $waitingEvaluation = (int) ($statusCounts[RfcStatus::IMPLEMENTED->value] ?? 0)
            + (int) ($statusCounts[RfcStatus::FAILED->value] ?? 0);

        return [
            'pending_review' => $pendingReview,
            'approved' => $approved,
            'in_progress' => $inProgress,
            'waiting_evaluation' => $waitingEvaluation,
            'closed' => (int) ($statusCounts[RfcStatus::CLOSED->value] ?? 0),
            'rejected' => (int) ($statusCounts[RfcStatus::REJECTED->value] ?? 0),
            'total' => array_sum($statusCounts),
            'action_required' => $pendingReview + $approved + $inProgress + $waitingEvaluation,
            'queue' => $this->changeManagerQueue($now),
            'trend' => $trend,
            'trend_granularity' => $trendGranularity,
            'distribution' => $this->statusDistribution($statusCounts),
            'release_team_distribution' => $this->releaseTeamDistribution(),
            'activities' => $this->recentActivities([
                RfcStatus::TICKET_CREATED->value,
                RfcStatus::RETURNED_TO_ADMIN->value,
                RfcStatus::APPROVED->value,
                RfcStatus::IN_PROGRESS->value,
                RfcStatus::IMPLEMENTED->value,
                RfcStatus::FAILED->value,
                RfcStatus::CLOSED->value,
                RfcStatus::REJECTED->value,
                RfcStatus::CANCELLED->value,
            ]),
            'satisfaction' => $this->satisfactionOverview(),
        ];
    }

    /**
     * Ringkasan tugas Tim Rilis untuk pengguna yang sedang masuk.
     *
     * @return array{
     *     counts: array{pending: int, doing: int, late: int, done_this_month: int, needs_action: int},
     *     tasks: array<int, array{
     *         task_id: int,
     *         rfc_id: int,
     *         rfc_number: ?string,
     *         title: string,
     *         application_name: ?string,
     *         requester_unit: ?string,
     *         state: string,
     *         estimated_days: ?int,
     *         started_at: ?Carbon,
     *         target_at: ?Carbon,
     *         days_elapsed: int,
     *         days_overdue: int,
     *         assigned_days_ago: int,
     *         has_estimate: bool,
     *         primary_action: string
     *     }>
     * }
     */
    public function releaseTeamOverview(User $user, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));
        $today = $now->copy()->startOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonthNoOverflow();

        $tasks = ReleaseTask::query()
            ->with(['changeRequest.application', 'changeRequest.submittedBy.opd'])
            ->whereHas('releaseTeamMember', function (Builder $member) use ($user): void {
                $member->where('user_id', $user->id)
                    ->where('is_active', true);
            })
            ->whereHas('changeRequest')
            ->where(function (Builder $query) use ($monthStart, $monthEnd): void {
                $query->whereIn('execution_status', ['pending', 'in_progress'])
                    ->orWhere(function (Builder $done) use ($monthStart, $monthEnd): void {
                        $done->whereIn('execution_status', ['successful', 'rolled_back'])
                            ->where('executed_at', '>=', $monthStart)
                            ->where('executed_at', '<', $monthEnd);
                    });
            })
            ->get();

        $active = $tasks
            ->filter(fn (ReleaseTask $task): bool => in_array($task->execution_status, ['pending', 'in_progress'], true))
            ->sort(function (ReleaseTask $left, ReleaseTask $right) use ($now): int {
                $order = ['late' => 0, 'doing' => 1, 'pending' => 2];
                $leftState = $this->releaseTaskState($left, $now);
                $rightState = $this->releaseTaskState($right, $now);

                if ($order[$leftState] !== $order[$rightState]) {
                    return $order[$leftState] <=> $order[$rightState];
                }

                if ($leftState === 'pending') {
                    return $left->created_at <=> $right->created_at;
                }

                return ($left->scheduled_end?->getTimestamp() ?? PHP_INT_MAX)
                    <=> ($right->scheduled_end?->getTimestamp() ?? PHP_INT_MAX);
            })
            ->values();

        $late = $active->filter(fn (ReleaseTask $task): bool => $this->releaseTaskState($task, $now) === 'late')->count();
        $doing = $active->filter(fn (ReleaseTask $task): bool => $this->releaseTaskState($task, $now) === 'doing')->count();
        $pending = $active->filter(fn (ReleaseTask $task): bool => $this->releaseTaskState($task, $now) === 'pending')->count();
        $doneThisMonth = $tasks
            ->filter(fn (ReleaseTask $task): bool => in_array($task->execution_status, ['successful', 'rolled_back'], true)
                && $task->executed_at !== null
                && $task->executed_at->copy()->timezone(config('app.timezone'))->isSameMonth($now))
            ->count();

        $items = $active->map(function (ReleaseTask $task) use ($now, $today): array {
            $rfc = $task->changeRequest;
            $state = $this->releaseTaskState($task, $now);
            $startedAt = $task->scheduled_start?->copy()->timezone(config('app.timezone'));
            $targetAt = $task->scheduled_end?->copy()->timezone(config('app.timezone'));
            $hasEstimate = $task->estimated_days !== null;
            $daysElapsed = $startedAt === null
                ? 0
                : max(0, (int) $startedAt->copy()->startOfDay()->diffInDays($today, false));
            $daysOverdue = $state === 'late' && $targetAt !== null
                ? max(1, (int) $targetAt->copy()->startOfDay()->diffInDays($today, false))
                : 0;
            $assignedDaysAgo = max(
                0,
                (int) $task->created_at->copy()->timezone(config('app.timezone'))->startOfDay()->diffInDays($today, false),
            );

            return [
                'task_id' => (int) $task->id,
                'rfc_id' => (int) $task->change_request_id,
                'rfc_number' => $rfc?->rfc_number,
                'title' => (string) $rfc?->title,
                'application_name' => $rfc?->application?->name,
                'requester_unit' => $rfc?->submittedBy?->opd?->name,
                'state' => $state,
                'estimated_days' => $task->estimated_days,
                'started_at' => $startedAt,
                'target_at' => $targetAt,
                'days_elapsed' => $daysElapsed,
                'days_overdue' => $daysOverdue,
                'assigned_days_ago' => $assignedDaysAgo,
                'has_estimate' => $hasEstimate,
                'primary_action' => match (true) {
                    $state === 'late' => 'finish',
                    $state === 'doing' => 'open',
                    $hasEstimate => 'start',
                    default => 'set_estimate',
                },
            ];
        })->all();

        return [
            'counts' => [
                'pending' => $pending,
                'doing' => $doing,
                'late' => $late,
                'done_this_month' => $doneThisMonth,
                'needs_action' => $late + $pending,
            ],
            'tasks' => $items,
        ];
    }

    /**
     * Status tampilan tugas aktif: terlambat, sedang dikerjakan, atau belum dimulai.
     */
    private function releaseTaskState(ReleaseTask $task, Carbon $now): string
    {
        if ($task->execution_status === 'pending') {
            return 'pending';
        }

        return $task->scheduled_end !== null && $now->greaterThan($task->scheduled_end)
            ? 'late'
            : 'doing';
    }

    /**
     * Rata-rata dan jumlah penilaian kepuasan untuk RFC yang tidak dihapus.
     * Satu query dipakai agar dashboard tidak menambah beban per kartu.
     *
     * @return array{average: ?float, count: int}
     */
    private function satisfactionOverview(): array
    {
        $ratings = RfcSatisfaction::query()
            ->whereHas('changeRequest')
            ->pluck('rating');

        $count = $ratings->count();

        return [
            'average' => $count > 0 ? round((float) $ratings->avg(), 1) : null,
            'count' => $count,
        ];
    }

    /**
     * Jumlah RFC unik yang didisposisikan ke setiap anggota Tim Rilis aktif.
     *
     * @return array<int, array{status: string, label: string, value: int, is_terminal: bool}>
     */
    public function releaseTeamDistribution(): array
    {
        return ReleaseTeamMember::query()
            ->leftJoin('release_tasks', 'release_tasks.release_team_member_id', '=', 'release_team_members.id')
            ->leftJoin('change_requests', function (JoinClause $join): void {
                $join->on('change_requests.id', '=', 'release_tasks.change_request_id')
                    ->whereNull('change_requests.deleted_at');
            })
            ->where('release_team_members.is_active', true)
            ->whereNotNull('release_team_members.user_id')
            ->groupBy('release_team_members.id', 'release_team_members.name')
            ->select(['release_team_members.id', 'release_team_members.name'])
            ->selectRaw('COUNT(DISTINCT change_requests.id) as rfc_count')
            ->get()
            ->map(fn (ReleaseTeamMember $member): array => [
                'status' => 'team_'.$member->id,
                'label' => $member->name,
                'value' => (int) $member->rfc_count,
                'is_terminal' => false,
            ])
            ->sortBy([
                ['value', 'desc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Awal periode 30 hari kalender, dihitung pada zona waktu aplikasi.
     */
    public function periodStart(?Carbon $now = null): Carbon
    {
        return ($now ?? now())
            ->copy()
            ->timezone(config('app.timezone'))
            ->startOfDay()
            ->subDays(self::PERIOD_DAYS - 1);
    }

    /**
     * @param  iterable<int, \DateTimeInterface|string|null>  $timestamps
     * @return array<int, array{date: string, label: string, full_label: string, value: int}>
     */
    public function dailySeries(iterable $timestamps, Carbon $start, Carbon $end): array
    {
        $timezone = config('app.timezone');
        $start = $start->copy()->timezone($timezone)->startOfDay();
        $end = $end->copy()->timezone($timezone)->startOfDay();
        $counts = [];

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null || $timestamp === '') {
                continue;
            }

            $date = ($timestamp instanceof \DateTimeInterface
                ? Carbon::instance($timestamp)
                : Carbon::parse($timestamp))
                ->timezone($timezone)
                ->toDateString();

            $counts[$date] = ($counts[$date] ?? 0) + 1;
        }

        $series = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $localized = $day->copy()->locale('id');

            $series[] = [
                'date' => $date,
                'label' => $localized->translatedFormat('j M'),
                'full_label' => $localized->translatedFormat('d F Y'),
                'value' => (int) ($counts[$date] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Persentase perubahan; null bila periode pembanding tidak berisi data.
     */
    public function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        return ChangeRequest::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array{current: int, previous: int, change_percent: ?float}
     */
    private function ticketsCreatedMetrics(Carbon $start, Carbon $end): array
    {
        $previousStart = $start->copy()->subDays(self::PERIOD_DAYS);

        $current = ChangeRequest::query()
            ->whereNotNull('ticket_created_at')
            ->whereBetween('ticket_created_at', [$start, $end])
            ->count();

        $previous = ChangeRequest::query()
            ->whereNotNull('ticket_created_at')
            ->where('ticket_created_at', '>=', $previousStart)
            ->where('ticket_created_at', '<', $start)
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * @return array{
     *     current: int,
     *     previous: int,
     *     change_percent: ?float,
     *     breakdown: array<int, array{key: string, label: string, count: int}>
     * }
     */
    private function rejectedMetrics(Carbon $start, Carbon $end): array
    {
        $emptyBreakdown = [
            ['key' => 'admin', 'label' => 'Admin', 'count' => 0],
            ['key' => 'change_manager', 'label' => 'Change Manager', 'count' => 0],
            ['key' => 'unattributed', 'label' => 'Tidak teratribusi', 'count' => 0],
        ];

        $rejectedRfcs = ChangeRequest::query()
            ->where('status', RfcStatus::REJECTED->value)
            ->get(['id', 'updated_at']);

        if ($rejectedRfcs->isEmpty()) {
            return [
                'current' => 0,
                'previous' => 0,
                'change_percent' => null,
                'breakdown' => $emptyBreakdown,
            ];
        }

        $activities = Activity::query()
            ->where('subject_type', (new ChangeRequest)->getMorphClass())
            ->whereIn('subject_id', $rejectedRfcs->pluck('id'))
            ->where('properties->new_status', RfcStatus::REJECTED->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'subject_id', 'properties', 'created_at'])
            ->groupBy('subject_id');

        $timezone = config('app.timezone');
        $previousStart = $start->copy()->subDays(self::PERIOD_DAYS);
        $current = 0;
        $previous = 0;
        $breakdown = [
            'admin' => 0,
            'change_manager' => 0,
            'unattributed' => 0,
        ];

        foreach ($rejectedRfcs as $rfc) {
            /** @var Collection<int, Activity> $rejectionActivities */
            $rejectionActivities = $activities->get($rfc->id, collect());
            $latest = $rejectionActivities->first();
            $rejectedAt = Carbon::parse($latest?->created_at ?? $rfc->updated_at)
                ->timezone($timezone);

            if ($rejectedAt->betweenIncluded($start, $end)) {
                $current++;

                $attribution = $rejectionActivities->first(
                    fn (Activity $activity): bool => filled(data_get($activity->properties, 'old_status'))
                );
                $oldStatus = RfcStatus::fromValue(data_get($attribution?->properties, 'old_status'));

                if (in_array($oldStatus, [RfcStatus::SUBMITTED, RfcStatus::RETURNED_TO_ADMIN], true)) {
                    $breakdown['admin']++;
                } elseif ($oldStatus === RfcStatus::TICKET_CREATED) {
                    $breakdown['change_manager']++;
                } else {
                    $breakdown['unattributed']++;
                }
            } elseif ($rejectedAt->greaterThanOrEqualTo($previousStart) && $rejectedAt->lessThan($start)) {
                $previous++;
            }
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->percentChange($current, $previous),
            'breakdown' => [
                ['key' => 'admin', 'label' => 'Admin', 'count' => $breakdown['admin']],
                ['key' => 'change_manager', 'label' => 'Change Manager', 'count' => $breakdown['change_manager']],
                ['key' => 'unattributed', 'label' => 'Tidak teratribusi', 'count' => $breakdown['unattributed']],
            ],
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     table_labels: array<int, string>,
     *     created: array<int, int>,
     *     tickets: array<int, int>,
     *     start: string,
     *     end: string
     * }
     */
    private function trend(Carbon $start, Carbon $end): array
    {
        $created = ChangeRequest::query()
            ->where('status', '!=', RfcStatus::DRAFT->value)
            ->whereBetween('created_at', [$start, $end])
            ->pluck('created_at');

        $tickets = ChangeRequest::query()
            ->whereNotNull('ticket_created_at')
            ->whereBetween('ticket_created_at', [$start, $end])
            ->pluck('ticket_created_at');

        $createdSeries = $this->dailySeries($created, $start, $end);
        $ticketSeries = $this->dailySeries($tickets, $start, $end);

        return [
            'labels' => array_column($createdSeries, 'label'),
            'table_labels' => array_column($createdSeries, 'full_label'),
            'created' => array_column($createdSeries, 'value'),
            'tickets' => array_column($ticketSeries, 'value'),
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     table_labels: array<int, string>,
     *     tickets: array<int, int>,
     *     decisions: array<int, int>,
     *     start: string,
     *     end: string
     * }
     */
    private function changeManagerTrend(Carbon $start, Carbon $end): array
    {
        $timezone = config('app.timezone');
        $start = $start->copy()->timezone($timezone)->startOfDay();
        $end = $end->copy()->timezone($timezone)->endOfDay();

        $tickets = ChangeRequest::query()
            ->whereNotNull('ticket_created_at')
            ->whereBetween('ticket_created_at', [$start, $end])
            ->pluck('ticket_created_at');

        $decisions = Activity::query()
            ->join('change_requests', function (JoinClause $join): void {
                $join->on('change_requests.id', '=', 'activity_log.subject_id')
                    ->where('activity_log.subject_type', '=', (new ChangeRequest)->getMorphClass());
            })
            ->whereNull('change_requests.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('activity_log.log_name')
                    ->orWhere('activity_log.log_name', '!=', 'auth');
            })
            ->whereIn('activity_log.properties->new_status', [
                RfcStatus::APPROVED->value,
                RfcStatus::REJECTED->value,
            ])
            ->whereBetween('activity_log.created_at', [$start, $end])
            ->pluck('activity_log.created_at');

        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $isWeekly = $days > 60;

        $ticketSeries = $isWeekly
            ? $this->weeklySeries($tickets, $start, $end)
            : $this->dailySeries($tickets, $start, $end);
        $decisionSeries = $isWeekly
            ? $this->weeklySeries($decisions, $start, $end)
            : $this->dailySeries($decisions, $start, $end);

        return [
            'labels' => array_column($ticketSeries, 'label'),
            'table_labels' => array_column($ticketSeries, $isWeekly ? 'table_label' : 'full_label'),
            'tickets' => array_column($ticketSeries, 'value'),
            'decisions' => array_column($decisionSeries, 'value'),
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'granularity' => $isWeekly ? 'week' : 'day',
        ];
    }

    /**
     * Agregasi mingguan untuk rentang panjang: bucket 7 hari, bucket terakhir
     * dipotong pada tanggal akhir.
     *
     * @param  iterable<int, \DateTimeInterface|string|null>  $timestamps
     * @return array<int, array{label: string, table_label: string, value: int}>
     */
    private function weeklySeries(iterable $timestamps, Carbon $start, Carbon $end): array
    {
        $timezone = config('app.timezone');
        $start = $start->copy()->timezone($timezone)->startOfDay();
        $end = $end->copy()->timezone($timezone)->startOfDay();

        $bucketStarts = [];

        for ($bucket = $start->copy(); $bucket->lte($end); $bucket->addDays(7)) {
            $bucketStarts[] = $bucket->copy();
        }

        $counts = [];

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null || $timestamp === '') {
                continue;
            }

            $date = ($timestamp instanceof \DateTimeInterface
                ? Carbon::instance($timestamp)
                : Carbon::parse($timestamp))
                ->timezone($timezone)
                ->startOfDay();

            if ($date->lt($start) || $date->gt($end)) {
                continue;
            }

            $index = (int) floor($start->copy()->diffInDays($date) / 7);
            $counts[$index] = ($counts[$index] ?? 0) + 1;
        }

        $series = [];

        foreach ($bucketStarts as $index => $bucketStart) {
            $bucketEnd = $bucketStart->copy()->addDays(6);

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $series[] = [
                'label' => $bucketStart->copy()->locale('id')->translatedFormat('d M'),
                'table_label' => $bucketStart->copy()->locale('id')->translatedFormat('d M')
                    .' - '.$bucketEnd->copy()->locale('id')->translatedFormat('d M'),
                'value' => (int) ($counts[$index] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     rfc_number: string,
     *     title: string,
     *     requester: string,
     *     unit: string,
     *     category: string,
     *     wait_days: int,
     *     waiting_since: string,
     *     status: string,
     *     action_label: string,
     *     action_route: string
     * }>
     */
    private function changeManagerQueue(Carbon $now): array
    {
        $statuses = [
            RfcStatus::TICKET_CREATED->value,
            RfcStatus::APPROVED->value,
            RfcStatus::IN_PROGRESS->value,
            RfcStatus::IMPLEMENTED->value,
            RfcStatus::FAILED->value,
        ];

        $rfcs = ChangeRequest::query()
            ->whereIn('status', $statuses)
            ->with([
                'submittedBy:id,name,opd_id',
                'submittedBy.opd:id,name',
                'implementation:id,change_request_id,evaluated_at',
                'releaseTasks:id,change_request_id',
            ])
            ->get([
                'id',
                'rfc_number',
                'title',
                'status',
                'ticket_created_at',
                'created_at',
                'updated_at',
                'change_type',
                'submitted_by_id',
            ]);

        $latestTransitions = $this->latestTransitionTimes($rfcs->pluck('id'), $statuses);
        $timezone = config('app.timezone');

        return $rfcs
            ->map(function (ChangeRequest $rfc) use ($latestTransitions, $now, $timezone): array {
                $status = RfcStatus::fromValue($rfc->status);
                $waitingSince = $status === RfcStatus::TICKET_CREATED
                    ? ($rfc->ticket_created_at ?? $latestTransitions->get($rfc->id)?->created_at ?? $rfc->updated_at)
                    : ($latestTransitions->get($rfc->id)?->created_at ?? $rfc->updated_at);

                $waitingSince = Carbon::parse($waitingSince)->timezone($timezone);
                $needsClosing = in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)
                    && $rfc->implementation?->evaluated_at !== null;

                [$actionLabel, $actionRoute] = match (true) {
                    $status === RfcStatus::TICKET_CREATED => ['Tinjau Risiko', 'assess-risk'],
                    $status === RfcStatus::APPROVED => $rfc->releaseTasks->isEmpty()
                        ? ['Disposisi', 'show']
                        : ['Pantau Pelaksanaan', 'show'],
                    $status === RfcStatus::IN_PROGRESS => ['Pantau Pelaksanaan', 'show'],
                    $needsClosing => ['Tutup RFC', 'close'],
                    in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true) => ['Evaluasi RFC', 'evaluate'],
                    default => ['Lihat Detail', 'show'],
                };

                return [
                    'id' => $rfc->id,
                    'rfc_number' => $rfc->displayIdentifier(),
                    'title' => $rfc->title,
                    'requester' => $rfc->submittedBy?->name ?? 'Tidak tercatat',
                    'unit' => $rfc->submittedBy?->opd?->name ?? '-',
                    'category' => $this->changeTypeLabel($rfc->change_type),
                    'wait_days' => max(0, (int) $waitingSince->diffInDays($now)),
                    'waiting_since' => $waitingSince->toIso8601String(),
                    'status' => $rfc->status,
                    'action_label' => $actionLabel,
                    'action_route' => $actionRoute,
                ];
            })
            ->sortBy(fn (array $item): int => Carbon::parse($item['waiting_since'])->getTimestamp())
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  array<int, string>  $statuses
     * @return Collection<int, Activity>
     */
    private function latestTransitionTimes(Collection $ids, array $statuses): Collection
    {
        if ($ids->isEmpty() || $statuses === []) {
            return collect();
        }

        return Activity::query()
            ->where('subject_type', (new ChangeRequest)->getMorphClass())
            ->whereIn('subject_id', $ids)
            ->whereIn('properties->new_status', $statuses)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'subject_id', 'created_at'])
            ->groupBy('subject_id')
            ->map(fn (Collection $items): ?Activity => $items->first());
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     rfc_number: string,
     *     title: string,
     *     requester: string,
     *     unit: string,
     *     category: string,
     *     wait_days: int,
     *     waiting_since: string,
     *     status: string
     * }>
     */
    private function incomingQueue(Carbon $now): array
    {
        $rfcs = ChangeRequest::query()
            ->whereIn('status', [
                RfcStatus::SUBMITTED->value,
                RfcStatus::RETURNED_TO_ADMIN->value,
            ])
            ->with([
                'submittedBy:id,name,opd_id',
                'submittedBy.opd:id,name',
            ])
            ->get([
                'id',
                'rfc_number',
                'title',
                'status',
                'created_at',
                'updated_at',
                'change_type',
                'submitted_by_id',
            ]);

        $latestReturns = $this->latestTransitionTimes(
            $rfcs->where('status', RfcStatus::RETURNED_TO_ADMIN->value)->pluck('id'),
            [RfcStatus::RETURNED_TO_ADMIN->value],
        );

        $timezone = config('app.timezone');

        return $rfcs
            ->map(function (ChangeRequest $rfc) use ($latestReturns, $now, $timezone): array {
                $status = RfcStatus::fromValue($rfc->status);
                $waitingSince = $status === RfcStatus::RETURNED_TO_ADMIN
                    ? ($latestReturns->get($rfc->id)?->created_at ?? $rfc->updated_at)
                    : $rfc->created_at;

                $waitingSince = Carbon::parse($waitingSince)->timezone($timezone);

                return [
                    'id' => $rfc->id,
                    'rfc_number' => $rfc->displayIdentifier(),
                    'title' => $rfc->title,
                    'requester' => $rfc->submittedBy?->name ?? 'Tidak tercatat',
                    'unit' => $rfc->submittedBy?->opd?->name ?? '-',
                    'category' => $this->changeTypeLabel($rfc->change_type),
                    'wait_days' => max(0, (int) $waitingSince->diffInDays($now)),
                    'waiting_since' => $waitingSince->toIso8601String(),
                    'status' => $rfc->status,
                ];
            })
            ->sortBy(fn (array $item): int => Carbon::parse($item['waiting_since'])->getTimestamp())
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>|null  $counts
     * @return array<int, array{status: string, label: string, value: int, is_terminal: bool}>
     */
    private function statusDistribution(?array $counts = null): array
    {
        $counts ??= $this->statusCounts();

        return collect($counts)
            ->map(function (int $total, string $status): array {
                $statusEnum = RfcStatus::fromValue($status);

                return [
                    'status' => $status,
                    'label' => $this->distributionLabel($status),
                    'value' => $total,
                    'is_terminal' => $statusEnum?->allowedTransitions() === [],
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Label distribusi dibuat eksplisit agar dua status yang berbagi label
     * pada badge tetap dapat dibedakan di grafik.
     */
    private function distributionLabel(string $status): string
    {
        return match (RfcStatus::fromValue($status)) {
            RfcStatus::TICKET_CREATED => 'Sedang Ditinjau (Tiket)',
            RfcStatus::RETURNED_TO_ADMIN => 'Dikembalikan ke Admin',
            default => RfcStatus::labelFor($status),
        };
    }

    /**
     * @param  array<int, string>|null  $statuses
     * @return array<int, array{rfc_number: string, label: string, actor_name: string, occurred_at: Carbon}>
     */
    private function recentActivities(?array $statuses = null): array
    {
        $query = Activity::query()
            ->join('change_requests', function (JoinClause $join): void {
                $join->on('change_requests.id', '=', 'activity_log.subject_id')
                    ->where('activity_log.subject_type', '=', (new ChangeRequest)->getMorphClass());
            })
            ->whereNull('change_requests.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('activity_log.log_name')
                    ->orWhere('activity_log.log_name', '!=', 'auth');
            });

        if ($statuses !== null) {
            $query->whereIn('change_requests.status', $statuses);
        }

        return $query
            ->orderByDesc('activity_log.created_at')
            ->orderByDesc('activity_log.id')
            ->select([
                'activity_log.*',
                'change_requests.id as rfc_id',
                'change_requests.rfc_number',
                'change_requests.status as rfc_status',
            ])
            ->with('causer:id,name')
            ->limit(5)
            ->get()
            ->map(fn (Activity $activity): array => [
                'rfc_number' => ChangeRequest::formatIdentifier(
                    $activity->rfc_number,
                    $activity->rfc_id,
                    $activity->rfc_status,
                ),
                'label' => $this->activityLabel($activity),
                'actor_name' => $activity->causer?->name ?? 'Sistem',
                'occurred_at' => $activity->created_at,
            ])
            ->all();
    }

    private function activityLabel(Activity $activity): string
    {
        $event = data_get($activity->properties, 'event');
        $status = RfcStatus::fromValue(data_get($activity->properties, 'new_status'));

        return match (true) {
            $event === 'ticket_created' => 'Tiket change dibuat',
            $event === 'risk_assessment' => 'Analisis risiko dicatat',
            $event === 'emergency_classification_review' => 'Klasifikasi Emergency ditinjau',
            $event === 'evaluation_recorded' => 'Evaluasi dicatat',
            $event === 'rfc_revised' => 'RFC direvisi',
            $event === 'revision_reason_backfill' => 'Alasan revisi dicatat',
            $event === 'attachment_added' => 'Lampiran ditambahkan',
            $event === 'attachment_deleted' => 'Lampiran dihapus',
            $event === 'closure_recorded' => 'RFC ditutup',
            $status === RfcStatus::DRAFT => 'Draft dibuat',
            $status === RfcStatus::SUBMITTED => 'RFC diajukan',
            $status === RfcStatus::NEEDS_REVISION => 'Perlu revisi',
            $status === RfcStatus::TICKET_CREATED => 'Tiket change dibuat',
            $status === RfcStatus::RETURNED_TO_ADMIN => 'Dikembalikan ke Admin',
            $status === RfcStatus::APPROVED => 'Disetujui',
            $status === RfcStatus::IN_PROGRESS => 'Implementasi dimulai',
            $status === RfcStatus::IMPLEMENTED => 'Implementasi dicatat',
            $status === RfcStatus::FAILED => 'Implementasi gagal atau rollback',
            $status === RfcStatus::CLOSED => 'RFC ditutup',
            $status === RfcStatus::REJECTED => 'RFC ditolak',
            $status === RfcStatus::CANCELLED => 'RFC dibatalkan',
            default => 'Pembaruan RFC',
        };
    }

    private function changeTypeLabel(?string $changeType): string
    {
        return match ((string) $changeType) {
            'standard' => 'Standar',
            'normal' => 'Normal',
            'emergency' => 'Emergency',
            default => 'Tidak tercatat',
        };
    }

    /**
     * @return array{missing_ticket_number: int, note: ?string}
     */
    private function ticketNumberQuality(): array
    {
        $count = ChangeRequest::query()
            ->whereIn('status', [
                RfcStatus::TICKET_CREATED->value,
                RfcStatus::RETURNED_TO_ADMIN->value,
                RfcStatus::APPROVED->value,
                RfcStatus::IN_PROGRESS->value,
                RfcStatus::IMPLEMENTED->value,
                RfcStatus::FAILED->value,
                RfcStatus::CLOSED->value,
            ])
            ->where(function ($query): void {
                $query->whereNull('ticket_number')
                    ->orWhere('ticket_number', '');
            })
            ->count();

        return [
            'missing_ticket_number' => $count,
            'note' => $count > 0 ? 'Sebagian RFC lama belum memiliki nomor tiket.' : null,
        ];
    }
}
