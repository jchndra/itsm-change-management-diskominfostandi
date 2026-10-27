<?php

namespace App\Livewire\Auth;

use App\Models\Opd;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $nip = '';

    public string $opd_id = '';

    public string $position = '';

    public string $email = '';

    public string $phone_number = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'digits:18', Rule::unique('users', 'nip')],
            'opd_id' => ['required', 'integer', Rule::exists('opds', 'id')->where('is_active', true)],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected array $messages = [
        'name.required' => 'Nama lengkap wajib diisi.',
        'nip.required' => 'NIP wajib diisi.',
        'nip.digits' => 'NIP harus terdiri dari 18 digit.',
        'nip.unique' => 'NIP sudah terdaftar.',
        'opd_id.required' => 'OPD/unit kerja wajib dipilih.',
        'opd_id.exists' => 'OPD/unit kerja yang dipilih tidak aktif atau tidak ditemukan.',
        'position.required' => 'Jabatan wajib diisi.',
        'email.required' => 'Alamat email wajib diisi.',
        'email.email' => 'Format alamat email tidak valid.',
        'email.unique' => 'Alamat email sudah terdaftar.',
        'phone_number.max' => 'Nomor telepon maksimal 20 karakter.',
        'password.required' => 'Kata sandi wajib diisi.',
        'password.min' => 'Kata sandi minimal 8 karakter.',
        'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
    ];

    public function register(): void
    {
        $this->name = trim($this->name);
        $this->nip = trim($this->nip);
        $this->position = trim($this->position);
        $this->email = Str::lower(trim($this->email));
        $this->phone_number = trim($this->phone_number);

        $this->validate();

        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan registrasi. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = DB::transaction(function (): User {
            $user = User::create([
                'opd_id' => $this->opd_id,
                'name' => $this->name,
                'nip' => $this->nip,
                'email' => $this->email,
                'password' => $this->password,
                'position' => $this->position,
                'phone_number' => $this->phone_number !== '' ? $this->phone_number : null,
                'is_active' => true,
            ]);

            $user->assignRole('Pemohon');

            return $user;
        });

        Auth::login($user);
        session()->regenerate();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Registrasi pemohon berhasil');

        $this->redirect(route('requester.dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'opds' => Opd::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ])->title('Registrasi Pemohon');
    }
}
