<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcDispositionException;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\RfcChangeManagerReviewService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function cmReviewUser(array $roles = [], array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $attributes));

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmReviewMember(User $user, array $attributes = []): ReleaseTeamMember
{
    return ReleaseTeamMember::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $user->name,
        'is_active' => true,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmReviewRfc(User $requester, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ], $attributes));
}

/**
 * @return array<string, string>
 */
function cmReviewAssessment(): array
{
    return [
        'risk_level' => 'Sedang',
        'impact_level' => 'Sedang',
        'urgency_level' => 'Normal',
        'classification' => 'Normal Change',
        'analysis_notes' => 'Rencana mitigasi penggantian server cadangan dan pengujian pada staging.',
    ];
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('menyetujui dan mendisposisikan RFC dalam satu operasi atomik', function () {
    $manager = cmReviewUser(['Change Manager'], [
        'name' => 'CM Atomik',
        'nip' => '198501012015011002',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $requester = cmReviewUser(['Pemohon']);
    $member = cmReviewMember(cmReviewUser(['Tim Rilis'], ['name' => 'Anggota Rilis Atomik']));
    $rfc = cmReviewRfc($requester);
    $instruction = 'Laksanakan perubahan pada jendela pemeliharaan malam ini.';

    app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::APPROVED->value,
        'Disetujui berdasarkan hasil evaluasi teknis.',
        $member->id,
        $instruction,
    );

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::APPROVED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and($fresh->decisions()->count())->toBe(1)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(1);

    $this->assertDatabaseHas('release_tasks', [
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'assigned_by_id' => $manager->id,
        'instruction' => $instruction,
        'execution_status' => 'pending',
    ]);
});

it('menggulung seluruh perubahan ketika persetujuan tanpa penerima atau instruksi', function () {
    $manager = cmReviewUser(['Change Manager']);
    $requester = cmReviewUser(['Pemohon']);
    $member = cmReviewMember(cmReviewUser(['Tim Rilis']));
    $rfc = cmReviewRfc($requester);

    expect(fn () => app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::APPROVED->value,
        'Disetujui tanpa penerima disposisi.',
        null,
        null,
    ))->toThrow(RfcDispositionException::class);

    expect(fn () => app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::APPROVED->value,
        'Disetujui tanpa instruksi disposisi.',
        $member->id,
        '',
    ))->toThrow(RfcDispositionException::class);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(0)
        ->and($fresh->decisions()->count())->toBe(0)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(0);
});

it('menolak atau meminta revisi tanpa membuat tugas rilis tetapi tetap menyimpan analisis risiko', function (DecisionStatus $decisionStatus, RfcStatus $targetStatus) {
    $manager = cmReviewUser(['Change Manager']);
    $requester = cmReviewUser(['Pemohon']);
    $rfc = cmReviewRfc($requester);
    $notes = 'Catatan keputusan '.$decisionStatus->value;

    app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        $decisionStatus->value,
        $notes,
        null,
        null,
    );

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe($targetStatus->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and($fresh->decisions()->firstOrFail()->decision_status)->toBe($decisionStatus->value)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(0);

    if ($decisionStatus === DecisionStatus::REVISION_REQUIRED) {
        expect($fresh->rejection_note)->toBe($notes);
    }
})->with([
    'rejected' => [DecisionStatus::REJECTED, RfcStatus::REJECTED],
    'revision required' => [DecisionStatus::REVISION_REQUIRED, RfcStatus::NEEDS_REVISION],
]);

it('menolak change manager yang mengajukan RFC sendiri', function () {
    $manager = cmReviewUser(['Change Manager']);
    $rfc = cmReviewRfc($manager);

    expect(fn () => app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::REJECTED->value,
        'Catatan penolakan oleh pengaju.',
        null,
        null,
    ))->toThrow(RfcDispositionException::class);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(0)
        ->and($fresh->decisions()->count())->toBe(0);
});

it('menolak ketika status RFC bukan sedang ditinjau', function () {
    $manager = cmReviewUser(['Change Manager']);
    $requester = cmReviewUser(['Pemohon']);
    $rfc = cmReviewRfc($requester, ['status' => RfcStatus::APPROVED->value]);

    expect(fn () => app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::REJECTED->value,
        'Catatan penolakan pada status yang salah.',
        null,
        null,
    ))->toThrow(InvalidRfcStatusTransitionException::class);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::APPROVED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(0)
        ->and($fresh->decisions()->count())->toBe(0);
});

it('menggulung seluruh perubahan ketika penerima disposisi tidak layak', function () {
    $manager = cmReviewUser(['Change Manager']);
    $requester = cmReviewUser(['Pemohon']);
    $ineligibleMember = cmReviewMember(cmReviewUser(['Pemohon'], ['name' => 'Bukan Tim Rilis']));
    $rfc = cmReviewRfc($requester);

    expect(fn () => app(RfcChangeManagerReviewService::class)->submit(
        $rfc,
        $manager,
        cmReviewAssessment(),
        DecisionStatus::APPROVED->value,
        'Disetujui dengan penerima yang tidak layak.',
        $ineligibleMember->id,
        'Instruksi disposisi untuk penerima tidak layak.',
    ))->toThrow(RfcDispositionException::class);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(0)
        ->and($fresh->decisions()->count())->toBe(0)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(0);
});
