<?php

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon PDF']);
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create(['name' => 'Pemohon PDF Lain']);
    $this->pemohonLain->assignRole('Pemohon');

    $this->cm = User::factory()->create(['name' => 'Change Manager PDF']);
    $this->cm->assignRole('Change Manager');

    $this->timRilis = User::factory()->create(['name' => 'Tim Rilis PDF']);
    $this->timRilis->assignRole('Tim Rilis');

    $this->makeRfc = function (string $status, ?User $owner = null): ChangeRequest {
        return ChangeRequest::factory()->create([
            'rfc_number' => 'RFC-2026-9001',
            'submitted_by_id' => ($owner ?? $this->pemohon)->id,
            'created_by_id' => ($owner ?? $this->pemohon)->id,
            'status' => $status,
        ]);
    };
});

it('mengunduh PDF hasil RFC closed oleh Change Manager', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    $response = $this->actingAs($this->cm)
        ->get(route('change-requests.result-pdf', $rfc));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment; filename="Hasil-RFC-')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('mengizinkan Pemohon pemilik mengunduh PDF hasil RFC', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.result-pdf', $rfc))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('menolak Pemohon lain mengunduh PDF hasil RFC', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    $this->actingAs($this->pemohonLain)
        ->get(route('change-requests.result-pdf', $rfc))
        ->assertForbidden();
});

it('menolak unduhan untuk RFC yang belum final', function () {
    $rfc = ($this->makeRfc)(RfcStatus::APPROVED->value);

    $this->actingAs($this->cm)
        ->get(route('change-requests.result-pdf', $rfc))
        ->assertForbidden();
});

it('mengalihkan tamu ke halaman login', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    $this->get(route('change-requests.result-pdf', $rfc))
        ->assertRedirect(route('login'));
});

it('mengembalikan 404 untuk RFC yang sudah dihapus', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $rfc->delete();

    $this->actingAs($this->cm)
        ->get(route('change-requests.result-pdf', $rfc))
        ->assertNotFound();
});

it('menyembunyikan penilaian Pemohon dari Tim Rilis pada view PDF', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $rfc->satisfaction()->create([
        'user_id' => $this->pemohon->id,
        'rating' => 5,
        'comment' => 'Pelayanan sangat baik.',
    ]);

    $html = view('pdf.rfc-result', [
        'rfc' => $rfc->fresh(),
        'viewer' => $this->timRilis,
    ])->render();

    expect($html)->not->toContain('Penilaian Pemohon')
        ->not->toContain('Pelayanan sangat baik.');
});

it('menampilkan penilaian Pemohon untuk Change Manager pada view PDF', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $rfc->satisfaction()->create([
        'user_id' => $this->pemohon->id,
        'rating' => 4,
        'comment' => 'Pelayanan sudah baik.',
    ]);

    $html = view('pdf.rfc-result', [
        'rfc' => $rfc->fresh(),
        'viewer' => $this->cm,
    ])->render();

    expect($html)->toContain('Penilaian Pemohon')
        ->toContain('Pelayanan sudah baik.')
        ->toContain('4/5');
});
