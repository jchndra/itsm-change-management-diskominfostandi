<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Exceptions\RfcCommentException;
use App\Models\ChangeRequest;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class RfcCommentService
{
    public const MIN_BODY_LENGTH = 2;

    public const MAX_BODY_LENGTH = 1000;

    public const MAX_PER_MINUTE = 10;

    /**
     * Simpan komentar diskusi pada RFC.
     *
     * Seluruh penjagaan diulang di dalam transaksi dan row lock agar aturan
     * tetap berlaku walaupun pemanggil melewati policy route.
     */
    public function add(ChangeRequest $rfc, User $actor, string $body, bool $internal = false): Comment
    {
        $body = trim($body);

        if (mb_strlen($body) < self::MIN_BODY_LENGTH) {
            throw new RfcCommentException('Komentar minimal '.self::MIN_BODY_LENGTH.' karakter.');
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new RfcCommentException('Komentar maksimal '.self::MAX_BODY_LENGTH.' karakter.');
        }

        $this->assertCanWrite($rfc, $actor, $internal);

        $rateKey = 'rfc-comment:'.$actor->id;

        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_PER_MINUTE)) {
            throw new RfcCommentException('Terlalu banyak komentar. Coba lagi dalam satu menit.');
        }

        $comment = DB::transaction(function () use ($rfc, $actor, $body, $internal): Comment {
            $lockedRfc = ChangeRequest::withTrashed()
                ->whereKey($rfc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc) {
                throw new RfcCommentException('RFC tidak ditemukan atau sudah dihapus.');
            }

            $this->assertCanWrite($lockedRfc, $actor, $internal);

            $comment = Comment::query()->create([
                'change_request_id' => $lockedRfc->getKey(),
                'user_id' => $actor->id,
                'comment' => $body,
                'is_internal' => $internal,
            ]);

            activity()
                ->performedOn($lockedRfc)
                ->causedBy($actor)
                ->withProperties([
                    'event' => 'comment_added',
                    'is_internal' => $internal,
                ])
                ->log('Komentar diskusi RFC ditambahkan.');

            return $comment;
        });

        RateLimiter::hit($rateKey);

        $comment->setRelation('user', $actor);
        $comment->setRelation('changeRequest', $rfc);

        app(RfcNotifier::class)->commentAdded($rfc, $comment);

        return $comment;
    }

    private function assertCanWrite(ChangeRequest $rfc, User $actor, bool $internal): void
    {
        if ($rfc->trashed()) {
            throw new RfcCommentException('Diskusi tidak tersedia untuk RFC yang sudah dihapus.');
        }

        $status = RfcStatus::fromValue($rfc->status);

        if ($status === null
            || in_array($status, [RfcStatus::DRAFT, RfcStatus::CLOSED, RfcStatus::REJECTED], true)) {
            throw new RfcCommentException('Diskusi sudah ditutup untuk RFC dengan status ini.');
        }

        $isOwner = $rfc->submitted_by_id === $actor->id;
        $isManager = $actor->hasRole('Change Manager');
        $isAssignedReleaseTeam = $actor->isReleaseTeam() && $rfc->hasReleaseTaskFor($actor);

        if (! $isOwner && ! $isManager && ! $isAssignedReleaseTeam) {
            throw new RfcCommentException('Anda tidak berhak menambahkan komentar pada RFC ini.');
        }

        if ($internal && ($isOwner || (! $isManager && ! $isAssignedReleaseTeam))) {
            throw new RfcCommentException(
                'Catatan internal hanya dapat dibuat oleh Change Manager atau Tim Rilis yang ditugaskan.'
            );
        }
    }
}
