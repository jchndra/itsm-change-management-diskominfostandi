<?php

namespace App\Livewire\Admin;

use App\Exceptions\UserAccountException;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showResetModal = false;

    public ?int $selectedUserId = null;

    public string $temporaryPassword = '';

    public bool $passwordGenerated = false;

    public string $statusMessage = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openResetModal(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('resetPassword', $user);

        $this->selectedUserId = $user->id;
        $this->temporaryPassword = '';
        $this->passwordGenerated = false;
        $this->resetValidation();
        $this->showResetModal = true;
    }

    public function closeResetModal(): void
    {
        $this->showResetModal = false;
        $this->selectedUserId = null;
        $this->temporaryPassword = '';
        $this->passwordGenerated = false;
        $this->resetValidation();
    }

    public function resetPassword(UserAccountService $service): void
    {
        if ($this->selectedUserId === null) {
            return;
        }

        $user = User::query()->findOrFail($this->selectedUserId);

        Gate::authorize('resetPassword', $user);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->temporaryPassword = $service->resetPassword($user, $actor);
        } catch (UserAccountException $exception) {
            $this->addError('resetPassword', $exception->getMessage());

            return;
        }

        $this->passwordGenerated = true;
        $this->statusMessage = "Kata sandi {$user->name} berhasil direset.";
    }

    public function toggleActive(int $userId, UserAccountService $service): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('toggleActive', $user);

        /** @var User $actor */
        $actor = Auth::user();
        $willBeActive = ! $user->is_active;

        try {
            $service->setActive($user, $actor, $willBeActive);
        } catch (UserAccountException $exception) {
            $this->addError('toggleActive', $exception->getMessage());

            return;
        }

        $this->statusMessage = $willBeActive
            ? "Akun {$user->name} berhasil diaktifkan."
            : "Akun {$user->name} berhasil dinonaktifkan.";
    }

    public function render(): View
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        $selectedUser = $this->selectedUserId === null
            ? null
            : User::query()->with('roles')->find($this->selectedUserId);

        return view('livewire.admin.users', [
            'users' => $users,
            'selectedUser' => $selectedUser,
        ])->title('Kelola Pengguna');
    }
}
