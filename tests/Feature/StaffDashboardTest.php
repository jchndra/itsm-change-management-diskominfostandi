<?php

use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\ChangeManagerDashboard;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<int, string>  $roles
 * @param  array<int, string>  $permissions
 */
function staffDashboardUser(array $roles = [], array $permissions = []): User
{
    $user = User::factory()->create();

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function staffSidebarHtml(string $html): string
{
    $start = strpos($html, '<aside');
    $end = strpos($html, '</aside>');

    return $start === false || $end === false
        ? ''
        : substr($html, $start, $end - $start);
}

it('mengalihkan tamu ke login dari kedua dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('change-manager.dashboard'))->assertRedirect(route('login'));
});

it('menolak pemohon di kedua dashboard staf', function () {
    $pemohon = staffDashboardUser(['Pemohon']);

    $this->actingAs($pemohon);

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('change-manager.dashboard'))->assertForbidden();
});

it('hanya mengizinkan admin membuka dashboard admin', function () {
    $admin = staffDashboardUser(['Admin']);

    $this->actingAs($admin);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Admin');

    $this->get(route('change-manager.dashboard'))->assertForbidden();
});

it('hanya mengizinkan change manager membuka dashboard change manager', function () {
    $manager = staffDashboardUser(['Change Manager']);

    $this->actingAs($manager);

    $this->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Change Manager');

    $this->get(route('admin.dashboard'))->assertForbidden();
});

it('mengizinkan super admin dan akun dua peran membuka kedua dashboard', function () {
    $users = [
        staffDashboardUser(['Super Admin']),
        staffDashboardUser(['Admin', 'Change Manager']),
    ];

    foreach ($users as $user) {
        $this->actingAs($user);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('change-manager.dashboard'))->assertOk();
    }
});

it('menolak staf yang tidak memiliki izin dashboard', function () {
    $staff = staffDashboardUser(permissions: ['rfc.view_all']);

    $this->actingAs($staff);

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('change-manager.dashboard'))->assertForbidden();
    $this->get(route('change-requests.index'))->assertOk();
});

it('menolak request Livewire lanjutan pada dashboard admin tanpa izin', function () {
    $admin = staffDashboardUser(['Admin']);
    $manager = staffDashboardUser(['Change Manager']);

    $this->actingAs($admin);

    $adminDashboard = Livewire::test(AdminDashboard::class)->assertOk();

    $this->actingAs($manager);

    $adminDashboard->refresh()->assertForbidden();
});

it('menolak request Livewire lanjutan pada dashboard change manager tanpa izin', function () {
    $manager = staffDashboardUser(['Change Manager']);
    $admin = staffDashboardUser(['Admin']);

    $this->actingAs($manager);

    $managerDashboard = Livewire::test(ChangeManagerDashboard::class)->assertOk();

    $this->actingAs($admin);

    $managerDashboard->refresh()->assertForbidden();
});

it('mengarahkan root sesuai izin dashboard', function () {
    $pemohon = staffDashboardUser(['Pemohon']);
    $admin = staffDashboardUser(['Admin']);
    $manager = staffDashboardUser(['Change Manager']);
    $superAdmin = staffDashboardUser(['Super Admin']);
    $dualRole = staffDashboardUser(['Admin', 'Change Manager']);
    $staff = staffDashboardUser(permissions: ['rfc.view_all']);

    $this->actingAs($pemohon)->get('/')->assertRedirect(route('requester.dashboard'));
    $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($manager)->get('/')->assertRedirect(route('change-manager.dashboard'));
    $this->actingAs($superAdmin)->get('/')->assertRedirect(route('change-manager.dashboard'));
    $this->actingAs($dualRole)->get('/')->assertRedirect(route('change-manager.dashboard'));
    $this->actingAs($staff)->get('/')->assertRedirect(route('change-requests.index'));
});

it('menampilkan menu sidebar staf sesuai izin', function (array $roles, array $permissions, array $visible, array $hidden) {
    $user = staffDashboardUser($roles, $permissions);

    $html = $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->getContent();

    $sidebar = staffSidebarHtml($html);

    foreach ($visible as $label) {
        expect($sidebar)->toContain($label);
    }

    foreach ($hidden as $label) {
        expect($sidebar)->not->toContain($label);
    }
})->with([
    'admin' => [
        ['Admin'],
        [],
        ['Dashboard Admin', 'Daftar RFC', 'Buat Tiket Change', 'Riwayat Penghapusan', 'Notifikasi', 'Profil'],
        ['Dashboard Change Manager', 'Ajukan RFC', 'RFC Saya'],
    ],
    'change manager' => [
        ['Change Manager'],
        [],
        ['Dashboard Change Manager', 'Daftar RFC', 'Riwayat Penghapusan', 'Notifikasi', 'Profil'],
        ['Dashboard Admin', 'Buat Tiket Change', 'Ajukan RFC', 'RFC Saya'],
    ],
    'dua peran' => [
        ['Admin', 'Change Manager'],
        [],
        ['Dashboard Admin', 'Dashboard Change Manager', 'Daftar RFC', 'Buat Tiket Change'],
        ['Ajukan RFC', 'RFC Saya'],
    ],
    'staf tanpa izin dashboard' => [
        [],
        ['rfc.view_all'],
        ['Daftar RFC', 'Riwayat Penghapusan', 'Notifikasi', 'Profil'],
        ['Dashboard Admin', 'Dashboard Change Manager', 'Buat Tiket Change'],
    ],
    'pemohon' => [
        ['Pemohon'],
        [],
        ['Dashboard', 'Ajukan RFC', 'RFC Saya', 'Riwayat Penghapusan', 'Notifikasi', 'Profil'],
        ['Dashboard Admin', 'Dashboard Change Manager', 'Daftar RFC', 'Buat Tiket Change'],
    ],
]);

it('tidak menampilkan menu Dashboard lama pada sidebar staf', function (array $roles, array $permissions) {
    $user = staffDashboardUser($roles, $permissions);

    $sidebar = staffSidebarHtml(
        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->getContent()
    );

    expect($sidebar)->not->toMatch('/>\s*Dashboard\s*</');
})->with([
    'admin' => [['Admin'], []],
    'change manager' => [['Change Manager'], []],
    'staf tanpa izin dashboard' => [[], ['rfc.view_all']],
]);

it('menampilkan kerangka dashboard admin dengan tanggal dan jam WIB dinamis', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 26, 14, 5, 0, 'Asia/Jakarta'));

    $admin = staffDashboardUser(['Admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang, '.$admin->name)
        ->assertSee('Dashboard Admin')
        ->assertSee('Senin, 26 Oktober 2026 pukul 14:05 WIB')
        ->assertSee('Antrean RFC Masuk')
        ->assertSee('Daftar RFC')
        ->assertSee(route('change-requests.index', ['queue' => 'incoming']))
        ->assertSee(route('change-requests.index'));

    foreach (['CAB', 'ECAB', 'Release Management', 'Laporan', 'Pengaturan', 'SLA'] as $prohibited) {
        $response->assertDontSee($prohibited);
    }
});

it('menampilkan kerangka dashboard change manager dengan tanggal dan jam WIB dinamis', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 26, 8, 30, 0, 'Asia/Jakarta'));

    $manager = staffDashboardUser(['Change Manager']);

    $response = $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang, '.$manager->name)
        ->assertSee('Dashboard Change Manager')
        ->assertSee('Senin, 26 Oktober 2026 pukul 08:30 WIB')
        ->assertSee('Daftar RFC')
        ->assertDontSee('Antrean RFC Masuk');

    foreach (['CAB', 'ECAB', 'Release Management', 'Laporan', 'Pengaturan', 'SLA'] as $prohibited) {
        $response->assertDontSee($prohibited);
    }
});
