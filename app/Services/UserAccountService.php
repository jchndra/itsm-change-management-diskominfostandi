<?php

namespace App\Services;

use App\Exceptions\UserAccountException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserAccountService
{
    /**
     * Reset kata sandi pengguna dan kembalikan kata sandi sementara hanya
     * kepada pemanggil. Kata sandi tidak boleh masuk ke log atau session.
     */
    public function resetPassword(User $target, User $actor): string
    {
        return DB::transaction(function () use ($target, $actor): string {
            $this->ensureAllowed($target, $actor, 'resetPassword');

            $temporaryPassword = Str::password(12, symbols: false);

            $target->update([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ]);

            DB::table('sessions')->where('user_id', $target->id)->delete();

            activity()
                ->performedOn($target)
                ->causedBy($actor)
                ->withProperties(['event' => 'password_reset_by_admin'])
                ->log("Admin {$actor->name} mereset kata sandi pengguna {$target->name}.");

            return $temporaryPassword;
        });
    }

    public function setActive(User $target, User $actor, bool $active): void
    {
        DB::transaction(function () use ($target, $actor, $active): void {
            $this->ensureAllowed($target, $actor, 'toggleActive');

            $target->update(['is_active' => $active]);

            if (! $active) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }

            activity()
                ->performedOn($target)
                ->causedBy($actor)
                ->withProperties([
                    'event' => $active ? 'account_activated_by_admin' : 'account_deactivated_by_admin',
                ])
                ->log(
                    $active
                        ? "Admin {$actor->name} mengaktifkan akun pengguna {$target->name}."
                        : "Admin {$actor->name} menonaktifkan akun pengguna {$target->name}."
                );
        });
    }

    private function ensureAllowed(User $target, User $actor, string $ability): void
    {
        if (! $actor->can($ability, $target)) {
            throw new UserAccountException('Anda tidak memiliki izin untuk mengelola akun pengguna ini.');
        }
    }
}
