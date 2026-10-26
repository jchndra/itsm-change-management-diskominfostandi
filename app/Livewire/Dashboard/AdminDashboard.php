<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AdminDashboard extends Component
{
    /**
     * Periksa otorisasi pada setiap request Livewire, termasuk request lanjutan.
     */
    public function boot(): void
    {
        Gate::authorize('viewAdminDashboard');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.dashboard.admin-dashboard', [
            'user' => $user,
            'now' => now()->locale('id'),
        ])->title('Dashboard Admin');
    }
}
