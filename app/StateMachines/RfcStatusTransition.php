<?php

namespace App\StateMachines;

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcNotifier;
use App\Services\RfcNumberGenerator;
use Illuminate\Support\Facades\DB;

class RfcStatusTransition
{
    public static function transitionTo(
        ChangeRequest $rfc,
        RfcStatus $newStatus,
        User $actor,
        ?string $note = null,
        array $properties = []
    ): void {
        $currentStatus = RfcStatus::fromValue($rfc->status);

        if (! $currentStatus) {
            throw new InvalidRfcStatusTransitionException(
                'Status RFC saat ini tidak dikenali sehingga tidak dapat diubah.'
            );
        }

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidRfcStatusTransitionException(
                "Status RFC tidak dapat diubah dari {$currentStatus->label()} ke {$newStatus->label()}."
            );
        }

        $requiredActor = $currentStatus->actorFor($newStatus);

        if (! self::actorCan($actor, $requiredActor, $rfc)) {
            throw new InvalidRfcStatusTransitionException(
                "Anda tidak memiliki izin untuk mengubah status RFC dari {$currentStatus->label()} ke {$newStatus->label()}."
            );
        }

        $oldStatus = $rfc->status;
        $attributes = ['status' => $newStatus->value];

        DB::transaction(function () use ($rfc, $newStatus, $currentStatus, $oldStatus, $actor, $note, $properties, &$attributes): void {
            // Nomor resmi hanya terbit pada transisi menuju Submitted. Generator
            // dipanggil di dalam transaksi yang sama agar lock nomor bertahan sampai
            // update selesai, termasuk saat method ini dipanggil tanpa transaksi luar.
            if ($newStatus === RfcStatus::SUBMITTED && blank($rfc->rfc_number)) {
                $attributes['rfc_number'] = RfcNumberGenerator::generate();
            }

            $rfc->update($attributes);

            if (function_exists('activity')) {
                activity()
                    ->performedOn($rfc)
                    ->causedBy($actor)
                    ->withProperties(array_merge([
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus->value,
                        'note' => $note,
                    ], $properties))
                    ->log("Status RFC diubah dari {$currentStatus->label()} menjadi {$newStatus->label()}.");
            }
        });

        // Notifikasi tidak boleh membatalkan perubahan status maupun activity
        // log. Pengiriman ditunda sampai transaksi luar selesai (di production)
        // dan kegagalannya dilaporkan tanpa melempar ulang.
        app(RfcNotifier::class)->statusChanged($rfc, $newStatus, $note, $actor);
    }

    private static function actorCan(User $actor, ?string $requiredActor, ChangeRequest $rfc): bool
    {
        if ($requiredActor === null) {
            return false;
        }

        return match ($requiredActor) {
            'owner' => $rfc->submitted_by_id === $actor->id,
            'admin' => $actor->hasRole('Admin'),
            'change_manager' => $actor->hasRole('Change Manager'),
            default => false,
        };
    }
}
