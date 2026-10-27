<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\DeleteRfc;
use App\Livewire\ChangeRequests\DeletionHistory;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\Requester\Dashboard;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcDeletionService;
use App\Services\RfcNumberGenerator;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function pemohonBaru(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('Pemohon');

    return $user;
}

function staffBaru(string $role = 'Admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function draftMilik(User $owner, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $owner->id,
        'created_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
    ], $attributes));
}

it('memungkinkan pemohon menghapus draft miliknya secara soft delete dengan alasan', function () {
    $pemohon = pemohonBaru();
    $rfc = draftMilik($pemohon, [
        'rfc_number' => 'RFC-2026-8001',
        'title' => 'Draft yang akan dihapus',
    ]);

    $this->actingAs($pemohon);

    Livewire::test(DeleteRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->assertSet('showModal', true)
        ->assertSee($rfc->displayIdentifier())
        ->assertSee($rfc->title)
        ->set('reason', 'Draft dibuat untuk pengujian internal.')
        ->call('delete')
        ->assertDispatched('rfc-deleted')
        ->assertSet('showModal', false);

    $this->assertSoftDeleted('change_requests', ['id' => $rfc->id]);

    $deleted = ChangeRequest::withTrashed()->findOrFail($rfc->id);

    expect($deleted->deleted_by_id)->toBe($pemohon->id)
        ->and($deleted->deletion_reason)->toBe('Draft dibuat untuk pengujian internal.')
        ->and($deleted->rfc_number)->toBe('RFC-2026-8001');

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => ChangeRequest::class,
        'subject_id' => $rfc->id,
        'causer_id' => $pemohon->id,
    ]);
});

it('menampilkan notifikasi keberhasilan penghapusan pada dashboard pemohon', function () {
    $pemohon = pemohonBaru();

    $this->actingAs($pemohon);

    Livewire::test(Dashboard::class)
        ->dispatch('rfc-deleted', message: 'RFC-2026-8999 berhasil dihapus dan dipindahkan ke Riwayat Penghapusan.')
        ->assertSee('RFC-2026-8999 berhasil dihapus dan dipindahkan ke Riwayat Penghapusan.');
});

it('menampilkan aksi hapus pada draft milik pemohon di dashboard dan daftar RFC', function () {
    $pemohon = pemohonBaru();
    $rfc = draftMilik($pemohon, ['rfc_number' => 'RFC-2026-8007', 'title' => 'Draft dengan aksi hapus']);

    $this->actingAs($pemohon);

    Livewire::test(Dashboard::class)
        ->assertSee($rfc->displayIdentifier())
        ->assertSee('Hapus RFC');

    Livewire::test(ListChangeRequests::class)
        ->assertSee($rfc->displayIdentifier())
        ->assertSee('Hapus');
});

it('membuka halaman riwayat penghapusan lewat rute untuk pemohon dan staf', function () {
    $pemohon = pemohonBaru();
    $admin = staffBaru('Admin');

    $this->actingAs($pemohon)
        ->get(route('change-requests.deletions.index'))
        ->assertOk()
        ->assertSee('Riwayat Penghapusan');

    $this->actingAs($admin)
        ->get(route('change-requests.deletions.index'))
        ->assertOk()
        ->assertSee('Riwayat Penghapusan');
});

it('menolak penghapusan RFC yang statusnya bukan draft', function () {
    $pemohon = pemohonBaru();
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    $this->actingAs($pemohon);

    Livewire::test(DeleteRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->assertForbidden();

    $this->assertNotSoftDeleted('change_requests', ['id' => $rfc->id]);
});

it('menolak pemohon menghapus draft milik pengguna lain', function () {
    $pemilik = pemohonBaru();
    $penyusup = pemohonBaru();
    $rfc = draftMilik($pemilik);

    $this->actingAs($penyusup);

    Livewire::test(DeleteRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->assertForbidden();

    $this->assertNotSoftDeleted('change_requests', ['id' => $rfc->id]);
});

it('menyembunyikan RFC terhapus dari dashboard, daftar aktif, dan statistik', function () {
    $pemohon = pemohonBaru();
    $rfc = draftMilik($pemohon, ['rfc_number' => 'RFC-2026-8002']);

    RfcDeletionService::delete($rfc, $pemohon, 'Sudah tidak dibutuhkan.');

    $this->actingAs($pemohon);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertDontSee($rfc->displayIdentifier())
        ->assertViewHas('stats', fn (array $stats) => $stats['draft'] === 0);

    Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertDontSee($rfc->displayIdentifier());

    $this->assertDatabaseHas('change_requests', ['id' => $rfc->id]);
});

it('tidak memakai ulang nomor RFC yang sudah dihapus', function () {
    $pemohon = pemohonBaru();
    $tahun = date('Y');
    $rfc = draftMilik($pemohon, ['rfc_number' => "RFC-{$tahun}-0001"]);

    RfcDeletionService::delete($rfc, $pemohon);

    expect(RfcNumberGenerator::generate())->toBe("RFC-{$tahun}-0002");
});

it('menampilkan riwayat penghapusan hanya untuk RFC milik pemohon', function () {
    $pemohon = pemohonBaru();
    $pemohonLain = pemohonBaru();

    $milikSendiri = draftMilik($pemohon, [
        'rfc_number' => 'RFC-2026-8003',
        'title' => 'Draft milik sendiri',
    ]);
    $milikOrangLain = draftMilik($pemohonLain, [
        'rfc_number' => 'RFC-2026-8004',
        'title' => 'Draft milik orang lain',
    ]);

    RfcDeletionService::delete($milikSendiri, $pemohon, 'Alasan pemohon sendiri.');
    RfcDeletionService::delete($milikOrangLain, $pemohonLain, 'Alasan pemohon lain.');

    $this->actingAs($pemohon);

    Livewire::test(DeletionHistory::class)
        ->assertOk()
        ->assertSee('Riwayat Penghapusan')
        ->assertSee($milikSendiri->displayIdentifier())
        ->assertSee('Alasan pemohon sendiri.')
        ->assertSee('Lihat Detail')
        ->assertDontSee($milikOrangLain->displayIdentifier())
        ->assertDontSee('Pulihkan');
});

it('memungkinkan staf melihat semua riwayat dan memulihkan RFC tanpa mengubah status', function () {
    $pemohon = pemohonBaru();
    $admin = staffBaru('Admin');
    $rfc = draftMilik($pemohon, [
        'rfc_number' => 'RFC-2026-8005',
        'title' => 'Draft untuk dipulihkan',
    ]);

    RfcDeletionService::delete($rfc, $pemohon, 'Dihapus sementara.');

    $this->actingAs($admin);

    Livewire::test(DeletionHistory::class)
        ->assertSee($rfc->displayIdentifier())
        ->assertSee('Pulihkan')
        ->call('confirmRestore', $rfc->id)
        ->assertSet('showRestoreModal', true)
        ->assertSee('Pulihkan RFC?')
        ->call('restoreRfc')
        ->assertSet('showRestoreModal', false)
        ->assertSee('berhasil dipulihkan');

    $this->assertNotSoftDeleted('change_requests', ['id' => $rfc->id]);

    $restored = $rfc->fresh();

    expect($restored->status)->toBe(RfcStatus::DRAFT->value)
        ->and($restored->deleted_at)->toBeNull();

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => ChangeRequest::class,
        'subject_id' => $rfc->id,
        'causer_id' => $admin->id,
    ]);
});

it('menolak pemohon memulihkan RFC', function () {
    $pemohon = pemohonBaru();
    $rfc = draftMilik($pemohon);

    RfcDeletionService::delete($rfc, $pemohon);

    $this->actingAs($pemohon);

    Livewire::test(DeletionHistory::class)
        ->assertDontSee('Pulihkan')
        ->call('confirmRestore', $rfc->id)
        ->assertForbidden();
});

it('menampilkan detail RFC terhapus kepada pemilik dan staf, tetapi tidak kepada pemohon lain', function () {
    $pemilik = pemohonBaru();
    $pemohonLain = pemohonBaru();
    $admin = staffBaru('Admin');

    $rfc = draftMilik($pemilik, [
        'rfc_number' => 'RFC-2026-8006',
        'title' => 'Draft dengan detail terhapus',
    ]);

    RfcDeletionService::delete($rfc, $pemilik, 'Alasan untuk halaman detail.');

    $this->actingAs($pemilik)
        ->get(route('change-requests.deletions.show', $rfc->id))
        ->assertOk()
        ->assertSee('RFC ini telah dihapus')
        ->assertSee($rfc->displayIdentifier())
        ->assertSee('Alasan untuk halaman detail.');

    $this->actingAs($admin)
        ->get(route('change-requests.deletions.show', $rfc->id))
        ->assertOk()
        ->assertSee($rfc->displayIdentifier());

    $this->actingAs($pemohonLain)
        ->get(route('change-requests.deletions.show', $rfc->id))
        ->assertForbidden();

    $this->get(route('change-requests.show', $rfc->id))->assertNotFound();
});

it('mendukung pencarian dan filter status pada riwayat penghapusan', function () {
    $admin = staffBaru('Admin');
    $pemohon = pemohonBaru();

    $pertama = draftMilik($pemohon, [
        'rfc_number' => 'RFC-2026-8101',
        'title' => 'Migrasi Server Surel',
    ]);
    $kedua = draftMilik($pemohon, [
        'rfc_number' => 'RFC-2026-8102',
        'title' => 'Pembaruan Aplikasi Arsip',
    ]);

    RfcDeletionService::delete($pertama, $pemohon);
    RfcDeletionService::delete($kedua, $pemohon);

    $this->actingAs($admin);

    Livewire::test(DeletionHistory::class)
        ->set('search', 'Surel')
        ->assertSee($pertama->displayIdentifier())
        ->assertDontSee($kedua->displayIdentifier())
        ->set('search', $pertama->displayIdentifier())
        ->assertSee($pertama->displayIdentifier())
        ->assertDontSee($kedua->displayIdentifier())
        ->set('search', '')
        ->set('statusFilter', RfcStatus::DRAFT->value)
        ->assertSee($pertama->displayIdentifier())
        ->assertSee($kedua->displayIdentifier())
        ->set('statusFilter', RfcStatus::SUBMITTED->value)
        ->assertDontSee($pertama->displayIdentifier())
        ->assertDontSee($kedua->displayIdentifier());
});
