<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ReviewCompleteness;
use App\Models\ChangeRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeReturnedToAdminRfc(): ChangeRequest
{
    $pemohon = User::factory()->create();

    return ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
    ]);
}

test('admin dapat membuat tiket dari rfc returned_to_admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $rfc = makeReturnedToAdminRfc();

    $this->actingAs($admin);

    Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->set('action', RfcStatus::TICKET_CREATED->value)
        ->set('priority', 'high')
        ->set('category', 'application')
        ->set('data_complete', true)
        ->set('attachments_verified', true)
        ->set('application_verified', true)
        ->call('submitReview')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->fresh()->ticket_number)->toStartWith('CHG-');
});

test('admin dapat mengembalikan rfc returned_to_admin ke needs_revision', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $rfc = makeReturnedToAdminRfc();

    $this->actingAs($admin);

    Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->set('action', RfcStatus::NEEDS_REVISION->value)
        ->set('note', 'Dokumen pendukung belum lengkap.')
        ->call('submitReview')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::NEEDS_REVISION->value);
    expect($rfc->fresh()->rejection_note)->toBe('Dokumen pendukung belum lengkap.');
});

test('admin dapat menolak rfc returned_to_admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $rfc = makeReturnedToAdminRfc();

    $this->actingAs($admin);

    Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->set('action', RfcStatus::REJECTED->value)
        ->set('note', 'Tidak memenuhi ketentuan.')
        ->call('submitReview')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::REJECTED->value);
});
