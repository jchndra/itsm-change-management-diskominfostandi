<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Exceptions\ReleaseTaskProgressException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTaskUpdate;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReleaseTaskProgressService
{
    public const MIN_DAYS = 1;

    public const MAX_DAYS = 90;

    public const MAX_PLANNED_START_DAYS = 60;

    public const MIN_REASON_LENGTH = 10;

    public const MAX_REASON_LENGTH = 500;

    public const MIN_NOTE_LENGTH = 5;

    public const MAX_NOTE_LENGTH = 1000;

    public function setEstimate(ReleaseTask $task, User $actor, int $days, ?Carbon $plannedStart = null): ReleaseTaskUpdate
    {
        $this->validateDays($days);
        $this->validatePlannedStart($plannedStart);

        $plannedStartOn = $plannedStart?->copy()->timezone('Asia/Jakarta')->toDateString()
            ?? now('Asia/Jakarta')->toDateString();

        $update = DB::transaction(function () use ($task, $actor, $days, $plannedStartOn): ReleaseTaskUpdate {
            [$lockedTask, $rfc] = $this->lockTask($task, $actor);

            if ($lockedTask->estimated_days !== null) {
                throw new ReleaseTaskProgressException('Estimasi sudah ditetapkan dan hanya dapat direvisi.');
            }

            $lockedTask->update([
                'estimated_days' => $days,
                'planned_start_on' => $plannedStartOn,
            ]);

            $update = $lockedTask->updates()->create([
                'change_request_id' => $rfc->id,
                'user_id' => $actor->id,
                'type' => 'estimate_set',
                'old_estimated_days' => null,
                'new_estimated_days' => $days,
            ]);

            $this->logActivity($rfc, $actor, $lockedTask, 'release_progress_estimate_set', 'Estimasi pengerjaan ditetapkan', [
                'old_estimated_days' => null,
                'new_estimated_days' => $days,
                'planned_start_on' => $plannedStartOn,
            ]);

            return $update;
        });

        $this->notifyReleaseProgress($update);

        return $update;
    }

    /**
     * Cari tugas RFC lain pada aplikasi yang sama dengan jendela jadwal beririsan.
     *
     * Bentrok tidak memblokir aksi; hasilnya dipakai sebagai peringatan.
     *
     * @return Collection<int, ReleaseTask>
     */
    public function conflictsFor(ReleaseTask $task, Carbon $start, Carbon $end): Collection
    {
        $rfc = $task->changeRequest;

        if ($rfc === null || $rfc->application_id === null) {
            return new Collection;
        }

        return ReleaseTask::query()
            ->with('changeRequest')
            ->whereKeyNot($task->getKey())
            ->where('change_request_id', '!=', $task->change_request_id)
            ->whereIn('execution_status', ['pending', 'in_progress'])
            ->whereHas('changeRequest', function ($query) use ($rfc): void {
                $query->where('application_id', $rfc->application_id)
                    ->whereNull('deleted_at');
            })
            ->get()
            ->filter(function (ReleaseTask $other) use ($start, $end): bool {
                $window = $other->scheduleWindow();

                if ($window === null) {
                    return false;
                }

                [$otherStart, $otherEnd] = $window;

                return $otherStart->lt($end) && $otherEnd->gt($start);
            })
            ->values();
    }

    public function reviseEstimate(ReleaseTask $task, User $actor, int $newDays, string $reason): ReleaseTaskUpdate
    {
        $this->validateDays($newDays);

        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new ReleaseTaskProgressException(
                'Alasan revisi minimal '.self::MIN_REASON_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new ReleaseTaskProgressException(
                'Alasan revisi maksimal '.self::MAX_REASON_LENGTH.' karakter.'
            );
        }

        $update = DB::transaction(function () use ($task, $actor, $newDays, $reason): ReleaseTaskUpdate {
            [$lockedTask, $rfc] = $this->lockTask($task, $actor);

            if ($lockedTask->estimated_days === null) {
                throw new ReleaseTaskProgressException('Tetapkan estimasi terlebih dahulu sebelum melakukan revisi.');
            }

            if ($lockedTask->estimated_days === $newDays) {
                throw new ReleaseTaskProgressException('Estimasi baru harus berbeda dari estimasi saat ini.');
            }

            $oldDays = $lockedTask->estimated_days;

            $attributes = ['estimated_days' => $newDays];

            if ($lockedTask->scheduled_start !== null) {
                $attributes['scheduled_end'] = $lockedTask->scheduled_start->copy()->addDays($newDays);
            }

            $lockedTask->update($attributes);

            $update = $lockedTask->updates()->create([
                'change_request_id' => $rfc->id,
                'user_id' => $actor->id,
                'type' => 'estimate_revised',
                'reason' => $reason,
                'old_estimated_days' => $oldDays,
                'new_estimated_days' => $newDays,
            ]);

            $this->logActivity($rfc, $actor, $lockedTask, 'release_progress_estimate_revised', 'Estimasi pengerjaan direvisi', [
                'old_estimated_days' => $oldDays,
                'new_estimated_days' => $newDays,
                'reason' => $reason,
            ]);

            return $update;
        });

        $this->notifyReleaseProgress($update);

        return $update;
    }

    public function start(ReleaseTask $task, User $actor): ReleaseTaskUpdate
    {
        $update = DB::transaction(function () use ($task, $actor): ReleaseTaskUpdate {
            [$lockedTask, $rfc] = $this->lockTask($task, $actor);

            if ($lockedTask->estimated_days === null) {
                throw new ReleaseTaskProgressException('Tetapkan estimasi pengerjaan sebelum memulai.');
            }

            if ($lockedTask->execution_status !== 'pending') {
                throw new ReleaseTaskProgressException('Pengerjaan hanya dapat dimulai dari status menunggu.');
            }

            $status = RfcStatus::fromValue($rfc->status);

            if (! in_array($status, [RfcStatus::APPROVED, RfcStatus::IN_PROGRESS], true)) {
                throw new ReleaseTaskProgressException('RFC belum siap untuk memulai pengerjaan.');
            }

            $startedAt = now();

            $lockedTask->update([
                'execution_status' => 'in_progress',
                'scheduled_start' => $startedAt,
                'scheduled_end' => $startedAt->copy()->addDays($lockedTask->estimated_days),
            ]);

            if ($status === RfcStatus::APPROVED) {
                RfcStatusTransition::transitionTo($rfc, RfcStatus::IN_PROGRESS, $actor);
            }

            $update = $lockedTask->updates()->create([
                'change_request_id' => $rfc->id,
                'user_id' => $actor->id,
                'type' => 'started',
            ]);

            $this->logActivity($rfc, $actor, $lockedTask, 'release_progress_started', 'Pengerjaan tugas dimulai', [
                'estimated_days' => $lockedTask->estimated_days,
                'scheduled_start' => $startedAt->toDateTimeString(),
                'scheduled_end' => $lockedTask->scheduled_end?->toDateTimeString(),
            ]);

            return $update;
        });

        $this->notifyReleaseProgress($update);

        return $update;
    }

    public function addNote(ReleaseTask $task, User $actor, string $note, bool $blocked = false): ReleaseTaskUpdate
    {
        $note = trim($note);

        if (mb_strlen($note) < self::MIN_NOTE_LENGTH) {
            throw new ReleaseTaskProgressException(
                'Catatan kegiatan minimal '.self::MIN_NOTE_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new ReleaseTaskProgressException(
                'Catatan kegiatan maksimal '.self::MAX_NOTE_LENGTH.' karakter.'
            );
        }

        $update = DB::transaction(function () use ($task, $actor, $note, $blocked): ReleaseTaskUpdate {
            [$lockedTask, $rfc] = $this->lockTask($task, $actor);

            if ($lockedTask->execution_status !== 'in_progress') {
                throw new ReleaseTaskProgressException('Catatan kegiatan hanya dapat ditambahkan saat pengerjaan berlangsung.');
            }

            $type = $blocked ? 'blocked' : 'progress';

            $update = $lockedTask->updates()->create([
                'change_request_id' => $rfc->id,
                'user_id' => $actor->id,
                'type' => $type,
                'note' => $note,
            ]);

            $this->logActivity(
                $rfc,
                $actor,
                $lockedTask,
                $blocked ? 'release_progress_blocked' : 'release_progress_note',
                $blocked ? 'Kendala pengerjaan dicatat' : 'Catatan kegiatan ditambahkan',
                ['note' => $note, 'type' => $type],
            );

            return $update;
        });

        $this->notifyReleaseProgress($update);

        return $update;
    }

    private function notifyReleaseProgress(ReleaseTaskUpdate $update): void
    {
        if (! in_array($update->type, ['started', 'estimate_set', 'estimate_revised', 'blocked'], true)) {
            return;
        }

        $rfc = $update->changeRequest;

        if ($rfc === null) {
            return;
        }

        app(RfcNotifier::class)->releaseProgress($rfc, $update);
    }

    /**
     * @return array{0: ReleaseTask, 1: ChangeRequest}
     */
    private function lockTask(ReleaseTask $task, User $actor): array
    {
        $lockedTask = ReleaseTask::query()
            ->whereKey($task->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedTask) {
            throw new ReleaseTaskProgressException('Tugas Tim Rilis tidak ditemukan.');
        }

        $rfc = ChangeRequest::withTrashed()
            ->whereKey($lockedTask->change_request_id)
            ->lockForUpdate()
            ->first();

        if (! $rfc || $rfc->trashed()) {
            throw new ReleaseTaskProgressException('RFC tidak ditemukan atau sudah dihapus.');
        }

        $memberId = $actor->releaseTeamMember?->id;

        if ($memberId === null || (int) $memberId !== (int) $lockedTask->release_team_member_id) {
            throw new ReleaseTaskProgressException('Anda bukan anggota Tim Rilis yang ditugaskan pada RFC ini.');
        }

        if (! in_array($lockedTask->execution_status, ['pending', 'in_progress'], true)) {
            throw new ReleaseTaskProgressException('Tugas ini sudah selesai dan tidak dapat diperbarui.');
        }

        return [$lockedTask, $rfc];
    }

    private function validateDays(int $days): void
    {
        if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            throw new ReleaseTaskProgressException(
                'Estimasi harus antara '.self::MIN_DAYS.' dan '.self::MAX_DAYS.' hari.'
            );
        }
    }

    private function validatePlannedStart(?Carbon $plannedStart): void
    {
        if ($plannedStart === null) {
            return;
        }

        $today = now('Asia/Jakarta')->startOfDay();
        $planned = $plannedStart->copy()->timezone('Asia/Jakarta')->startOfDay();

        if ($planned->lt($today)) {
            throw new ReleaseTaskProgressException('Rencana mulai tidak boleh di masa lalu.');
        }

        if ($planned->gt($today->copy()->addDays(self::MAX_PLANNED_START_DAYS))) {
            throw new ReleaseTaskProgressException(
                'Rencana mulai maksimal '.self::MAX_PLANNED_START_DAYS.' hari ke depan.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(
        ChangeRequest $rfc,
        User $actor,
        ReleaseTask $task,
        string $event,
        string $message,
        array $properties = [],
    ): void {
        activity()
            ->performedOn($rfc)
            ->causedBy($actor)
            ->withProperties(array_merge([
                'event' => $event,
                'release_task_id' => $task->id,
                'release_team_member_id' => $task->release_team_member_id,
            ], $properties))
            ->log($message);
    }
}
