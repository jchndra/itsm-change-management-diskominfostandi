<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\DeleteRfc;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function penggunaFilter(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('menampilkan pesan flash global sebagai toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['message' => 'RFC berhasil disimpan sebagai draft.'])
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('RFC berhasil disimpan sebagai draft.');

    $this->actingAs($user)
        ->withSession(['error' => 'RFC gagal disimpan.'])
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('RFC gagal disimpan.');
});

it('memfilter daftar RFC berdasarkan pencarian, status, jenis, aplikasi, unit kerja, dan tanggal', function () {
    $admin = penggunaFilter('Admin');

    $opdSatu = Opd::factory()->create(['name' => 'Dinas Pendidikan']);
    $opdDua = Opd::factory()->create(['name' => 'Dinas Kesehatan']);

    $pemohonSatu = penggunaFilter('Pemohon', ['opd_id' => $opdSatu->id]);
    $pemohonDua = penggunaFilter('Pemohon', ['opd_id' => $opdDua->id]);

    $aplikasiSatu = Application::factory()->create(['name' => 'Sistem Persuratan']);
    $aplikasiDua = Application::factory()->create(['name' => 'Sistem Keuangan']);

    $rfcSatu = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonSatu->id,
        'created_by_id' => $pemohonSatu->id,
        'application_id' => $aplikasiSatu->id,
        'title' => 'Migrasi Server Persuratan',
        'status' => RfcStatus::SUBMITTED->value,
        'change_type' => 'standard',
        'created_at' => now()->subDays(10),
    ]);

    $rfcDua = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonDua->id,
        'created_by_id' => $pemohonDua->id,
        'application_id' => $aplikasiDua->id,
        'title' => 'Pembaruan Aplikasi Keuangan',
        'status' => RfcStatus::APPROVED->value,
        'change_type' => 'emergency',
        'created_at' => now()->subDay(),
    ]);

    $rfcTiga = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonSatu->id,
        'created_by_id' => $pemohonSatu->id,
        'application_id' => $aplikasiSatu->id,
        'title' => 'Migrasi Jaringan Persuratan',
        'status' => RfcStatus::REJECTED->value,
        'change_type' => 'normal',
        'created_at' => now()->subDays(5),
    ]);

    $this->actingAs($admin);

    Livewire::test(ListChangeRequests::class)
        ->set('search', 'Server')
        ->assertSee($rfcSatu->rfc_number)
        ->assertDontSee($rfcDua->rfc_number)
        ->assertDontSee($rfcTiga->rfc_number)
        ->set('search', '')
        ->set('statusFilter', RfcStatus::APPROVED->value)
        ->assertSee($rfcDua->rfc_number)
        ->assertDontSee($rfcSatu->rfc_number)
        ->set('statusFilter', '')
        ->set('categoryFilter', 'emergency')
        ->assertSee($rfcDua->rfc_number)
        ->assertDontSee($rfcSatu->rfc_number)
        ->set('categoryFilter', '')
        ->set('applicationFilter', (string) $aplikasiSatu->id)
        ->assertSee($rfcSatu->rfc_number)
        ->assertSee($rfcTiga->rfc_number)
        ->assertDontSee($rfcDua->rfc_number)
        ->set('applicationFilter', '')
        ->set('opdFilter', (string) $opdSatu->id)
        ->assertSee($rfcSatu->rfc_number)
        ->assertSee($rfcTiga->rfc_number)
        ->assertDontSee($rfcDua->rfc_number)
        ->set('opdFilter', '')
        ->set('dateFrom', now()->subDays(6)->toDateString())
        ->assertSee($rfcDua->rfc_number)
        ->assertSee($rfcTiga->rfc_number)
        ->assertDontSee($rfcSatu->rfc_number)
        ->set('dateFrom', '')
        ->set('dateTo', now()->subDays(6)->toDateString())
        ->assertSee($rfcSatu->rfc_number)
        ->assertDontSee($rfcDua->rfc_number)
        ->assertDontSee($rfcTiga->rfc_number)
        ->set('dateTo', '')
        ->set('search', 'Server')
        ->call('resetFilters')
        ->assertSee($rfcSatu->rfc_number)
        ->assertSee($rfcDua->rfc_number)
        ->assertSee($rfcTiga->rfc_number);
});

it('menampilkan label status yang unik pada filter daftar RFC', function () {
    $admin = penggunaFilter('Admin');

    $this->actingAs($admin);

    $statuses = Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->viewData('statuses');

    expect($statuses[RfcStatus::TICKET_CREATED->value])->toBe('Sedang Ditinjau')
        ->and($statuses[RfcStatus::RETURNED_TO_ADMIN->value])->toBe('Dikembalikan ke Admin')
        ->and($statuses->values()->unique()->count())->toBe(count(RfcStatus::cases()));
});

it('mencari draft menggunakan identitas tampilan Draft #<id>', function () {
    $admin = penggunaFilter('Admin');
    $pemohon = penggunaFilter('Pemohon');

    $draft = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
        'title' => 'Draft Pencarian Identitas',
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
    ]);

    $draftLain = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
        'title' => 'Draft Lain yang Tidak Dicari',
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListChangeRequests::class)
        ->set('search', $draft->displayIdentifier())
        ->assertSee($draft->title)
        ->assertDontSee($draftLain->title);
});

it('tetap membatasi filter pada RFC milik pemohon sendiri', function () {
    $pemohon = penggunaFilter('Pemohon');
    $pemohonLain = penggunaFilter('Pemohon');

    $milikSendiri = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
        'status' => RfcStatus::APPROVED->value,
        'title' => 'RFC Milik Sendiri',
    ]);

    $milikOrangLain = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonLain->id,
        'created_by_id' => $pemohonLain->id,
        'status' => RfcStatus::APPROVED->value,
        'title' => 'RFC Milik Orang Lain',
    ]);

    $this->actingAs($pemohon);

    Livewire::test(ListChangeRequests::class)
        ->set('statusFilter', RfcStatus::APPROVED->value)
        ->assertSee($milikSendiri->rfc_number)
        ->assertDontSee($milikOrangLain->rfc_number);
});

it('menyediakan markup tabel responsif dan modal yang dapat ditutup dengan escape', function () {
    $pemohon = penggunaFilter('Pemohon');
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
        'status' => RfcStatus::DRAFT->value,
    ]);

    $this->actingAs($pemohon);

    $listHtml = Livewire::test(ListChangeRequests::class)
        ->assertSeeHtml('rfc-responsive-table')
        ->assertSeeHtml('data-label="Aksi"')
        ->html();

    expect($listHtml)->toContain('rfc-responsive-table');

    $deleteHtml = Livewire::test(DeleteRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->assertSee('Hapus RFC?')
        ->html();

    expect($deleteHtml)->toContain('wire:keydown.escape.window="closeModal"')
        ->and($deleteHtml)->toContain('autofocus');
});
