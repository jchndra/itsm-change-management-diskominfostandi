<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\RfcReminderLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class RfcReminderService
{
    public const KIND_PENDING_REVIEW = 'pending_review';

    public const KIND_APPROVED_UNASSIGNED = 'approved_unassigned';

    public const KIND_TASK_NOT_STARTED = 'task_not_started';

    public const KIND_TASK_OVERDUE = 'task_overdue';

    public const KIND_WAITING_EVALUATION = 'waiting_evaluation';

    private const TIMEZONE = 'Asia/Jakarta';

    /** @var Collection<int, User>|null */
    private ?Collection $managers = null;

    /**
     * Jalankan seluruh aturan pengingat. Ringkasan berisi jumlah notifikasi
     * per jenis; mode dry-run menghitung tanpa mengirim atau menulis log.
     *
     * @return array<string, int>
     */
    public function run(bool $dryRun = false): array
    {
        $summary = [
            self::KIND_PENDING_REVIEW => 0,
            self::KIND_APPROVED_UNASSIGNED => 0,
            self::KIND_TASK_NOT_STARTED => 0,
            self::KIND_TASK_OVERDUE => 0,
            self::KIND_WAITING_EVALUATION => 0,
        ];

        if (! (bool) config('rfc.reminders.enabled', true)) {
            return $summary;
        }

        $this->managers = null;
        $now = now();
        $today = $now->copy()->timezone(self::TIMEZONE);

        $this->remindPendingReview($now, $today, $dryRun, $summary);
        $this->remindApprovedUnassigned($now, $today, $dryRun, $summary);
        $this->remindTasksNotStarted($today, $dryRun, $summary);
        $this->remindTasksOverdue($now, $today, $dryRun, $summary);
        $this->remindWaitingEvaluation($now, $today, $dryRun, $summary);

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function remindPendingReview(Carbon $now, Carbon $today, bool $dryRun, array &$summary): void
    {
        $threshold = (int) config('rfc.reminders.pending_review_days');

        ChangeRequest::query()
            ->where('status', RfcStatus::TICKET_CREATED->value)
            ->with($this->rfcRelations())
            ->chunkById(100, function (Collection $rfcs) use ($now, $today, $dryRun, $threshold, &$summary): void {
                /** @var Collection<int, Activity> $latest */
                $latest = $this->latestTransitionTimes($rfcs->pluck('id'), [RfcStatus::TICKET_CREATED->value]);

                foreach ($rfcs as $rfc) {
                    $since = $this->resolveSince(
                        $latest->get($rfc->id)?->created_at,
                        $rfc->ticket_created_at,
                        $rfc->updated_at,
                    );

                    if ($since === null) {
                        continue;
                    }

                    $days = $this->daysSince($since, $today);

                    if ($days <= $threshold) {
                        continue;
                    }

                    $this->dispatch(
                        self::KIND_PENDING_REVIEW,
                        $rfc,
                        $this->managers(),
                        $since,
                        $days,
                        $now,
                        $dryRun,
                        $summary,
                    );
                }
            });
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function remindApprovedUnassigned(Carbon $now, Carbon $today, bool $dryRun, array &$summary): void
    {
        $threshold = (int) config('rfc.reminders.approved_unassigned_days');

        ChangeRequest::query()
            ->where('status', RfcStatus::APPROVED->value)
            ->whereDoesntHave('releaseTasks')
            ->with($this->rfcRelations())
            ->chunkById(100, function (Collection $rfcs) use ($now, $today, $dryRun, $threshold, &$summary): void {
                /** @var Collection<int, Activity> $latest */
                $latest = $this->latestTransitionTimes($rfcs->pluck('id'), [RfcStatus::APPROVED->value]);

                foreach ($rfcs as $rfc) {
                    $since = $this->resolveSince($latest->get($rfc->id)?->created_at, $rfc->updated_at);

                    if ($since === null) {
                        continue;
                    }

                    $days = $this->daysSince($since, $today);

                    if ($days <= $threshold) {
                        continue;
                    }

                    $this->dispatch(
                        self::KIND_APPROVED_UNASSIGNED,
                        $rfc,
                        $this->managers(),
                        $since,
                        $days,
                        $now,
                        $dryRun,
                        $summary,
                    );
                }
            });
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function remindTasksNotStarted(Carbon $today, bool $dryRun, array &$summary): void
    {
        $threshold = (int) config('rfc.reminders.task_not_started_days');

        ReleaseTask::query()
            ->where('execution_status', 'pending')
            ->whereHas('changeRequest')
            ->with([
                'changeRequest:id,rfc_number,title,status,submitted_by_id,created_at,updated_at',
                'changeRequest.submittedBy:id,name,is_active',
                'releaseTeamMember.user:id,name,is_active',
            ])
            ->chunkById(100, function (Collection $tasks) use ($today, $dryRun, $threshold, &$summary): void {
                foreach ($tasks as $task) {
                    $rfc = $task->changeRequest;

                    if ($rfc === null || $task->created_at === null) {
                        continue;
                    }

                    $since = $task->created_at;
                    $days = $this->daysSince($since, $today);

                    if ($days <= $threshold) {
                        continue;
                    }

                    $recipients = collect([$task->releaseTeamMember?->user])
                        ->merge($this->managers());

                    $this->dispatch(
                        self::KIND_TASK_NOT_STARTED,
                        $rfc,
                        $recipients,
                        $since,
                        $days,
                        $today,
                        $dryRun,
                        $summary,
                    );
                }
            });
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function remindTasksOverdue(Carbon $now, Carbon $today, bool $dryRun, array &$summary): void
    {
        ReleaseTask::query()
            ->where('execution_status', 'in_progress')
            ->whereNotNull('scheduled_end')
            ->where('scheduled_end', '<', $now)
            ->whereHas('changeRequest')
            ->with([
                'changeRequest:id,rfc_number,title,status,submitted_by_id,created_at,updated_at',
                'changeRequest.submittedBy:id,name,is_active',
                'releaseTeamMember.user:id,name,is_active',
            ])
            ->chunkById(100, function (Collection $tasks) use ($now, $today, $dryRun, &$summary): void {
                foreach ($tasks as $task) {
                    $rfc = $task->changeRequest;

                    if ($rfc === null || $task->scheduled_end === null) {
                        continue;
                    }

                    $since = $task->scheduled_end;
                    $days = max(1, $this->daysSince($since, $today));

                    $recipients = collect([$task->releaseTeamMember?->user, $rfc->submittedBy])
                        ->merge($this->managers());

                    $this->dispatch(
                        self::KIND_TASK_OVERDUE,
                        $rfc,
                        $recipients,
                        $since,
                        $days,
                        $now,
                        $dryRun,
                        $summary,
                    );
                }
            });
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function remindWaitingEvaluation(Carbon $now, Carbon $today, bool $dryRun, array &$summary): void
    {
        $threshold = (int) config('rfc.reminders.waiting_evaluation_days');
        $statuses = [RfcStatus::IMPLEMENTED->value, RfcStatus::FAILED->value];

        ChangeRequest::query()
            ->whereIn('status', $statuses)
            ->with($this->rfcRelations())
            ->chunkById(100, function (Collection $rfcs) use ($now, $today, $dryRun, $threshold, $statuses, &$summary): void {
                /** @var Collection<int, Activity> $latest */
                $latest = $this->latestTransitionTimes($rfcs->pluck('id'), $statuses);

                foreach ($rfcs as $rfc) {
                    $since = $this->resolveSince($latest->get($rfc->id)?->created_at, $rfc->updated_at);

                    if ($since === null) {
                        continue;
                    }

                    $days = $this->daysSince($since, $today);

                    if ($days <= $threshold) {
                        continue;
                    }

                    $this->dispatch(
                        self::KIND_WAITING_EVALUATION,
                        $rfc,
                        $this->managers(),
                        $since,
                        $days,
                        $now,
                        $dryRun,
                        $summary,
                    );
                }
            });
    }

    /**
     * @param  Collection<int, User|null>  $recipients
     * @param  array<string, int>  $summary
     */
    private function dispatch(
        string $kind,
        ChangeRequest $rfc,
        Collection $recipients,
        Carbon $since,
        int $days,
        Carbon $now,
        bool $dryRun,
        array &$summary,
    ): void {
        $recipients = $recipients
            ->filter(fn (?User $user): bool => $user !== null && $user->is_active)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $cooldownStart = $now->copy()->subDays((int) config('rfc.reminders.cooldown_days'));

        $log = RfcReminderLog::query()
            ->where('change_request_id', $rfc->id)
            ->where('kind', $kind)
            ->where('since_at', $since)
            ->first();

        if ($log !== null && $log->sent_at !== null && $log->sent_at->greaterThan($cooldownStart)) {
            return;
        }

        if ($dryRun) {
            $summary[$kind] += $recipients->count();

            return;
        }

        foreach ($recipients as $recipient) {
            app(RfcNotifier::class)->reminder($rfc, $recipient, $kind, $days);
        }

        RfcReminderLog::query()->updateOrCreate(
            [
                'change_request_id' => $rfc->id,
                'kind' => $kind,
                'since_at' => $since,
            ],
            ['sent_at' => now()],
        );

        $summary[$kind] += $recipients->count();
    }

    /**
     * @return array<int, string>
     */
    private function rfcRelations(): array
    {
        return [
            'submittedBy:id,name,is_active',
            'releaseTask.releaseTeamMember.user:id,name,is_active',
        ];
    }

    private function managers(): Collection
    {
        return $this->managers ??= User::role('Change Manager')
            ->where('is_active', true)
            ->get();
    }

    private function resolveSince(mixed ...$candidates): ?Carbon
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            return Carbon::parse($candidate);
        }

        return null;
    }

    /**
     * Selisih hari kalender pada zona waktu Jakarta.
     */
    private function daysSince(Carbon $since, Carbon $today): int
    {
        return (int) $since->copy()
            ->timezone(self::TIMEZONE)
            ->startOfDay()
            ->diffInDays($today->copy()->timezone(self::TIMEZONE)->startOfDay());
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
}
