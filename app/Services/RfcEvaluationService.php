<?php

namespace App\Services;

use App\Enums\RfcStatus;
use App\Exceptions\RfcEvaluationException;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RfcEvaluationService
{
    public const MIN_NOTES_LENGTH = 20;

    public const MAX_NOTES_LENGTH = 2000;

    /**
     * Catat evaluasi implementasi tanpa mengubah status RFC.
     *
     * Penjagaan status, keberadaan implementasi, dan evaluasi ganda berada di
     * service ini karena Super Admin dapat melewati policy melalui Gate::before.
     */
    public function record(ChangeRequest $rfc, User $actor, string $notes): ChangeRequestImplementation
    {
        $notes = trim($notes);

        if (mb_strlen($notes) < self::MIN_NOTES_LENGTH) {
            throw new RfcEvaluationException(
                'Catatan evaluasi minimal '.self::MIN_NOTES_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw new RfcEvaluationException(
                'Catatan evaluasi maksimal '.self::MAX_NOTES_LENGTH.' karakter.'
            );
        }

        return DB::transaction(function () use ($rfc, $actor, $notes): ChangeRequestImplementation {
            // Baca ulang status dari database di bawah row lock. Pengecekan ini
            // berlaku untuk semua pengguna, termasuk Super Admin.
            $lockedRfc = ChangeRequest::query()
                ->whereKey($rfc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc) {
                throw new RfcEvaluationException('RFC tidak ditemukan atau sudah dihapus.');
            }

            $status = RfcStatus::fromValue($lockedRfc->status);

            if (! in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)) {
                throw new RfcEvaluationException(
                    'Evaluasi hanya dapat dicatat untuk RFC yang sudah diimplementasikan atau gagal dilaksanakan.'
                );
            }

            $implementation = $lockedRfc->implementation()->first();

            if (! $implementation) {
                throw new RfcEvaluationException('RFC ini belum memiliki catatan implementasi.');
            }

            if ($implementation->evaluated_at !== null) {
                throw new RfcEvaluationException('Evaluasi RFC ini sudah pernah dicatat.');
            }

            $evaluatedAt = now();

            $implementation->update([
                'evaluation_notes' => $notes,
                'evaluated_by_id' => $actor->id,
                'evaluated_at' => $evaluatedAt,
            ]);

            activity()
                ->performedOn($lockedRfc)
                ->causedBy($actor)
                ->withProperties([
                    'event' => 'evaluation_recorded',
                    'status' => $lockedRfc->status,
                    'old_status' => $lockedRfc->status,
                    'new_status' => $lockedRfc->status,
                    'implementation_result' => $implementation->implementation_result,
                    'summary' => $implementation->summary,
                    'evaluation_notes' => $notes,
                    'evaluated_at' => $evaluatedAt->toDateTimeString(),
                ])
                ->log('Evaluasi RFC dicatat.');

            return $implementation->fresh();
        });
    }
}
