<?php

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function userWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

test('pemilik rfc dapat mengajukan draft dan nomor resmi terbit saat diajukan', function () {
    $opd = Opd::factory()->create(['code' => 'PD01']);
    $owner = userWithRole('Pemohon', ['opd_id' => $opd->id]);
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
    ]);

    expect($rfc->displayIdentifier())->toBe('Draft #'.$rfc->id);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::SUBMITTED, $owner);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::SUBMITTED->value)
        ->and($fresh->rfc_number)->toBe('RFC-PD01-1')
        ->and($fresh->displayIdentifier())->toBe($fresh->rfc_number);
});

test('pemilik dapat mengajukan langsung ke ticket_created dengan nomor resmi', function () {
    $opd = Opd::factory()->create(['code' => 'PD01']);
    $owner = userWithRole('Pemohon', ['opd_id' => $opd->id]);
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
    ]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $owner);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->rfc_number)->toBe('RFC-PD01-1');
});

test('change manager dapat meminta revisi dari ticket_created tetapi admin dan super admin tidak', function () {
    $cm = userWithRole('Change Manager');
    $admin = userWithRole('Admin');
    $superAdmin = userWithRole('Super Admin');

    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    foreach ([$admin, $superAdmin] as $actor) {
        expect(fn () => RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::NEEDS_REVISION, $actor))
            ->toThrow(InvalidRfcStatusTransitionException::class);
    }

    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::NEEDS_REVISION, $cm, 'Lengkapi lampiran.');

    expect($rfc->fresh()->status)->toBe(RfcStatus::NEEDS_REVISION->value);
});

test('admin dapat meneruskan submitted ke ticket_created', function () {
    $admin = userWithRole('Admin');
    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $admin);

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value);
});

test('tim rilis yang ditugaskan menjalankan approved sampai implemented lalu change manager menutup', function () {
    $cm = userWithRole('Change Manager');
    $releaseTeam = userWithRole('Tim Rilis');
    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::APPROVED->value,
    ]);

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $releaseTeam->id,
        'is_active' => true,
    ]);

    ReleaseTask::factory()->create([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
    ]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::IN_PROGRESS, $releaseTeam);
    RfcStatusTransition::transitionTo($rfc, RfcStatus::IMPLEMENTED, $releaseTeam);
    RfcStatusTransition::transitionTo($rfc, RfcStatus::CLOSED, $cm);

    expect($rfc->fresh()->status)->toBe(RfcStatus::CLOSED->value);
});

test('transisi yang tidak ada di matriks ditolak', function () {
    $owner = userWithRole('Pemohon');
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
    ]);

    expect(fn () => RfcStatusTransition::transitionTo($rfc, RfcStatus::APPROVED, $owner))
        ->toThrow(InvalidRfcStatusTransitionException::class);
});

test('transisi dengan role yang salah ditolak', function () {
    $cm = userWithRole('Change Manager');
    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    expect(fn () => RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $cm))
        ->toThrow(InvalidRfcStatusTransitionException::class);
});

test('failed hanya dapat menuju closed', function () {
    $cm = userWithRole('Change Manager');
    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::FAILED->value,
    ]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::CLOSED, $cm);

    expect($rfc->fresh()->status)->toBe(RfcStatus::CLOSED->value);
});
