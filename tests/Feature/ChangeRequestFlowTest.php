<?php

use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcNumberGenerator;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('pemohon hanya dapat melihat rfc miliknya sendiri di daftar rfc', function () {
    $pemohonA = User::factory()->create();
    $pemohonA->assignRole('Pemohon');

    $pemohonB = User::factory()->create();
    $pemohonB->assignRole('Pemohon');

    $app = Application::factory()->create();

    $rfcA = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonA->id,
        'application_id' => $app->id,
    ]);

    $rfcB = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohonB->id,
        'application_id' => $app->id,
    ]);

    $this->actingAs($pemohonA);

    Livewire::test(ListChangeRequests::class)
        ->assertSee($rfcA->rfc_number)
        ->assertDontSee($rfcB->rfc_number);
});

test('nomor rfc otomatis dibuat berurutan per kode opd', function () {
    $number1 = RfcNumberGenerator::generate('PD01');
    expect($number1)->toBe('RFC-PD01-1');

    ChangeRequest::factory()->create(['rfc_number' => $number1]);

    $number2 = RfcNumberGenerator::generate('PD01');
    expect($number2)->toBe('RFC-PD01-2');

    // OPD lain memiliki urutan sendiri dan tidak terpengaruh nomor OPD pertama.
    expect(RfcNumberGenerator::generate('PD02'))->toBe('RFC-PD02-1');
});
