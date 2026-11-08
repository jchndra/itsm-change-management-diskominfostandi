<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\ReleaseTaskUpdate;
use App\Models\RfcSatisfaction;
use App\Models\User;
use App\Notifications\ChangeManagerReviewNotification;
use App\Notifications\RfcDispositionNotification;
use App\Notifications\RfcReleaseProgressNotification;
use App\Notifications\RfcSatisfactionNotification;
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
     * Beri tahu Pemohon dan seluruh Change Manager aktif tentang progres
     * penting Tim Rilis. Catatan progres biasa tidak dikirim.
     */
    public function releaseProgress(ChangeRequest $rfc, ReleaseTaskUpdate $update): void
    {
        if (! in_array($update->type, ['started', 'estimate_set', 'estimate_revised', 'blocked'], true)) {
            return;
        }

        $recipients = collect([$rfc->submittedBy])
            ->filter()
            ->merge(User::role('Change Manager')->where('is_active', true)->get())
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            $send = function () use ($rfc, $update, $recipient): void {
                try {
                    $recipient->notify(new RfcReleaseProgressNotification($rfc, $update));
                } catch (Throwable $exception) {
                    report($exception);

                    Log::warning('Notifikasi progres Tim Rilis gagal dikirim.', [
                        'rfc_id' => $rfc->id,
                        'recipient_id' => $recipient->id,
                        'update_id' => $update->id,
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
     * Beri tahu seluruh Change Manager aktif bahwa Pemohon telah menilai
     * layanan. Isi komentar tidak pernah dimasukkan ke notifikasi.
     */
    public function satisfactionSubmitted(ChangeRequest $rfc, RfcSatisfaction $satisfaction): void
    {
        $recipients = User::role('Change Manager')->where('is_active', true)->get();

        foreach ($recipients as $recipient) {
            $send = function () use ($rfc, $satisfaction, $recipient): void {
                try {
                    $recipient->notify(new RfcSatisfactionNotification($rfc, $satisfaction->rating));
                } catch (Throwable $exception) {
                    report($exception);

                    Log::warning('Notifikasi penilaian kepuasan RFC gagal dikirim.', [
                        'rfc_id' => $rfc->id,
                        'recipient_id' => $recipient->id,
                        'satisfaction_id' => $satisfaction->id,
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
