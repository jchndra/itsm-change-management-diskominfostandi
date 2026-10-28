<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear(Str::transliterate(Str::lower('user@paraf.local').'|127.0.0.1'));
});

test('tamu dialihkan ke halaman login saat mengakses route terproteksi', function () {
    $this->get('/change-requests')
        ->assertRedirect('/login');
});

test('halaman login menampilkan elemen akses yang tersedia tanpa fitur yang tidak didukung', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Selamat datang')
        ->assertSee('Ingat saya')
        ->assertSee('Daftar sebagai Pemohon')
        ->assertSee(route('register'), false)
        ->assertDontSee('Lupa kata sandi');
});

test('pengguna dapat berhasil login dengan kredensial valid', function () {
    $user = User::factory()->create([
        'email' => 'user@paraf.local',
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'user@paraf.local')
        ->set('password', 'secret123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/requester/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('admin diarahkan ke dashboard admin setelah login', function () {
    $this->seed(PermissionSeeder::class);

    $admin = User::factory()->create([
        'email' => 'admin@paraf.local',
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);
    $admin->assignRole('Admin');

    Livewire::test(Login::class)
        ->set('email', 'admin@paraf.local')
        ->set('password', 'secret123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect('/admin/dashboard');

    $this->assertAuthenticatedAs($admin);
});

test('pengguna diarahkan ke dashboard sesuai izin setelah login', function (array $roles, array $permissions, string $expectedPath) {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect($expectedPath);

    $this->assertAuthenticatedAs($user);
})->with([
    'pemohon' => [['Pemohon'], [], '/requester/dashboard'],
    'change manager' => [['Change Manager'], [], '/change-manager/dashboard'],
    'super admin' => [['Super Admin'], [], '/change-manager/dashboard'],
    'akun dua peran' => [['Admin', 'Change Manager'], [], '/change-manager/dashboard'],
    'staf tanpa izin dashboard' => [[], ['rfc.view_all'], '/change-requests'],
]);

test('gagal login dengan kata sandi salah', function () {
    User::factory()->create([
        'email' => 'user@paraf.local',
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'user@paraf.local')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('penolakan login untuk akun tidak aktif', function () {
    User::factory()->create([
        'email' => 'user@paraf.local',
        'password' => bcrypt('secret123'),
        'is_active' => false,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'user@paraf.local')
        ->set('password', 'secret123')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('rate limiting aktif setelah percobaan login berulang', function () {
    User::factory()->create([
        'email' => 'user@paraf.local',
        'password' => bcrypt('secret123'),
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'user@paraf.local')
        ->set('password', 'wrong-password');

    for ($i = 0; $i < 5; $i++) {
        $component->call('login');
    }

    $component->call('login')
        ->assertHasErrors(['email']);
});

test('pengguna terotentikasi dapat melakukan logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
