<?php

use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function sidebarActiveLink(string $html, string $url): bool
{
    return (bool) preg_match(
        '/<a href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/',
        $html
    );
}

it('hanya menandai menu riwayat penghapusan sebagai aktif pada halaman riwayat', function () {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $html = $this->actingAs($pemohon)
        ->get(route('change-requests.deletions.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('change-requests.deletions.index')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeFalse();
});

it('tetap menandai menu RFC Saya sebagai aktif pada detail RFC', function () {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ]);

    $html = $this->actingAs($pemohon)
        ->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('change-requests.deletions.index')))->toBeFalse();
});

it('hanya menandai menu Dashboard Admin aktif pada dashboard admin', function () {
    $user = User::factory()->create();
    $user->assignRole(['Admin', 'Change Manager']);

    $html = $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('admin.dashboard')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('change-manager.dashboard')))->toBeFalse()
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeFalse();
});

it('hanya menandai menu Dashboard Change Manager aktif pada dashboard change manager', function () {
    $user = User::factory()->create();
    $user->assignRole(['Admin', 'Change Manager']);

    $html = $this->actingAs($user)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('change-manager.dashboard')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('admin.dashboard')))->toBeFalse()
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeFalse();
});

it('menandai menu Daftar RFC aktif pada daftar RFC staf', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Change Manager');

    $html = $this->actingAs($manager)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('change-manager.dashboard')))->toBeFalse();
});

it('menandai menu Daftar RFC aktif pada detail RFC staf', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Change Manager');
    $rfc = ChangeRequest::factory()->create();

    $html = $this->actingAs($manager)
        ->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeTrue()
        ->and(sidebarActiveLink($html, route('change-manager.dashboard')))->toBeFalse();
});

it('tidak menandai menu Buat Tiket Change maupun Daftar RFC pada antrean masuk', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $html = $this->actingAs($admin)
        ->get(route('change-requests.index', ['queue' => 'incoming']))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(0)
        ->and(sidebarActiveLink($html, route('change-requests.index', ['queue' => 'incoming'])))->toBeFalse()
        ->and(sidebarActiveLink($html, route('change-requests.index')))->toBeFalse();
});
