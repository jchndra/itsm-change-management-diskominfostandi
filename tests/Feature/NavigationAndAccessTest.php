<?php

use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('rute root mengarahkan admin dan change manager ke daftar RFC', function () {
    $admin = makeUserWithRole('Admin');
    $manager = makeUserWithRole('Change Manager');

    $this->actingAs($admin)
        ->get('/')
        ->assertRedirect(route('change-requests.index'));

    $this->actingAs($manager)
        ->get('/')
        ->assertRedirect(route('change-requests.index'));
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

test('RFC yang dibuat lewat komponen create memakai nomor berurutan dari generator', function () {
    $pemohon = makeUserWithRole('Pemohon');
    $application = Application::factory()->create();

    $this->actingAs($pemohon);

    foreach (['Perubahan Pertama', 'Perubahan Kedua'] as $title) {
        Livewire::test(CreateChangeRequest::class)
            ->set('title', $title)
            ->set('application_id', (string) $application->id)
            ->set('change_type', 'normal')
            ->set('description', 'Deskripsi perubahan yang cukup jelas untuk pengujian.')
            ->set('reason_for_change', 'Alasan perubahan yang cukup panjang untuk pengujian.')
            ->set('impact_description', 'Dampak perubahan yang cukup jelas untuk pengujian.')
            ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('requester.dashboard'));
    }

    $year = date('Y');

    expect(ChangeRequest::orderBy('id')->pluck('rfc_number')->all())
        ->toBe([
            "RFC-{$year}-0001",
            "RFC-{$year}-0002",
        ]);
});

test('pengguna tanpa rfc.assign_release mendapat 403 di halaman upload evidence', function () {
    $pemohon = makeUserWithRole('Pemohon');
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
    ]);

    $this->actingAs($pemohon)
        ->get(route('change-requests.upload-evidence', $rfc))
        ->assertForbidden();
});
