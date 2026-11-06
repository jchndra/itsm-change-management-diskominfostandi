<?php

use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('admin melihat panah kembali ke daftar RFC pada detail RFC', function () {
    $admin = makeUserWithRole('Admin');
    $rfc = ChangeRequest::factory()->create();

    $this->actingAs($admin);

    $html = Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->html();

    expect($html)->toMatch(
        '/<a[^>]+href="'.preg_quote(route('change-requests.index'), '/').'"[^>]*class="[^"]*rounded-full/'
    );
});

test('pemohon melihat panah kembali ke dashboard pemohon pada detail RFC', function () {
    $pemohon = makeUserWithRole('Pemohon');
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
    ]);

    $this->actingAs($pemohon);

    $html = Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->html();

    expect($html)->toMatch(
        '/<a[^>]+href="'.preg_quote(route('requester.dashboard'), '/').'"[^>]*class="[^"]*rounded-full/'
    );
});

test('rute root mengarahkan pemohon ke dashboard pemohon', function () {
    $pemohon = makeUserWithRole('Pemohon');

    $this->actingAs($pemohon)
        ->get('/')
        ->assertRedirect(route('requester.dashboard'));
});

test('rute root mengarahkan admin dan change manager ke dashboard sesuai izin', function () {
    $admin = makeUserWithRole('Admin');
    $manager = makeUserWithRole('Change Manager');

    $this->actingAs($admin)
        ->get('/')
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($manager)
        ->get('/')
        ->assertRedirect(route('change-manager.dashboard'));
});

test('admin tanpa rfc.create mendapat 403 di halaman create RFC', function () {
    $admin = makeUserWithRole('Admin');

    $this->actingAs($admin)
        ->get(route('change-requests.create'))
        ->assertForbidden();
});

test('pemohon dengan rfc.create dapat membuka halaman create RFC', function () {
    $pemohon = makeUserWithRole('Pemohon');

    $this->actingAs($pemohon)
        ->get(route('change-requests.create'))
        ->assertOk();
});

test('RFC yang diajukan lewat komponen create memakai nomor berurutan dari generator', function () {
    Storage::fake('local');

    $pemohon = makeUserWithRole('Pemohon');
    $application = Application::factory()->create([
        'opd_id' => Opd::factory()->create(['code' => 'PD01'])->id,
    ]);

    $this->actingAs($pemohon);

    foreach (['Perubahan Pertama', 'Perubahan Kedua'] as $title) {
        Livewire::test(CreateChangeRequest::class)
            ->set('title', $title)
            ->set('application_id', (string) $application->id)
            ->set('change_type', 'normal')
            ->set('description', 'Deskripsi perubahan yang cukup jelas untuk pengujian.')
            ->set('risk_event', RiskEvent::NATIONAL_SERVICE_INTEGRATION->value)
            ->set('reason_for_change', 'Alasan perubahan yang cukup panjang untuk pengujian.')
            ->set('legal_basis', 'Dasar hukum perubahan yang cukup jelas untuk pengujian.')
            ->set('change_risk', 'Risiko perubahan yang sudah diidentifikasi untuk pengujian.')
            ->set('impact_description', 'Dampak perubahan yang cukup jelas untuk pengujian.')
            ->set('head_of_department_approved', true)
            ->set('attachments', [UploadedFile::fake()->create('surat-persetujuan.pdf', 100, 'application/pdf')])
            ->call('save', RfcStatus::TICKET_CREATED->value)
            ->assertHasNoErrors()
            ->assertRedirect(route('requester.dashboard'));
    }

    expect(ChangeRequest::orderBy('id')->pluck('rfc_number')->all())
        ->toBe([
            'RFC-PD01-1',
            'RFC-PD01-2',
        ]);
});

test('pengguna tanpa izin implement mendapat 403 di halaman implementasi', function () {
    $pemohon = makeUserWithRole('Pemohon');
    $admin = makeUserWithRole('Admin');
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    $this->actingAs($pemohon)
        ->get(route('change-requests.implement', $rfc))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('change-requests.implement', $rfc))
        ->assertForbidden();
});
