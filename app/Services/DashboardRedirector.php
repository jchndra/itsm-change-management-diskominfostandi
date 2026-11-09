<?php

namespace App\Services;

use App\Models\User;

class DashboardRedirector
{
    /**
     * Tentukan nama rute tujuan setelah pengguna berhasil terautentikasi.
     */
    public function routeNameFor(User $user): string
    {
        if ($user->can('viewChangeManagerDashboard')) {
            return 'change-manager.dashboard';
        }

        if ($user->can('viewAdminDashboard')) {
            return 'admin.dashboard';
        }

        if ($user->isStaff()) {
            return 'change-requests.index';
        }

        if ($user->isReleaseTeam() && $user->can('rfc.view_assigned')) {
            return 'release-team.dashboard';
        }

        return 'requester.dashboard';
    }
}
