<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
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
     *     activities: array<int, array{rfc_number: string, label: string, actor_name: string, occurred_at: Carbon}>
     * }
     */
    public function changeManagerOverview(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));
        $periodStart = $this->periodStart($now);
        $periodEnd = $now->copy();
        $statusCounts = $this->statusCounts();

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
            'action_required' => $pendingReview + $approved + $inProgress + $waitingEvaluation,
            'queue' => $this->changeManagerQueue($now),
            'trend' => $this->changeManagerTrend($periodStart, $periodEnd),
            'distribution' => $this->statusDistribution($statusCounts),
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
        ];
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

        $ticketSeries = $this->dailySeries($tickets, $start, $end);
        $decisionSeries = $this->dailySeries($decisions, $start, $end);

        return [
            'labels' => array_column($ticketSeries, 'label'),
            'table_labels' => array_column($ticketSeries, 'full_label'),
            'tickets' => array_column($ticketSeries, 'value'),
            'decisions' => array_column($decisionSeries, 'value'),
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ];
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
