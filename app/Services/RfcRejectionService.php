<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\RfcRejectionException;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\DB;

class RfcRejectionService
{
    public const MIN_REASON_LENGTH = 20;

    public const MAX_REASON_LENGTH = 1000;

    /**
     * Tolak RFC yang sudah didisposisikan. Keputusan ini hanya untuk Change
     * Manager yang bukan pemilik RFC dan berlaku sejak disetujui hingga RFC
     * siap ditutup.
     *
     * Tugas Tim Rilis yang belum selesai atau sedang berjalan dibatalkan,
     * bukan ditandai sebagai hasil pelaksanaan.
     */
    public function reject(ChangeRequest $rfc, User $actor, string $reason): ChangeRequest
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new RfcRejectionException(
                'Alasan penolakan minimal '.self::MIN_REASON_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new RfcRejectionException(
                'Alasan penolakan maksimal '.self::MAX_REASON_LENGTH.' karakter.'
            );
        }

        if (! $actor->can('reject', $rfc)) {
            throw new RfcRejectionException('Anda tidak memiliki izin untuk menolak RFC ini.');
        }

        return DB::transaction(function () use ($rfc, $actor, $reason): ChangeRequest {
            // Baca ulang status di bawah row lock. Pengecekan ini berlaku
            // untuk semua pengguna, termasuk Super Admin.
            $lockedRfc = ChangeRequest::query()
                ->whereKey($rfc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc) {
                throw new RfcRejectionException('RFC tidak ditemukan atau sudah dihapus.');
            }

            $currentStatus = RfcStatus::fromValue($lockedRfc->status);

            if (! in_array($currentStatus, [
                RfcStatus::APPROVED,
                RfcStatus::IN_PROGRESS,
                RfcStatus::IMPLEMENTED,
                RfcStatus::FAILED,
            ], true)) {
                throw new RfcRejectionException(
                    'RFC ini tidak dapat ditolak pada status saat ini.'
                );
            }

            if (! $actor->can('reject', $lockedRfc)) {
                throw new RfcRejectionException('Anda tidak memiliki izin untuk menolak RFC ini.');
            }

            Decision::create([
                'change_request_id' => $lockedRfc->id,
                'authority_id' => null,
                'recorded_by_id' => $actor->id,
                'snapshot_authority_name' => $actor->name,
                'snapshot_authority_nip' => filled($actor->nip) ? $actor->nip : null,
                'snapshot_authority_position' => filled($actor->position) ? $actor->position : 'Change Manager',
                'decision_status' => DecisionStatus::REJECTED->value,
                'decision_date' => now(),
                'reason' => $reason,
            ]);

            $lockedRfc->update(['rejection_note' => $reason]);

            $cancelledTasks = $lockedRfc->releaseTasks()
                ->whereIn('execution_status', ['pending', 'in_progress'])
                ->update(['execution_status' => 'cancelled']);

            RfcStatusTransition::transitionTo(
                $lockedRfc,
                RfcStatus::REJECTED,
                $actor,
                $reason,
                [
                    'event' => 'rfc_rejected_after_disposition',
                    'rejection_stage' => $currentStatus->value,
                    'cancelled_task_count' => $cancelledTasks,
                ],
            );

            return $lockedRfc->refresh();
        });
    }
}
