<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Grant all permissions to Super Admin
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        Gate::define('viewAdminDashboard', function (User $user): bool {
            return $user->can('rfc.review_completeness');
        });

        Gate::define('viewChangeManagerDashboard', function (User $user): bool {
            return $user->canAny(['rfc.assess_risk', 'rfc.record_decision']);
        });
    }
}
