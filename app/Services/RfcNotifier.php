<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeManagerReviewNotification;
use App\Notifications\RfcDispositionNotification;
use App\Notifications\RfcStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RfcNotifier
{
    /**
     * Kirim notifikasi perubahan status tanpa mengganggu transaksi bisnis.
     *
     * Di production pengiriman ditunda sampai transaksi terluar commit. Jika
     * pengiriman gagal, exception dilaporkan dan activity log tetap tersimpan.
     */
    public function statusChanged(ChangeRequest $rfc, RfcStatus $newStatus, ?string $note, User $actor): void
    {
        if (! $newStatus->shouldNotifyPemohon()) {
            return;
        }

        $recipient = $rfc->submittedBy;
        $notifyOwnerForSubmission = $newStatus === RfcStatus::TICKET_CREATED;

        if (! $recipient || ($recipient->is($actor) && ! $notifyOwnerForSubmission)) {
            return;
        }

        $send = function () use ($rfc, $recipient, $newStatus, $note): void {
            try {
                $recipient->notify(new RfcStatusChangedNotification($rfc, $newStatus, $note));
            } catch (Throwable $exception) {
                report($exception);

                Log::warning('Notifikasi perubahan status RFC gagal dikirim.', [
                    'rfc_id' => $rfc->id,
                    'recipient_id' => $recipient->id,
                    'status' => $newStatus->value,
                ]);
            }
        };

        if (config('rfc.notify_after_commit', true) && DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    /**
     * Beri tahu seluruh Change Manager bahwa RFC menunggu pemeriksaan.
     * Notifikasi sengaja tidak membawa status agar ikon informasi digunakan.
     */
    public function submittedForReview(ChangeRequest $rfc): void
    {
        $recipients = User::role('Change Manager')->get();

        foreach ($recipients as $recipient) {
            $send = function () use ($rfc, $recipient): void {
                try {
                    $recipient->notify(new ChangeManagerReviewNotification($rfc));
                } catch (Throwable $exception) {
                    report($exception);

                    Log::warning('Notifikasi pemeriksaan Change Manager gagal dikirim.', [
                        'rfc_id' => $rfc->id,
                        'recipient_id' => $recipient->id,
                    ]);
                }
            };

            if (config('rfc.notify_after_commit', true) && DB::transactionLevel() > 0) {
                DB::afterCommit($send);
            } else {
                $send();
            }
        }
    }

    /**
     * Beri tahu Pemohon bahwa RFC-nya sudah diteruskan ke Tim Rilis.
     * Kegagalan kanal notifikasi tidak boleh membatalkan disposisi.
     */
    public function dispositionAssigned(ChangeRequest $rfc): void
    {
        $recipient = $rfc->submittedBy;

        if (! $recipient) {
            return;
        }

        $send = function () use ($rfc, $recipient): void {
            try {
                $recipient->notify(new RfcDispositionNotification($rfc));
            } catch (Throwable $exception) {
                report($exception);

                Log::warning('Notifikasi disposisi RFC gagal dikirim.', [
                    'rfc_id' => $rfc->id,
                    'recipient_id' => $recipient->id,
                ]);
            }
        };

        if (config('rfc.notify_after_commit', true) && DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }
}
