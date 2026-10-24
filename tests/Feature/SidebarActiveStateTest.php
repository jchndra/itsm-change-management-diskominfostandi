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
