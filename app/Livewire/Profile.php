<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Profile extends Component
{
    #[Validate('nullable|string|max:20')]
    public string $phone_number = '';

    #[Validate('required|string|min:8|max:100')]
    public string $current_password = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string')]
    public string $password_confirmation = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->phone_number = (string) ($user->phone_number ?? '');
    }

    public function updateProfile(): void
    {
        $this->validateOnly('phone_number');

        /** @var User $user */
        $user = Auth::user();

        $user->update(['phone_number' => $this->phone_number ?: null]);

        $this->dispatch('profile-updated');
    }

    public function updatePassword(): void
    {
        $this->validateOnly('current_password');
        $this->validateOnly('password');

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }

        if (Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini.',
            ]);
        }

        $user->update([
            'password' => $this->password,
            'must_change_password' => false,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile.show', [
            'user' => $user->loadMissing('opd'),
            'roles' => $user->getRoleNames(),
        ])->title('Profil Saya');
    }
}
