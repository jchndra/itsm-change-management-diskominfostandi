<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    Role::findOrCreate('Auditor Tanpa Izin', 'web');
});

/**
 * @param  array<int, string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function listAccessUser(array $roles = [], array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function listAccessRfc(User $pemohon, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ], $attributes));
}

it('menolak pengguna aktif tanpa role dan tidak menampilkan RFC apa pun', function () {
    $tanpaRole = listAccessUser(attributes: ['is_active' => true]);
    $rfc = listAccessRfc(listAccessUser(['Pemohon']), ['title' => 'RFC Rahasia Tanpa Akses']);

    $this->actingAs($tanpaRole)
        ->get(route('change-requests.index'))
        ->assertForbidden()
        ->assertDontSee($rfc->title);

    Livewire::actingAs($tanpaRole)
        ->test(ListChangeRequests::class)
        ->assertForbidden();
});

it('menolak role yang tidak memiliki izin view_own maupun view_all', function () {
    $auditor = listAccessUser(['Auditor Tanpa Izin'], ['is_active' => true]);
    $rfc = listAccessRfc(listAccessUser(['Pemohon']), ['title' => 'RFC Rahasia Untuk Role Asing']);

    $this->actingAs($auditor)
        ->get(route('change-requests.index'))
        ->assertForbidden()
        ->assertDontSee($rfc->title);

    Livewire::actingAs($auditor)
        ->test(ListChangeRequests::class)
        ->assertForbidden();
});

it('membatasi pemohon ke RFC miliknya pada HTML, pencarian, dan filter', function () {
    $pemohon = listAccessUser(['Pemohon']);
    $pemohonLain = listAccessUser(['Pemohon']);

    $milikSendiri = listAccessRfc($pemohon, [
        'title' => 'Pembaruan Sistem Persuratan Internal',
        'status' => RfcStatus::APPROVED->value,
    ]);

    $milikOrangLain = listAccessRfc($pemohonLain, [
        'title' => 'Pembaruan Basis Data Rahasia',
        'status' => RfcStatus::APPROVED->value,
    ]);

    $this->actingAs($pemohon)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->assertSee($milikSendiri->title)
        ->assertDontSee($milikOrangLain->title);

    Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertSee($milikSendiri->rfc_number)
        ->assertDontSee($milikOrangLain->rfc_number)
        ->set('search', 'Basis Data Rahasia')
        ->assertDontSee($milikOrangLain->title)
        ->set('search', 'Persuratan Internal')
        ->assertSee($milikSendiri->title)
        ->set('search', '')
        ->set('statusFilter', RfcStatus::APPROVED->value)
        ->assertSee($milikSendiri->rfc_number)
        ->assertDontSee($milikOrangLain->rfc_number);
});

it('tetap menampilkan seluruh RFC beserta statistik dan tombol untuk staf', function (string $role, string $roleView, array $expectedStats, array $expectedLabels) {
    $staff = listAccessUser([$role]);
    $pemohonSatu = listAccessUser(['Pemohon']);
    $pemohonDua = listAccessUser(['Pemohon']);

    $rfcMasuk = listAccessRfc($pemohonSatu, [
        'title' => 'RFC Masuk Untuk Staf',
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    $rfcTiket = listAccessRfc($pemohonDua, [
        'title' => 'RFC Siap Dievaluasi Staf',
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    $this->actingAs($staff)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->assertSee($rfcMasuk->title)
        ->assertSee($rfcTiket->title);

    $component = Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertViewHas('roleView', $roleView)
        ->assertSee($rfcMasuk->title)
        ->assertSee($rfcTiket->title);

    foreach ($expectedStats as $key => $value) {
        $component->assertViewHas('stats', fn (array $stats): bool => $stats[$key] === $value);
    }

    foreach ($expectedLabels as $label) {
        $component->assertSee($label);
    }
})->with([
    'admin' => [
        'Admin',
        'admin',
        ['incoming' => 1, 'needs_revision' => 0, 'forwarded' => 1, 'rejected' => 0],
        ['RFC Masuk', 'Perlu Revisi', 'Diteruskan', 'Buat Tiket', 'Detail'],
    ],
    'change manager' => [
        'Change Manager',
        'change_manager',
        [
            'total' => 2,
            'pending_approval' => 1,
            'approved' => 0,
            'rejected' => 0,
            'emergency' => 0,
        ],
        ['Total RFC', 'Menunggu Persetujuan', 'Disetujui', 'Emergency', 'Evaluasi', 'Detail'],
    ],
]);

it('menolak request Livewire lanjutan dari pengguna tanpa hak', function () {
    $admin = listAccessUser(['Admin']);
    $rfc = listAccessRfc(listAccessUser(['Pemohon']), ['title' => 'RFC Untuk Request Lanjutan']);

    $this->actingAs($admin);

    $component = Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertSee($rfc->title);

    $this->actingAs(listAccessUser(attributes: ['is_active' => true]));

    $component->refresh()->assertForbidden();
});

it('menentukan akses dari gabungan izin dua role', function (array $roles, bool $seesAll, string $roleView) {
    $user = listAccessUser($roles);
    $pemohonLain = listAccessUser(['Pemohon']);

    $milikSendiri = listAccessRfc($user, ['title' => 'RFC Milik Pengguna Dua Peran']);
    $milikOrangLain = listAccessRfc($pemohonLain, ['title' => 'RFC Milik Pemohon Lain']);

    $component = Livewire::actingAs($user)
        ->test(ListChangeRequests::class)
        ->assertOk()
        ->assertViewHas('roleView', $roleView)
        ->assertSee($milikSendiri->title);

    if ($seesAll) {
        $component->assertSee($milikOrangLain->title);
    } else {
        $component->assertDontSee($milikOrangLain->title);
    }
})->with([
    'pemohon dan admin' => [['Pemohon', 'Admin'], true, 'admin'],
    'pemohon dan change manager' => [['Pemohon', 'Change Manager'], true, 'change_manager'],
    'pemohon dan role tanpa izin' => [['Pemohon', 'Auditor Tanpa Izin'], false, 'pemohon'],
]);
