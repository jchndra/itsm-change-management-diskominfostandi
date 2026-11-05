<?php

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcNumberGenerator;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('menolak nomor RFC resmi duplikat di database', function () {
    $number = 'RFC-'.date('Y').'-9001';

    ChangeRequest::factory()->create([
        'rfc_number' => $number,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    expect(fn () => ChangeRequest::factory()->create([
        'rfc_number' => $number,
        'status' => RfcStatus::SUBMITTED->value,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('mempertahankan nomor resmi setelah transisi status dan soft delete', function () {
    $number = 'RFC-PD01-9100';

    $owner = User::factory()->create();
    $owner->assignRole('Pemohon');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'rfc_number' => $number,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $admin);

    expect($rfc->fresh()->rfc_number)->toBe($number);

    $rfc->delete();

    $deleted = ChangeRequest::withTrashed()->findOrFail($rfc->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->rfc_number)->toBe($number);

    $deleted->restore();

    expect($deleted->fresh()->rfc_number)->toBe($number)
        ->and(RfcNumberGenerator::generate('PD01'))->toBe('RFC-PD01-9101');
});
