<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Livewire\ChangeRequests\DeletionHistory;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Decision;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcDeletionService;
use App\Services\RfcHistoryService;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function releaseTeamPolicyUser(array $roles = [], array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $attributes));

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

function releaseTeamPolicyMember(User $user, array $attributes = []): ReleaseTeamMember
{
    return ReleaseTeamMember::factory()->create(array_merge([
        'user_id' => $user->id,
        'is_active' => true,
    ], $attributes));
}

function releaseTeamPolicyTask(ChangeRequest $rfc, ReleaseTeamMember $member, array $attributes = []): ReleaseTask
{
    return ReleaseTask::factory()->create(array_merge([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
    ], $attributes));
}

function releaseTeamPolicyImplementation(ChangeRequest $rfc): ChangeRequestImplementation
{
    return ChangeRequestImplementation::create([
        'change_request_id' => $rfc->id,
        'recorded_by_id' => User::factory()->create()->id,
        'implementation_result' => 'success',
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'executor_name' => 'Tim Rilis Pengujian',
        'summary' => 'Implementasi pengujian.',
        'test_results' => 'Seluruh pengujian berhasil.',
    ]);
}

it('hanya menampilkan RFC yang ditugaskan kepada Tim Rilis', function () {
    $user = releaseTeamPolicyUser(['Tim Rilis']);
    $member = releaseTeamPolicyMember($user);

    $otherUser = releaseTeamPolicyUser(['Tim Rilis']);
    $otherMember = releaseTeamPolicyMember($otherUser);

    $assigned = ChangeRequest::factory()->create([
        'title' => 'RFC Ditugaskan Kepada Saya',
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($assigned, $member);

    $otherRfc = ChangeRequest::factory()->create([
        'title' => 'RFC Milik Tim Lain',
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($otherRfc, $otherMember);

    $unassigned = ChangeRequest::factory()->create([
        'title' => 'RFC Tanpa Penugasan',
        'status' => RfcStatus::APPROVED->value,
    ]);

    $this->actingAs($user)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->assertSee($assigned->title)
        ->assertDontSee($otherRfc->title)
        ->assertDontSee($unassigned->title);

    Livewire::actingAs($user)
        ->test(ListChangeRequests::class)
        ->assertOk()
        ->assertViewHas('roleView', 'release_team')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['assigned'] === 1)
        ->assertSee($assigned->title)
        ->assertDontSee($otherRfc->title)
        ->assertDontSee($unassigned->title);

    $detailHtml = $this->actingAs($user)
        ->get(route('change-requests.show', $assigned))
        ->assertOk()
        ->getContent();

    expect($detailHtml)->toMatch(
        '/<a[^>]+href="'.preg_quote(route('change-requests.index'), '/').'"[^>]*class="[^"]*rounded-full/'
    );

    $this->actingAs($user)
        ->get(route('change-requests.show', $otherRfc))
        ->assertForbidden();

    expect($user->can('view', $assigned))->toBeTrue()
        ->and($user->can('view', $otherRfc))->toBeFalse()
        ->and($user->can('view', $unassigned))->toBeFalse()
        ->and($user->can('viewEvidence', $assigned))->toBeTrue()
        ->and($user->can('viewEvidence', $otherRfc))->toBeFalse();
});

it('menyembunyikan nomor tiket, keputusan, disposisi, dan riwayat internal dari Tim Rilis', function () {
    $manager = releaseTeamPolicyUser(['Change Manager'], ['name' => 'Manajer Internal Rahasia']);
    $user = releaseTeamPolicyUser(['Tim Rilis']);
    $member = releaseTeamPolicyMember($user);

    $rfc = ChangeRequest::factory()->create([
        'title' => 'RFC Tugas Dengan Data Internal',
        'status' => RfcStatus::APPROVED->value,
        'ticket_number' => 'CHG-2026-7777',
    ]);

    releaseTeamPolicyTask($rfc, $member, [
        'instruction' => 'Instruksi disposisi rahasia.',
    ]);

    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => null,
        'recorded_by_id' => $manager->id,
        'snapshot_authority_name' => 'Pejabat Internal',
        'snapshot_authority_position' => 'Kepala Bidang Internal',
        'decision_status' => DecisionStatus::APPROVED->value,
        'decision_date' => now(),
        'reason' => 'Alasan keputusan internal rahasia.',
    ]);

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($manager)
        ->withProperties([
            'old_status' => RfcStatus::TICKET_CREATED->value,
            'new_status' => RfcStatus::APPROVED->value,
            'note' => 'Catatan transisi internal rahasia.',
        ])
        ->log('Status RFC disetujui.');

    $this->actingAs($user);

    $this->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->assertSee($rfc->title)
        ->assertDontSee('Nomor Tiket')
        ->assertDontSee('CHG-2026-7777')
        ->assertDontSee('Alasan keputusan internal rahasia.')
        ->assertDontSee('Catatan transisi internal rahasia.')
        ->assertDontSee('Instruksi disposisi rahasia.')
        ->assertDontSee('Manajer Internal Rahasia');

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), false, true);
    $serializedHistory = json_encode($history['events']);

    expect($serializedHistory)
        ->not->toContain('Alasan keputusan internal rahasia.')
        ->not->toContain('Catatan transisi internal rahasia.')
        ->not->toContain('Instruksi disposisi rahasia.')
        ->not->toContain('Manajer Internal Rahasia')
        ->and(collect($history['events'])->pluck('note')->filter()->every(fn ($note): bool => ! str_contains((string) $note, 'rahasia')))
        ->toBeTrue();

    $this->actingAs($manager);

    $this->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->assertSee('CHG-2026-7777')
        ->assertSee('Alasan keputusan internal rahasia.')
        ->assertSee('Manajer Internal Rahasia');
});

it('menolak Tim Rilis membuka arsip penghapusan RFC', function () {
    $user = releaseTeamPolicyUser(['Tim Rilis']);

    $this->actingAs($user)
        ->get(route('change-requests.deletions.index'))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(DeletionHistory::class)
        ->assertForbidden();
});

it('mengizinkan eksekusi hanya untuk anggota aktif yang ditugaskan pada status yang tepat', function () {
    $owner = releaseTeamPolicyUser(['Pemohon']);
    $user = releaseTeamPolicyUser(['Tim Rilis']);
    $member = releaseTeamPolicyMember($user);

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($rfc, $member);

    expect($user->can('recordExecution', $rfc))->toBeTrue();

    $unassigned = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    expect($user->can('recordExecution', $unassigned))->toBeFalse();

    $inProgress = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::IN_PROGRESS->value,
    ]);
    releaseTeamPolicyTask($inProgress, $member, ['execution_status' => 'in_progress']);

    expect($user->can('recordExecution', $inProgress))->toBeTrue();

    $successfulTask = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($successfulTask, $member, ['execution_status' => 'successful']);

    expect($user->can('recordExecution', $successfulTask))->toBeFalse();

    $withImplementation = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($withImplementation, $member);
    releaseTeamPolicyImplementation($withImplementation);

    expect($user->can('recordExecution', $withImplementation))->toBeFalse();

    $ownedByTeam = ChangeRequest::factory()->create([
        'submitted_by_id' => $user->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($ownedByTeam, $member);

    expect($user->can('recordExecution', $ownedByTeam))->toBeFalse();
});

it('menolak eksekusi untuk anggota nonaktif, tugas selesai, status salah, dan RFC terhapus', function () {
    $owner = releaseTeamPolicyUser(['Pemohon']);
    $user = releaseTeamPolicyUser(['Tim Rilis']);
    $member = releaseTeamPolicyMember($user);

    $inactiveRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($inactiveRfc, $member);
    $member->update(['is_active' => false]);

    expect($user->can('recordExecution', $inactiveRfc))->toBeFalse();

    $member->update(['is_active' => true]);

    $rolledBack = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($rolledBack, $member, ['execution_status' => 'rolled_back']);

    expect($user->can('recordExecution', $rolledBack))->toBeFalse();

    foreach ([RfcStatus::DRAFT, RfcStatus::SUBMITTED, RfcStatus::TICKET_CREATED, RfcStatus::IMPLEMENTED, RfcStatus::FAILED, RfcStatus::CLOSED] as $status) {
        $rfc = ChangeRequest::factory()->create([
            'submitted_by_id' => $owner->id,
            'status' => $status->value,
        ]);
        releaseTeamPolicyTask($rfc, $member);

        expect($user->can('recordExecution', $rfc))->toBeFalse();
    }

    $trashed = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);
    releaseTeamPolicyTask($trashed, $member);
    $trashed->delete();

    expect($user->can('recordExecution', $trashed))->toBeFalse();
});

it('menolak eksekusi oleh Super Admin tanpa penugasan Tim Rilis', function () {
    $owner = releaseTeamPolicyUser(['Pemohon']);
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    expect($superAdmin->hasPermissionTo('rfc.record_execution'))->toBeTrue()
        ->and($superAdmin->can('recordExecution', $rfc))->toBeFalse();
});

it('membatasi penugasan rilis hanya untuk Change Manager pada RFC yang disetujui', function () {
    $approved = ChangeRequest::factory()->create(['status' => RfcStatus::APPROVED->value]);

    $changeManager = releaseTeamPolicyUser(['Change Manager']);
    $admin = releaseTeamPolicyUser(['Admin']);
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);
    $releaseTeam = releaseTeamPolicyUser(['Tim Rilis']);
    $pemohon = releaseTeamPolicyUser(['Pemohon']);

    expect($changeManager->can('assignRelease', $approved))->toBeTrue()
        ->and($admin->can('assignRelease', $approved))->toBeFalse()
        ->and($superAdmin->can('assignRelease', $approved))->toBeFalse()
        ->and($releaseTeam->can('assignRelease', $approved))->toBeFalse()
        ->and($pemohon->can('assignRelease', $approved))->toBeFalse();

    foreach ([RfcStatus::DRAFT, RfcStatus::SUBMITTED, RfcStatus::TICKET_CREATED, RfcStatus::IN_PROGRESS, RfcStatus::IMPLEMENTED, RfcStatus::CLOSED] as $status) {
        $rfc = ChangeRequest::factory()->create(['status' => $status->value]);

        expect($changeManager->can('assignRelease', $rfc))->toBeFalse();
    }

    $trashed = ChangeRequest::factory()->create(['status' => RfcStatus::APPROVED->value]);
    $trashed->delete();

    expect($changeManager->can('assignRelease', $trashed))->toBeFalse();
});

it('menegakkan pemisahan tugas pada penilaian risiko tanpa pengecualian pemilik', function () {
    $changeManager = releaseTeamPolicyUser(['Change Manager']);
    $otherChangeManager = releaseTeamPolicyUser(['Change Manager']);
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);

    $ownedByChangeManager = ChangeRequest::factory()->create([
        'submitted_by_id' => $changeManager->id,
    ]);
    $ownedBySuperAdmin = ChangeRequest::factory()->create([
        'submitted_by_id' => $superAdmin->id,
    ]);

    expect($changeManager->can('assessRisk', $ownedByChangeManager))->toBeFalse()
        ->and($otherChangeManager->can('assessRisk', $ownedByChangeManager))->toBeTrue()
        ->and($superAdmin->can('assessRisk', $ownedByChangeManager))->toBeTrue()
        ->and($superAdmin->can('assessRisk', $ownedBySuperAdmin))->toBeFalse();
});

it('tidak membiarkan Super Admin melewati aturan hapus dan transisi status', function () {
    $owner = releaseTeamPolicyUser(['Pemohon']);
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);

    $draft = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
    ]);

    expect($superAdmin->can('delete', $draft))->toBeFalse()
        ->and($superAdmin->can('update', $draft))->toBeFalse()
        ->and(fn () => RfcDeletionService::delete($draft, $superAdmin))
        ->toThrow(RuntimeException::class);

    $ticketCreated = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    expect(fn () => RfcStatusTransition::transitionTo(
        $ticketCreated,
        RfcStatus::APPROVED,
        $superAdmin,
        'Mencoba melewati aturan Change Manager.',
    ))->toThrow(InvalidRfcStatusTransitionException::class);

    expect($ticketCreated->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value);
});

it('mempertahankan fungsi baca dan pemulihan Super Admin', function () {
    $owner = releaseTeamPolicyUser(['Pemohon']);
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);

    $draft = ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'status' => RfcStatus::DRAFT->value,
    ]);

    RfcDeletionService::delete($draft, $owner, 'Dihapus untuk pengujian pemulihan.');

    $trashed = ChangeRequest::withTrashed()->findOrFail($draft->id);

    expect($superAdmin->can('view', $trashed))->toBeTrue()
        ->and($superAdmin->can('restore', $trashed))->toBeTrue();

    RfcDeletionService::restore($trashed, $superAdmin);

    $this->assertNotSoftDeleted('change_requests', ['id' => $draft->id]);

    expect($draft->fresh()->status)->toBe(RfcStatus::DRAFT->value);
});

it('membatasi pengelolaan akun Tim Rilis sesuai status anggota', function () {
    $superAdmin = releaseTeamPolicyUser(['Super Admin']);
    $changeManager = releaseTeamPolicyUser(['Change Manager']);

    $linkableMember = ReleaseTeamMember::factory()->create([
        'user_id' => null,
        'is_active' => true,
    ]);
    $inactiveMember = ReleaseTeamMember::factory()->create([
        'user_id' => null,
        'is_active' => false,
    ]);

    $linkedTarget = releaseTeamPolicyUser(['Tim Rilis']);
    $linkedMember = releaseTeamPolicyMember($linkedTarget);

    expect($superAdmin->can('viewAny', ReleaseTeamMember::class))->toBeTrue()
        ->and($superAdmin->can('manage', ReleaseTeamMember::class))->toBeTrue()
        ->and($superAdmin->can('link', $linkableMember))->toBeTrue()
        ->and($superAdmin->can('link', $inactiveMember))->toBeFalse()
        ->and($superAdmin->can('link', $linkedMember))->toBeFalse()
        ->and($changeManager->can('viewAny', ReleaseTeamMember::class))->toBeFalse();

    expect($linkedMember->isEligibleForAssignment())->toBeTrue()
        ->and($linkableMember->isEligibleForAssignment())->toBeFalse()
        ->and($inactiveMember->isEligibleForAssignment())->toBeFalse();

    $withoutRole = releaseTeamPolicyUser();
    $withoutRoleMember = releaseTeamPolicyMember($withoutRole);
    expect($withoutRoleMember->isEligibleForAssignment())->toBeFalse();
});
