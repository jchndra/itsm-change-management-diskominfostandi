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
        self::perform($rfc, $newStatus, $actor, $note, $properties);
    }

    /**
     * Transisi khusus penyesuaian data lama. Hanya mengizinkan pemindahan RFC
     * submitted/returned_to_admin menuju ticket_created tanpa akun pelaku.
     */
    public static function transitionBySystem(
        ChangeRequest $rfc,
        RfcStatus $newStatus,
        ?string $note = null,
        array $properties = [],
        ?string $logMessage = null
    ): void {
        self::perform($rfc, $newStatus, null, $note, $properties, $logMessage);
    }

    private static function perform(
        ChangeRequest $rfc,
        RfcStatus $newStatus,
        ?User $actor,
        ?string $note,
        array $properties,
        ?string $logMessage = null
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

        if ($actor === null) {
            if (! self::systemCan($currentStatus, $newStatus)) {
                throw new InvalidRfcStatusTransitionException(
                    "Transisi sistem dari {$currentStatus->label()} ke {$newStatus->label()} tidak diizinkan."
                );
            }
        } else {
            $requiredActor = $currentStatus->actorFor($newStatus);

            if (! self::actorCan($actor, $requiredActor, $rfc)) {
                throw new InvalidRfcStatusTransitionException(
                    "Anda tidak memiliki izin untuk mengubah status RFC dari {$currentStatus->label()} ke {$newStatus->label()}."
                );
            }
        }

        $oldStatus = $rfc->status;
        $attributes = ['status' => $newStatus->value];

        DB::transaction(function () use ($rfc, $newStatus, $currentStatus, $oldStatus, $actor, $note, $properties, $logMessage, &$attributes): void {
            // Nomor resmi terbit saat RFC masuk ke alur pemeriksaan. Untuk alur
            // baru ini terjadi pada transisi langsung ke ticket_created.
            if (in_array($newStatus, [RfcStatus::SUBMITTED, RfcStatus::TICKET_CREATED], true)
                && blank($rfc->rfc_number)) {
                $opdCode = $rfc->opdCode();

                if (blank($opdCode)) {
                    throw new InvalidRfcStatusTransitionException(
                        'Kode OPD pemohon belum diisi sehingga nomor RFC tidak dapat diterbitkan.'
                    );
                }

                $attributes['rfc_number'] = RfcNumberGenerator::generate($opdCode);
            }

            $rfc->update($attributes);

            if (function_exists('activity')) {
                $activity = activity()
                    ->performedOn($rfc)
                    ->withProperties(array_merge([
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus->value,
                        'note' => $note,
                    ], $properties));

                if ($actor !== null) {
                    $activity->causedBy($actor);
                }

                $activity->log($logMessage ?? self::logMessage($currentStatus, $newStatus));
            }
        });

        // Notifikasi tidak boleh membatalkan perubahan status maupun activity
        // log. Pengiriman ditunda sampai transaksi luar selesai (di production)
        // dan kegagalannya dilaporkan tanpa melempar ulang.
        if ($actor !== null) {
            $notifier = app(RfcNotifier::class);
            $notifier->statusChanged($rfc, $newStatus, $note, $actor);

            if ($newStatus === RfcStatus::TICKET_CREATED
                && in_array($currentStatus, [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true)) {
                $notifier->submittedForReview($rfc);
            }
        }
    }

    private static function logMessage(RfcStatus $currentStatus, RfcStatus $newStatus): string
    {
        if ($newStatus === RfcStatus::TICKET_CREATED
            && in_array($currentStatus, [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true)) {
            return 'RFC diajukan dan diteruskan ke Change Manager';
        }

        return "Status RFC diubah dari {$currentStatus->label()} menjadi {$newStatus->label()}.";
    }

    private static function systemCan(RfcStatus $currentStatus, RfcStatus $newStatus): bool
    {
        return $newStatus === RfcStatus::TICKET_CREATED
            && in_array($currentStatus, [RfcStatus::SUBMITTED, RfcStatus::RETURNED_TO_ADMIN], true);
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
            'release_team' => $actor->hasPermissionTo('rfc.record_execution')
                && $rfc->hasReleaseTaskFor($actor),
            default => false,
        };
    }
}
