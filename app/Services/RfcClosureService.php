<?php

namespace App\Services;

use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Exceptions\RfcClosureException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Support\Facades\DB;

class RfcClosureService
{
    public const MIN_NOTES_LENGTH = 10;

    public const MAX_NOTES_LENGTH = 1000;

    /**
     * Tutup RFC setelah evaluasi implementasi tercatat.
     *
     * Penjagaan status dan prasyarat evaluasi berada di service ini karena
     * Super Admin dapat melewati policy melalui Gate::before.
     */
    public function close(ChangeRequest $rfc, User $actor, string $notes): ChangeRequest
    {
        $notes = trim($notes);

        if (mb_strlen($notes) < self::MIN_NOTES_LENGTH) {
            throw new RfcClosureException(
                'Catatan penutupan minimal '.self::MIN_NOTES_LENGTH.' karakter.'
            );
        }

        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw new RfcClosureException(
                'Catatan penutupan maksimal '.self::MAX_NOTES_LENGTH.' karakter.'
            );
        }

        return DB::transaction(function () use ($rfc, $actor, $notes): ChangeRequest {
            // Baca ulang status dari database di bawah row lock. Pengecekan ini
            // berlaku untuk semua pengguna, termasuk Super Admin.
            $lockedRfc = ChangeRequest::query()
                ->whereKey($rfc->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRfc) {
                throw new RfcClosureException('RFC tidak ditemukan atau sudah dihapus.');
            }

            $status = RfcStatus::fromValue($lockedRfc->status);

            if (! in_array($status, [RfcStatus::IMPLEMENTED, RfcStatus::FAILED], true)) {
                throw new RfcClosureException(
                    'Penutupan hanya dapat dilakukan untuk RFC yang sudah diimplementasikan atau gagal dilaksanakan.'
                );
            }

            $implementation = $lockedRfc->implementation()->first();

            if (! $implementation) {
                throw new RfcClosureException('RFC ini belum memiliki catatan implementasi.');
            }

            if ($implementation->evaluated_at === null) {
                throw new RfcClosureException('Evaluasi implementasi wajib dicatat sebelum RFC ditutup.');
            }

            $outcome = $implementation->implementation_result === ImplementationResult::SUCCESS->value
                ? 'successful'
                : 'unsuccessful';

            RfcStatusTransition::transitionTo(
                $lockedRfc,
                RfcStatus::CLOSED,
                $actor,
                null,
                [
                    'event' => 'closure_recorded',
                    'outcome' => $outcome,
                    'implementation_result' => $implementation->implementation_result,
                    'closure_notes' => $notes,
                    'evaluation_notes' => $implementation->evaluation_notes,
                    'evaluated_at' => $implementation->evaluated_at?->toDateTimeString(),
                ],
            );

            $lockedRfc->update([
                'closed_at' => now(),
                'closed_by_id' => $actor->id,
                'closure_notes' => $notes,
            ]);

            return $lockedRfc->fresh();
        });
    }
}
