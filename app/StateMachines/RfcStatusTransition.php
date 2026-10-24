<?php

namespace App\StateMachines;

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\RfcStatusChangedNotification;

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

        $rfc->update([
            'status' => $newStatus->value,
        ]);

        if ($newStatus->shouldNotifyPemohon()
            && $rfc->submittedBy
            && $rfc->submittedBy->id !== $actor->id) {
            $rfc->submittedBy->notify(
                new RfcStatusChangedNotification($rfc, $newStatus, $note)
            );
        }

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
    }

    private static function actorCan(User $actor, ?string $requiredActor, ChangeRequest $rfc): bool
    {
        if ($requiredActor === null) {
            return false;
        }

        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        return match ($requiredActor) {
            'owner' => $rfc->submitted_by_id === $actor->id,
            'admin' => $actor->hasRole('Admin'),
            'change_manager' => $actor->hasRole('Change Manager'),
            default => false,
        };
    }
}
