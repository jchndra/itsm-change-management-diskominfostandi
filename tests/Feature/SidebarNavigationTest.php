<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @param  array<int, string>  $roles
 */
function sidebarNavigationUser(array $roles = []): User
{
    $user = User::factory()->create();

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

function sidebarNavigationHtml(string $html): string
{
    $start = strpos($html, '<aside');
    $end = strpos($html, '</aside>');

    return $start === false || $end === false
        ? ''
        : substr($html, $start, $end - $start);
}

function sidebarGroupButton(string $sidebar, string $id): string
{
    return preg_match(
        '/<button[^>]*aria-controls="sidebar-submenu-'.preg_quote($id, '/').'"[^>]*>/',
        $sidebar,
        $matches
    ) ? $matches[0] : '';
}

function sidebarNavigationActiveLink(string $html, string $url): bool
{
    return (bool) preg_match(
        '/<a href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/',
        $html
    );
}

it('membuka submenu Change Request otomatis pada halaman anak yang aktif', function () {
    $manager = sidebarNavigationUser(['Change Manager']);

    $html = $this->actingAs($manager)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->getContent();

    $sidebar = sidebarNavigationHtml($html);

    expect($sidebar)->toContain('aria-controls="sidebar-submenu-change-request"')
        ->and(sidebarGroupButton($sidebar, 'change-request'))->toContain('aria-expanded="true"')
        ->and(sidebarNavigationActiveLink($sidebar, route('change-requests.index')))->toBeTrue()
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});

it('menutup submenu Change Request saat tidak ada halaman anak yang aktif', function () {
    $manager = sidebarNavigationUser(['Change Manager']);

    $sidebar = sidebarNavigationHtml(
        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->getContent()
    );

    expect(sidebarGroupButton($sidebar, 'change-request'))->toContain('aria-expanded="false"');
});

it('menampilkan submenu Administrasi hanya untuk pengguna yang berwenang', function () {
    $admin = sidebarNavigationUser(['Admin']);
    $manager = sidebarNavigationUser(['Change Manager']);
    $superAdmin = sidebarNavigationUser(['Super Admin']);

    $adminSidebar = sidebarNavigationHtml(
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->getContent()
    );

    expect($adminSidebar)->toContain('aria-controls="sidebar-submenu-administration"')
        ->and($adminSidebar)->toContain('Kelola Pengguna')
        ->and($adminSidebar)->not->toContain('Akun Tim Rilis');

    $managerSidebar = sidebarNavigationHtml(
        $this->actingAs($manager)->get(route('notifications.index'))->assertOk()->getContent()
    );

    expect($managerSidebar)->not->toContain('aria-controls="sidebar-submenu-administration"')
        ->and($managerSidebar)->not->toContain('Kelola Pengguna')
        ->and($managerSidebar)->not->toContain('Akun Tim Rilis');

    $superAdminSidebar = sidebarNavigationHtml(
        $this->actingAs($superAdmin)->get(route('notifications.index'))->assertOk()->getContent()
    );

    expect($superAdminSidebar)->toContain('Akun Tim Rilis')
        ->and($superAdminSidebar)->toContain('Kelola Pengguna');
});

it('membuka submenu Administrasi pada halaman anak yang aktif', function () {
    $admin = sidebarNavigationUser(['Admin']);

    $html = $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->getContent();

    $sidebar = sidebarNavigationHtml($html);

    expect(sidebarGroupButton($sidebar, 'administration'))->toContain('aria-expanded="true"')
        ->and(sidebarNavigationActiveLink($sidebar, route('admin.users.index')))->toBeTrue()
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});
