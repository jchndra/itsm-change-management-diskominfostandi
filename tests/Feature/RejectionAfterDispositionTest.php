<?php

use App\Enums\DecisionStatus;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Exceptions\RfcRejectionException;
use App\Livewire\ChangeRequests\RejectRfc;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Decision;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcHistoryService;
use App\Services\RfcRejectionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function rejectionUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function rejectionReason(): string
{
    return 'Umpan balik pengujian dan analisis dampak tidak memadai sehingga perubahan ini tidak aman untuk diterapkan.';
}

function rejectionRfc(RfcStatus $status, ?User $owner = null): ChangeRequest
{
    $attributes = ['status' => $status->value];

    if ($owner !== null) {
        $attributes['submitted_by_id'] = $owner->id;
        $attributes['created_by_id'] = $owner->id;
    }

    return ChangeRequest::factory()->create($attributes);
}

function rejectionReleaseTeamActor(ChangeRequest $rfc, array $taskAttributes = []): User
{
    $user = rejectionUser('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    ReleaseTask::factory()->create(array_merge([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'in_progress',
        'estimated_days' => 5,
    ], $taskAttributes));

    return $user;
}

function rejectionImplementationRow(ChangeRequest $rfc, User $actor, string $result = ImplementationResult::SUCCESS->value): ChangeRequestImplementation
{
    return ChangeRequestImplementation::create([
        'change_request_id' => $rfc->id,
        'recorded_by_id' => $actor->id,
        'implementation_result' => $result,
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'executor_name' => 'Tim Rilis Diskominfostandi',
        'report_source' => 'Laporan tertulis Tim Rilis',
        'summary' => 'Perubahan diterapkan pada lingkungan produksi.',
        'test_results' => 'Pengujian fungsional berhasil.',
        'failure_notes' => $result === ImplementationResult::SUCCESS->value ? null : 'Perubahan gagal diterapkan dan layanan dipulihkan.',
        'follow_up_action' => $result === ImplementationResult::SUCCESS->value ? null : 'new_rfc',
        'follow_up_notes' => $result === ImplementationResult::SUCCESS->value ? null : 'Akan diajukan RFC baru setelah analisis akar masalah.',
    ]);
}

test('Change Manager dapat menolak RFC yang sedang dikerjakan dan tugas Tim Rilis dibatalkan', function () {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $requester);
    $team = rejectionReleaseTeamActor($rfc);

    $this->actingAs($cm)
        ->get(route('change-requests.reject', $rfc))
        ->assertOk()
        ->assertSee('Tolak RFC');

    Livewire::actingAs($cm)
        ->test(RejectRfc::class, ['rfc' => $rfc])
        ->set('reason', rejectionReason())
        ->set('confirmed', true)
        ->call('reject')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc->id));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::REJECTED->value)
        ->and($fresh->rejection_note)->toBe(rejectionReason())
        ->and($team->fresh()->is_active)->toBeTrue();

    expect($fresh->releaseTaskFor($team)->execution_status)->toBe('cancelled');

    $decision = Decision::where('change_request_id', $rfc->id)
        ->where('decision_status', DecisionStatus::REJECTED->value)
        ->sole();

    expect($decision->recorded_by_id)->toBe($cm->id)
        ->and($decision->reason)->toBe(rejectionReason())
        ->and($decision->snapshot_authority_name)->toBe($cm->name);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::REJECTED->value);

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($cm->id)
        ->and(data_get($activity->properties, 'event'))->toBe('rfc_rejected_after_disposition')
        ->and(data_get($activity->properties, 'rejection_stage'))->toBe(RfcStatus::IN_PROGRESS->value)
        ->and(data_get($activity->properties, 'cancelled_task_count'))->toBe(1);

    expect($team->notifications()->get()->contains(
        fn ($notification): bool => data_get($notification->data, 'status') === RfcStatus::REJECTED->value
    ))->toBeTrue();
});

test('Change Manager dapat menolak RFC yang baru disetujui dan tugas yang menunggu dibatalkan', function () {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::APPROVED, $requester);
    $team = rejectionReleaseTeamActor($rfc, ['execution_status' => 'pending']);

    app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason());

    expect($rfc->fresh()->status)->toBe(RfcStatus::REJECTED->value)
        ->and($rfc->fresh()->releaseTaskFor($team)->execution_status)->toBe('cancelled');
});

test('Change Manager dapat menolak RFC implemented dan failed', function (RfcStatus $status) {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc($status, $requester);
    rejectionImplementationRow(
        $rfc,
        $cm,
        $status === RfcStatus::FAILED ? ImplementationResult::FAILED->value : ImplementationResult::SUCCESS->value,
    );

    app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason());

    expect($rfc->fresh()->status)->toBe(RfcStatus::REJECTED->value);

    $decision = Decision::where('change_request_id', $rfc->id)
        ->where('decision_status', DecisionStatus::REJECTED->value)
        ->sole();

    expect($decision->reason)->toBe(rejectionReason());
})->with([RfcStatus::IMPLEMENTED, RfcStatus::FAILED]);

test('penolakan tidak mengubah tugas yang sudah selesai', function (string $executionStatus) {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IMPLEMENTED, $requester);
    $team = rejectionReleaseTeamActor($rfc, [
        'execution_status' => $executionStatus,
        'executed_at' => now()->subHour(),
    ]);

    rejectionImplementationRow(
        $rfc,
        $cm,
        $executionStatus === 'successful' ? ImplementationResult::SUCCESS->value : ImplementationResult::FAILED->value,
    );

    app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason());

    expect($rfc->fresh()->releaseTaskFor($team)->execution_status)->toBe($executionStatus);
})->with(['successful', 'rolled_back']);

test('Change Manager tidak dapat menolak RFC sebelum didisposisikan atau pada status akhir', function (RfcStatus $status) {
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc($status);

    $this->actingAs($cm)
        ->get(route('change-requests.reject', $rfc))
        ->assertForbidden();

    expect(fn () => app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason()))
        ->toThrow(RfcRejectionException::class);
})->with([
    RfcStatus::DRAFT,
    RfcStatus::SUBMITTED,
    RfcStatus::TICKET_CREATED,
    RfcStatus::NEEDS_REVISION,
    RfcStatus::RETURNED_TO_ADMIN,
    RfcStatus::REJECTED,
    RfcStatus::CLOSED,
    RfcStatus::CANCELLED,
]);

test('Pemohon, Admin, dan Tim Rilis tidak dapat menolak RFC', function (string $role) {
    $requester = rejectionUser('Pemohon');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $requester);
    rejectionReleaseTeamActor($rfc);
    $actor = rejectionUser($role);

    $this->actingAs($actor)
        ->get(route('change-requests.reject', $rfc))
        ->assertForbidden();

    expect($actor->can('reject', $rfc))->toBeFalse();
})->with(['Pemohon', 'Admin', 'Tim Rilis']);

test('Change Manager selain pemilik dapat menolak RFC yang dimiliki Change Manager lain', function () {
    $owner = rejectionUser('Change Manager');
    $otherCm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $owner);

    expect($otherCm->can('reject', $rfc))->toBeTrue();
});

test('Change Manager pemilik RFC tidak dapat menolak RFC miliknya sendiri', function () {
    $owner = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $owner);

    expect($owner->can('reject', $rfc))->toBeFalse();
});

test('penolakan menolak alasan yang terlalu pendek atau tanpa konfirmasi', function () {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $requester);

    Livewire::actingAs($cm)
        ->test(RejectRfc::class, ['rfc' => $rfc])
        ->set('reason', 'terlalu pendek')
        ->set('confirmed', false)
        ->call('reject')
        ->assertHasErrors(['reason', 'confirmed']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::IN_PROGRESS->value)
        ->and(Decision::where('change_request_id', $rfc->id)->count())->toBe(0);
});

test('RFC yang ditolak adalah final: implementasi, evaluasi, dan penutupan tidak lagi tersedia', function () {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IMPLEMENTED, $requester);
    rejectionImplementationRow($rfc, $cm);

    app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason());

    $this->actingAs($cm)
        ->get(route('change-requests.implement', $rfc))
        ->assertForbidden();

    $this->actingAs($cm)
        ->get(route('change-requests.evaluate', $rfc))
        ->assertForbidden();

    $this->actingAs($cm)
        ->get(route('change-requests.close', $rfc))
        ->assertForbidden();
});

test('riwayat mencatat penolakan setelah disposisi dan tidak lagi menyisakan langkah berikutnya', function () {
    $requester = rejectionUser('Pemohon');
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::IN_PROGRESS, $requester);
    rejectionReleaseTeamActor($rfc);

    app(RfcRejectionService::class)->reject($rfc, $cm, rejectionReason());

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);

    $rejectionEvent = collect($history['events'])->firstWhere('key', RfcStatus::REJECTED->value);

    expect($rejectionEvent)->not->toBeNull()
        ->and($rejectionEvent['label'])->toContain('Ditolak oleh Change Manager')
        ->and($history['next_steps'])->toBe([]);
});

test('detail RFC menampilkan aksi Tolak RFC hanya untuk Change Manager yang bukan pemilik', function () {
    $requester = rejectionUser('Pemohon');
    $pemohonOwner = $requester;
    $cm = rejectionUser('Change Manager');
    $rfc = rejectionRfc(RfcStatus::APPROVED, $pemohonOwner);

    Livewire::actingAs($cm)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Tolak RFC');

    Livewire::actingAs($pemohonOwner)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Tolak RFC');
});
