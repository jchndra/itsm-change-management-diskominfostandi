<?php

namespace App\Services;

use App\Exceptions\ReleaseTeamAccountException;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReleaseTeamAccountLinker
{
    /**
     * Tautkan akun yang sudah ada ke anggota Tim Rilis dan berikan role-nya
     * dalam satu transaksi. Service ini tidak membuat atau menghapus akun.
     */
    public function link(ReleaseTeamMember $member, User $targetUser, User $actor): ReleaseTeamMember
    {
        if (! $actor->hasPermissionTo('release_team.manage')) {
            throw new ReleaseTeamAccountException('Anda tidak berwenang menautkan akun Tim Rilis.');
        }

        if ($actor->is($targetUser)) {
            throw new ReleaseTeamAccountException('Anda tidak dapat menautkan akun sendiri sebagai Tim Rilis.');
        }

        try {
            return DB::transaction(function () use ($member, $targetUser, $actor): ReleaseTeamMember {
                $lockedMember = ReleaseTeamMember::query()
                    ->whereKey($member->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedMember) {
                    throw new ReleaseTeamAccountException('Anggota Tim Rilis tidak ditemukan.');
                }

                if (! $lockedMember->is_active) {
                    throw new ReleaseTeamAccountException('Anggota Tim Rilis tidak aktif dan tidak dapat ditautkan.');
                }

                if ($lockedMember->user_id !== null) {
                    throw new ReleaseTeamAccountException('Anggota Tim Rilis ini sudah tertaut ke akun lain.');
                }

                $lockedUser = User::query()
                    ->whereKey($targetUser->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedUser) {
                    throw new ReleaseTeamAccountException('Akun yang dipilih tidak ditemukan.');
                }

                if (! $lockedUser->is_active) {
                    throw new ReleaseTeamAccountException('Akun yang dipilih tidak aktif.');
                }

                if ($lockedUser->hasAnyRole(['Admin', 'Change Manager', 'Super Admin'])) {
                    throw new ReleaseTeamAccountException(
                        'Akun dengan role Admin, Change Manager, atau Super Admin tidak dapat ditautkan sebagai Tim Rilis.'
                    );
                }

                $alreadyLinked = ReleaseTeamMember::query()
                    ->where('user_id', $lockedUser->id)
                    ->whereKeyNot($lockedMember->getKey())
                    ->exists();

                if ($alreadyLinked) {
                    throw new ReleaseTeamAccountException('Akun ini sudah tertaut ke anggota Tim Rilis lain.');
                }

                $lockedMember->update(['user_id' => $lockedUser->id]);

                $lockedUser->assignRole('Tim Rilis');

                activity('release_team')
                    ->performedOn($lockedMember)
                    ->causedBy($actor)
                    ->withProperties([
                        'event' => 'release_team_account_linked',
                        'release_team_member_id' => $lockedMember->id,
                        'user_id' => $lockedUser->id,
                        'user_email' => $lockedUser->email,
                        'role' => 'Tim Rilis',
                    ])
                    ->log("Akun {$lockedUser->name} ditautkan ke anggota Tim Rilis {$lockedMember->name}.");

                return $lockedMember->fresh(['user']);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new ReleaseTeamAccountException('Akun ini sudah tertaut ke anggota Tim Rilis lain.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains(strtolower($exception->getMessage()), 'unique constraint')
            || str_contains(strtolower($exception->getMessage()), 'duplicate entry')
            || str_contains(strtolower($exception->getMessage()), 'duplicate key');
    }
}
