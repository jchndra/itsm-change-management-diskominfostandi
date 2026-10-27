<?php

use App\Livewire\Auth\Register;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    RateLimiter::clear('register|127.0.0.1');
});

function validRegistrationFields(Opd $opd, array $overrides = []): array
{
    return array_merge([
        'name' => 'Pegawai Contoh',
        'nip' => fake()->unique()->numerify('##################'),
        'opd_id' => (string) $opd->id,
        'position' => 'Staf Pengelola Aplikasi',
        'email' => fake()->unique()->safeEmail(),
        'phone_number' => '081234567890',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ], $overrides);
}

test('halaman registrasi dapat dibuka tamu dan hanya menampilkan OPD aktif', function () {
    $activeOpd = Opd::factory()->create(['name' => 'Dinas Aktif Contoh']);
    Opd::factory()->create(['name' => 'Dinas Nonaktif Contoh', 'is_active' => false]);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('<title>Registrasi Pemohon', false)
        ->assertSee('Buat akun Pemohon')
        ->assertSee($activeOpd->name)
        ->assertDontSee('Dinas Nonaktif Contoh');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('register'), false)
        ->assertSee('Daftar sebagai');
});

test('registrasi membuat akun Pemohon aktif dan langsung login', function () {
    $opd = Opd::factory()->create();
    $fields = validRegistrationFields($opd);

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('phone_number', $fields['phone_number'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('requester.dashboard'));

    $user = User::query()->where('email', $fields['email'])->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['Pemohon'])
        ->and(Hash::check('secret123', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('registrasi menolak NIP yang sudah terdaftar', function () {
    $opd = Opd::factory()->create();
    $existingUser = User::factory()->create([
        'nip' => '199001012020010001',
    ]);
    $fields = validRegistrationFields($opd, ['nip' => $existingUser->nip]);

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasErrors(['nip' => 'unique']);

    expect(User::query()->where('email', $fields['email'])->exists())->toBeFalse();
});

test('registrasi menolak email yang sudah terdaftar', function () {
    $opd = Opd::factory()->create();
    $existingUser = User::factory()->create();
    $fields = validRegistrationFields($opd, ['email' => $existingUser->email]);

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::count())->toBe(1);
});

test('registrasi menolak OPD yang tidak aktif', function () {
    $opd = Opd::factory()->create(['is_active' => false]);
    $fields = validRegistrationFields($opd);

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasErrors(['opd_id' => 'exists']);

    expect(User::query()->where('email', $fields['email'])->exists())->toBeFalse();
});

test('registrasi menolak data wajib yang kosong', function () {
    Livewire::test(Register::class)
        ->call('register')
        ->assertHasErrors([
            'name',
            'nip',
            'opd_id',
            'position',
            'email',
            'password',
        ]);

    expect(User::count())->toBe(0);
});

test('registrasi menolak konfirmasi kata sandi yang tidak cocok', function () {
    $opd = Opd::factory()->create();
    $fields = validRegistrationFields($opd, ['password_confirmation' => 'beda-sekali']);

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed']);

    expect(User::query()->where('email', $fields['email'])->exists())->toBeFalse();
});

test('registrasi dibatasi setelah terlalu banyak percobaan', function () {
    $opd = Opd::factory()->create();
    $fields = validRegistrationFields($opd);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        RateLimiter::hit('register|127.0.0.1', 60);
    }

    Livewire::test(Register::class)
        ->set('name', $fields['name'])
        ->set('nip', $fields['nip'])
        ->set('opd_id', $fields['opd_id'])
        ->set('position', $fields['position'])
        ->set('email', $fields['email'])
        ->set('password', $fields['password'])
        ->set('password_confirmation', $fields['password_confirmation'])
        ->call('register')
        ->assertHasErrors(['email']);

    expect(User::query()->where('email', $fields['email'])->exists())->toBeFalse();
});
