<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RfcDeletionService
{
    /**
     * Hapus RFC secara soft delete dan catat jejak auditnya.
     */
    public static function delete(ChangeRequest $rfc, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($rfc, $actor, $reason) {
            $rfc->refresh();

            if ($rfc->trashed()) {
                throw new RuntimeException('RFC ini sudah berada di Riwayat Penghapusan.');
            }

            if (RfcStatus::fromValue($rfc->status) !== RfcStatus::DRAFT) {
                throw new RuntimeException('Hanya RFC berstatus Draft yang dapat dihapus.');
            }

            if ($rfc->submitted_by_id !== $actor->id) {
                throw new RuntimeException('Anda tidak berhak menghapus RFC ini.');
            }

            $reason = filled($reason) ? trim((string) $reason) : null;
            $previousStatus = $rfc->status;

            $rfc->forceFill([
                'deleted_by_id' => $actor->id,
                'deletion_reason' => $reason,
            ])->save();

            $rfc->delete();

            activity('rfc')
                ->performedOn($rfc)
                ->causedBy($actor)
                ->withProperties([
                    'rfc_number' => $rfc->rfc_number,
                    'title' => $rfc->title,
                    'previous_status' => $previousStatus,
                    'deletion_reason' => $reason,
                ])
                ->log('RFC dihapus (soft delete) oleh '.$actor->name.'.');
        });
    }

    /**
     * Pulihkan RFC tanpa mengubah status terakhirnya.
     */
    public static function restore(ChangeRequest $rfc, User $actor): void
    {
        DB::transaction(function () use ($rfc, $actor) {
            $rfc->refresh();

            if (! $rfc->trashed()) {
                throw new RuntimeException('RFC ini tidak sedang berada di Riwayat Penghapusan.');
            }

            if (! $actor->hasPermissionTo('rfc.view_all')) {
                throw new RuntimeException('Anda tidak berhak memulihkan RFC ini.');
            }

            if (RfcStatus::fromValue($rfc->status) === null) {
                throw new RuntimeException('Status RFC tidak dikenali sehingga tidak dapat dipulihkan.');
            }

            $deletedAt = $rfc->deleted_at;
            $deletedBy = $rfc->deleted_by_id;
            $deletionReason = $rfc->deletion_reason;

            $rfc->restore();

            activity('rfc')
                ->performedOn($rfc)
                ->causedBy($actor)
                ->withProperties([
                    'rfc_number' => $rfc->rfc_number,
                    'title' => $rfc->title,
                    'restored_status' => $rfc->status,
                    'deleted_at' => $deletedAt?->toDateTimeString(),
                    'deleted_by_id' => $deletedBy,
                    'deletion_reason' => $deletionReason,
                ])
                ->log('RFC dipulihkan dari Riwayat Penghapusan oleh '.$actor->name.'.');
        });
    }
}
