<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\DashboardRedirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($throttleKey);

            activity('auth')
                ->withProperties(['email' => $this->email, 'ip' => request()->ip()])
                ->log('Login gagal: Kredensial tidak valid');

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        /** @var User|null $user */
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $this->email]);

        if ($user && isset($user->is_active) && ! $user->is_active) {
            RateLimiter::hit($throttleKey);

            activity('auth')
                ->causedBy($user)
                ->withProperties(['ip' => request()->ip()])
                ->log('Login ditolak: Akun tidak aktif');

            throw ValidationException::withMessages([
                'email' => 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.',
            ]);
        }

        Auth::login($user, $this->remember);
        RateLimiter::clear($throttleKey);
        session()->regenerate();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Login berhasil');

        /** @var User $user */
        $user = Auth::user();

        $defaultRoute = app(DashboardRedirector::class)->routeNameFor($user);

        $this->redirectIntended(default: route($defaultRoute, absolute: false), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
