<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function resetPassword(User $user, User $targetUser): bool
    {
        return $this->canManageTarget($user, $targetUser);
    }

    public function toggleActive(User $user, User $targetUser): bool
    {
        return $this->canManageTarget($user, $targetUser);
    }

    private function canManageTarget(User $user, User $targetUser): bool
    {
        if (! $user->can('users.manage') || $user->id === $targetUser->id) {
            return false;
        }

        return ! $targetUser->hasRole('Super Admin') || $user->hasRole('Super Admin');
    }

    /**
     * Pemisahan Tugas: Admin tidak boleh mengubah peran (role) dirinya sendiri
     */
    public function updateRole(User $user, User $targetUser): bool
    {
        if (! $user->can('users.manage')) {
            return false;
        }

        // Mencegah self-role update
        return $user->id !== $targetUser->id;
    }
}
