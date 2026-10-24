<?php

use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function foundationUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function foundationRfc(RfcStatus $status, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'status' => $status->value,
    ], $attributes));
}

function foundationImplementation(ChangeRequest $rfc, array $attributes = []): ChangeRequestImplementation
{
    return ChangeRequestImplementation::create(array_merge([
        'change_request_id' => $rfc->id,
        'recorded_by_id' => User::factory()->create()->id,
        'implementation_result' => ImplementationResult::SUCCESS->value,
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'executor_name' => 'Tim Rilis Diskominfostandi',
        'summary' => 'Perubahan diterapkan pada lingkungan produksi.',
        'test_results' => 'Seluruh pengujian fungsional dan regresi berhasil.',
    ], $attributes));
}

it('membuat tabel implementasi dan kolom penutupan dengan unique RFC', function () {
    expect(Schema::hasTable('change_request_implementations'))->toBeTrue()
        ->and(Schema::hasColumns('change_request_implementations', [
            'id',
            'change_request_id',
            'recorded_by_id',
            'implementation_result',
            'started_at',
            'finished_at',
            'executor_name',
            'report_source',
            'summary',
            'test_results',
            'failure_notes',
            'follow_up_action',
            'follow_up_notes',
            'evaluation_notes',
            'evaluated_by_id',
            'evaluated_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('change_requests', [
            'closed_at',
            'closed_by_id',
            'closure_notes',
        ]))->toBeTrue();

    $rfc = foundationRfc(RfcStatus::IMPLEMENTED);

    foundationImplementation($rfc);

    expect(fn () => foundationImplementation($rfc))->toThrow(QueryException::class);
});

it('menghubungkan implementasi dengan RFC dan pengguna terkait', function () {
    $recorder = User::factory()->create();
    $evaluator = User::factory()->create();
    $closer = User::factory()->create();

    $rfc = foundationRfc(RfcStatus::IMPLEMENTED, [
        'closed_at' => now(),
        'closed_by_id' => $closer->id,
        'closure_notes' => 'RFC ditutup setelah evaluasi.',
    ]);

    $implementation = foundationImplementation($rfc, [
        'recorded_by_id' => $recorder->id,
        'evaluated_by_id' => $evaluator->id,
        'evaluated_at' => now(),
    ]);

    $freshRfc = $rfc->fresh();

    expect($freshRfc->implementation)
        ->toBeInstanceOf(ChangeRequestImplementation::class)
        ->and($freshRfc->implementation->is($implementation))->toBeTrue()
        ->and($implementation->changeRequest->is($rfc))->toBeTrue()
        ->and($implementation->recordedBy->is($recorder))->toBeTrue()
        ->and($implementation->evaluatedBy->is($evaluator))->toBeTrue()
        ->and($freshRfc->closedBy->is($closer))->toBeTrue()
        ->and($freshRfc->closure_notes)->toBe('RFC ditutup setelah evaluasi.')
        ->and($implementation->started_at)->toBeInstanceOf(Carbon::class)
        ->and($implementation->finished_at)->toBeInstanceOf(Carbon::class)
        ->and($implementation->evaluated_at)->toBeInstanceOf(Carbon::class);
});

it('menyediakan label Indonesia untuk hasil implementasi dan tindak lanjut', function () {
    expect(ImplementationResult::labelFor('success'))->toBe('Berhasil')
        ->and(ImplementationResult::labelFor('failed'))->toBe('Gagal')
        ->and(ImplementationResult::labelFor('rolled_back'))->toBe('Rollback dilakukan')
        ->and(FollowUpAction::labelFor('none'))->toBe('Tidak ada tindak lanjut')
        ->and(FollowUpAction::labelFor('new_rfc'))->toBe('Ajukan RFC baru')
        ->and(FollowUpAction::labelFor('reschedule'))->toBe('Jadwalkan ulang')
        ->and(FollowUpAction::labelFor('escalation'))->toBe('Eskalasi atau investigasi');
});

it('mengizinkan implement hanya untuk change manager pada approved dan in_progress', function () {
    $cm = foundationUser('Change Manager');
    $pemohon = foundationUser('Pemohon');
    $admin = foundationUser('Admin');

    foreach ([RfcStatus::APPROVED, RfcStatus::IN_PROGRESS] as $status) {
        $rfc = foundationRfc($status);

        expect($cm->can('implement', $rfc))->toBeTrue()
            ->and($pemohon->can('implement', $rfc))->toBeFalse()
            ->and($admin->can('implement', $rfc))->toBeFalse();
    }

    $otherStatuses = [
        RfcStatus::DRAFT,
        RfcStatus::SUBMITTED,
        RfcStatus::NEEDS_REVISION,
        RfcStatus::TICKET_CREATED,
        RfcStatus::RETURNED_TO_ADMIN,
        RfcStatus::IMPLEMENTED,
        RfcStatus::FAILED,
        RfcStatus::CLOSED,
        RfcStatus::REJECTED,
        RfcStatus::CANCELLED,
    ];

    foreach ($otherStatuses as $status) {
        expect($cm->can('implement', foundationRfc($status)))->toBeFalse();
    }
});

it('mengizinkan evaluasi hanya setelah implementasi ada dan belum dievaluasi', function () {
    $cm = foundationUser('Change Manager');
    $pemohon = foundationUser('Pemohon');
    $admin = foundationUser('Admin');

    foreach ([RfcStatus::IMPLEMENTED, RfcStatus::FAILED] as $status) {
        $rfc = foundationRfc($status);
        foundationImplementation($rfc);

        expect($cm->can('evaluate', $rfc))->toBeTrue()
            ->and($pemohon->can('evaluate', $rfc))->toBeFalse()
            ->and($admin->can('evaluate', $rfc))->toBeFalse();
    }

    $evaluated = foundationRfc(RfcStatus::IMPLEMENTED);
    foundationImplementation($evaluated, [
        'evaluated_by_id' => $cm->id,
        'evaluated_at' => now(),
    ]);

    $withoutImplementation = foundationRfc(RfcStatus::IMPLEMENTED);

    expect($cm->can('evaluate', $evaluated))->toBeFalse()
        ->and($cm->can('evaluate', $withoutImplementation))->toBeFalse()
        ->and($cm->can('evaluate', foundationRfc(RfcStatus::APPROVED)))->toBeFalse()
        ->and($cm->can('evaluate', foundationRfc(RfcStatus::CLOSED)))->toBeFalse();
});

it('mengizinkan penutupan hanya setelah implementasi dievaluasi', function () {
    $cm = foundationUser('Change Manager');
    $pemohon = foundationUser('Pemohon');
    $admin = foundationUser('Admin');

    foreach ([RfcStatus::IMPLEMENTED, RfcStatus::FAILED] as $status) {
        $rfc = foundationRfc($status);
        foundationImplementation($rfc, [
            'evaluated_by_id' => $cm->id,
            'evaluated_at' => now(),
        ]);

        expect($cm->can('close', $rfc))->toBeTrue()
            ->and($pemohon->can('close', $rfc))->toBeFalse()
            ->and($admin->can('close', $rfc))->toBeFalse();
    }

    $notEvaluated = foundationRfc(RfcStatus::IMPLEMENTED);
    foundationImplementation($notEvaluated);

    $withoutImplementation = foundationRfc(RfcStatus::IMPLEMENTED);

    expect($cm->can('close', $notEvaluated))->toBeFalse()
        ->and($cm->can('close', $withoutImplementation))->toBeFalse()
        ->and($cm->can('close', foundationRfc(RfcStatus::APPROVED)))->toBeFalse()
        ->and($cm->can('close', foundationRfc(RfcStatus::CLOSED)))->toBeFalse();
});

it('mengizinkan viewEvidence untuk staf yang dapat melihat semua RFC', function () {
    $cm = foundationUser('Change Manager');
    $admin = foundationUser('Admin');
    $pemohon = foundationUser('Pemohon');

    foreach ([RfcStatus::APPROVED, RfcStatus::IMPLEMENTED, RfcStatus::CLOSED] as $status) {
        $rfc = foundationRfc($status, [
            'submitted_by_id' => $pemohon->id,
        ]);

        expect($cm->can('viewEvidence', $rfc))->toBeTrue()
            ->and($admin->can('viewEvidence', $rfc))->toBeTrue()
            ->and($pemohon->can('viewEvidence', $rfc))->toBeFalse();
    }
});

it('menolak seluruh ability implementasi, evaluasi, penutupan, dan bukti untuk RFC terhapus', function () {
    $cm = foundationUser('Change Manager');
    $admin = foundationUser('Admin');
    $pemohon = foundationUser('Pemohon');

    $evaluated = foundationRfc(RfcStatus::IMPLEMENTED);
    foundationImplementation($evaluated, [
        'evaluated_by_id' => $cm->id,
        'evaluated_at' => now(),
    ]);
    $evaluated->delete();

    $notEvaluated = foundationRfc(RfcStatus::IMPLEMENTED);
    foundationImplementation($notEvaluated);
    $notEvaluated->delete();

    foreach ([$evaluated, $notEvaluated] as $deletedRfc) {
        $trashed = ChangeRequest::withTrashed()->findOrFail($deletedRfc->id);

        expect($cm->can('implement', $trashed))->toBeFalse()
            ->and($cm->can('evaluate', $trashed))->toBeFalse()
            ->and($cm->can('close', $trashed))->toBeFalse()
            ->and($cm->can('viewEvidence', $trashed))->toBeFalse()
            ->and($admin->can('implement', $trashed))->toBeFalse()
            ->and($admin->can('evaluate', $trashed))->toBeFalse()
            ->and($admin->can('close', $trashed))->toBeFalse()
            ->and($admin->can('viewEvidence', $trashed))->toBeFalse()
            ->and($pemohon->can('implement', $trashed))->toBeFalse()
            ->and($pemohon->can('evaluate', $trashed))->toBeFalse()
            ->and($pemohon->can('close', $trashed))->toBeFalse()
            ->and($pemohon->can('viewEvidence', $trashed))->toBeFalse();
    }
});
