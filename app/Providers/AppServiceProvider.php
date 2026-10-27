<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ChangeRequestPolicy;
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
        // Tidak ada lagi bypass global untuk Super Admin. Setiap ability harus
        // memiliki permission eksplisit, dan aturan bisnis tetap ditegakkan di
        // policy, service, serta state machine.

        Gate::define('viewAdminDashboard', function (User $user): bool {
            return $user->can('rfc.review_completeness');
        });

        Gate::define('viewChangeManagerDashboard', function (User $user): bool {
            return $user->canAny(['rfc.assess_risk', 'rfc.record_decision']);
        });

        Gate::define('viewDisposition', [ChangeRequestPolicy::class, 'viewDisposition']);

        // Dokumen RFC statis hanya untuk pengguna yang berhak mengakses RFC.
        Gate::define('downloadRfcDocument', function (User $user): bool {
            return $user->hasAnyPermission(['rfc.create', 'rfc.view_own', 'rfc.view_all']);
        });
    }
}
