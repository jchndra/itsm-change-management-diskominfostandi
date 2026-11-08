<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Exceptions\RfcSatisfactionException;
use App\Models\ChangeRequest;
use App\Models\RfcSatisfaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RfcSatisfactionService
{
    public const MAX_COMMENT_LENGTH = 1000;

    /**
     * Simpan penilaian kepuasan Pemohon untuk RFC yang sudah ditutup.
     *
     * Seluruh penjagaan diulang di dalam transaksi dan row lock agar aturan
     * tetap berlaku walaupun pemanggil melewati policy route.
     */
    public function submit(ChangeRequest $rfc, User $actor, int $rating, ?string $comment): RfcSatisfaction
    {
        if ($rating < 1 || $rating > 5) {
            throw new RfcSatisfactionException('Penilaian harus antara 1 sampai 5 bintang.');
        }

        $comment = $comment !== null ? trim($comment) : null;

        if ($comment === '') {
            $comment = null;
        }

        if ($comment !== null && mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new RfcSatisfactionException(
                'Komentar penilaian maksimal '.self::MAX_COMMENT_LENGTH.' karakter.'
            );
        }

        try {
            $satisfaction = DB::transaction(function () use ($rfc, $actor, $rating, $comment): RfcSatisfaction {
                $lockedRfc = ChangeRequest::query()
                    ->whereKey($rfc->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedRfc) {
                    throw new RfcSatisfactionException('RFC tidak ditemukan atau sudah dihapus.');
                }

                if (RfcStatus::fromValue($lockedRfc->status) !== RfcStatus::CLOSED) {
                    throw new RfcSatisfactionException(
                        'Penilaian hanya dapat dikirim untuk RFC yang sudah ditutup.'
                    );
                }

                if ($lockedRfc->submitted_by_id !== $actor->id) {
                    throw new RfcSatisfactionException(
                        'Hanya Pemohon pemilik RFC yang dapat memberikan penilaian.'
                    );
                }

                if (RfcSatisfaction::query()->where('change_request_id', $lockedRfc->getKey())->exists()) {
                    throw new RfcSatisfactionException('RFC ini sudah dinilai.');
                }

                $satisfaction = RfcSatisfaction::query()->create([
                    'change_request_id' => $lockedRfc->getKey(),
                    'user_id' => $actor->id,
                    'rating' => $rating,
                    'comment' => $comment,
                ]);

                activity()
                    ->performedOn($lockedRfc)
                    ->causedBy($actor)
                    ->withProperties([
                        'event' => 'satisfaction_submitted',
                        'rating' => $rating,
                    ])
                    ->log('Penilaian kepuasan Pemohon dicatat.');

                return $satisfaction;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new RfcSatisfactionException('RFC ini sudah dinilai.');
            }

            throw $exception;
        }

        app(RfcNotifier::class)->satisfactionSubmitted($rfc, $satisfaction);

        return $satisfaction;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23000'
            || $sqlState === '23505'
            || ($exception->errorInfo[1] ?? null) === 1062
            || str_contains(mb_strtolower($exception->getMessage()), 'unique');
    }
}
