<?php

namespace App\Policies;

use App\Models\ReleaseTeamMember;
use App\Models\User;

class ReleaseTeamMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('release_team.manage');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('release_team.manage');
    }

    public function link(User $user, ReleaseTeamMember $member): bool
    {
        return $user->hasPermissionTo('release_team.manage')
            && $member->is_active
            && $member->user_id === null;
    }
}
