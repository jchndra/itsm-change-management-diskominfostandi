<?php

use App\Enums\RfcStatus;
use App\Exceptions\ReleaseTaskProgressException;
use App\Livewire\ChangeRequests\ImplementChange;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Notifications\RfcReleaseProgressNotification;
use App\Services\ReleaseTaskProgressService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: ChangeRequest, 2: ReleaseTask, 3: ReleaseTeamMember}
 */
function releaseTaskProgressSetup(array $taskAttributes = [], array $rfcAttributes = []): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $rfc = ChangeRequest::factory()->create(array_merge([
        'status' => RfcStatus::APPROVED->value,
    ], $rfcAttributes));

    $task = ReleaseTask::factory()->create(array_merge([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
        'estimated_days' => null,
        'scheduled_start' => null,
        'scheduled_end' => null,
    ], $taskAttributes));

    return [$user, $rfc, $task, $member];
}

it('menetapkan estimasi hari pada tugas Tim Rilis', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    app(ReleaseTaskProgressService::class)->setEstimate($task, $user, 5);

    expect($task->fresh()->estimated_days)->toBe(5);

    $this->assertDatabaseHas('release_task_updates', [
        'release_task_id' => $task->id,
        'change_request_id' => $rfc->id,
        'user_id' => $user->id,
        'type' => 'estimate_set',
        'old_estimated_days' => null,
        'new_estimated_days' => 5,
    ]);
});

it('menolak estimasi di luar rentang 1 sampai 90 hari', function (int $days) {
    [$user, , $task] = releaseTaskProgressSetup();

    expect(fn () => app(ReleaseTaskProgressService::class)->setEstimate($task, $user, $days))
        ->toThrow(ReleaseTaskProgressException::class);
})->with([0, 91]);

it('menolak revisi estimasi tanpa alasan', function () {
    [$user, , $task] = releaseTaskProgressSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 5);

    expect(fn () => $service->reviseEstimate($task, $user, 8, ''))
        ->toThrow(ReleaseTaskProgressException::class);

    expect($task->fresh()->estimated_days)->toBe(5);
});

it('menyimpan revisi estimasi beserta nilai lama dan baru', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 5);
    $service->reviseEstimate($task, $user, 8, 'Tambahan pengujian integrasi antarsistem.');

    expect($task->fresh()->estimated_days)->toBe(8);

    $this->assertDatabaseHas('release_task_updates', [
        'release_task_id' => $task->id,
        'change_request_id' => $rfc->id,
        'user_id' => $user->id,
        'type' => 'estimate_revised',
        'old_estimated_days' => 5,
        'new_estimated_days' => 8,
        'reason' => 'Tambahan pengujian integrasi antarsistem.',
    ]);
});

it('memulai pengerjaan dan menghitung selesai dari estimasi', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 7, 9, 0, 0, 'Asia/Jakarta'));

    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 5);
    $service->start($task, $user);

    $fresh = $task->fresh();

    expect($fresh->execution_status)->toBe('in_progress')
        ->and($rfc->fresh()->status)->toBe(RfcStatus::IN_PROGRESS->value)
        ->and($fresh->scheduled_start?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($fresh->scheduled_end?->toDateTimeString())->toBe(now()->addDays(5)->toDateTimeString());

    $this->assertDatabaseHas('release_task_updates', [
        'release_task_id' => $task->id,
        'user_id' => $user->id,
        'type' => 'started',
    ]);
});

it('menampilkan progres read-only kepada pemohon pemilik tanpa instruksi internal', function () {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    [$user, $rfc, $task, $member] = releaseTaskProgressSetup([], [
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ]);

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 5);
    $service->start($task, $user);
    $service->reviseEstimate($task, $user, 8, 'Tambahan pengujian integrasi antarsistem.');

    Livewire::actingAs($pemohon)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Progres Tim Rilis')
        ->assertSee($member->name)
        ->assertSee('Estimasi: 8 hari')
        ->assertSee('Estimasi direvisi')
        ->assertSee('5 hari')
        ->assertSee('8 hari')
        ->assertSee('Tambahan pengujian integrasi antarsistem.')
        ->assertDontSee($task->instruction);
});

it('menolak pemohon lain melihat progres RFC yang bukan miliknya', function () {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    [, $rfc] = releaseTaskProgressSetup([], [
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ]);

    $otherRequester = User::factory()->create();
    $otherRequester->assignRole('Pemohon');

    $this->actingAs($otherRequester)
        ->get(route('change-requests.show', ['rfc' => $rfc->id]))
        ->assertForbidden();
});

it('mengirim notifikasi revisi estimasi ke pemohon dan change manager aktif', function () {
    Notification::fake();

    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $changeManager = User::factory()->create(['is_active' => true]);
    $changeManager->assignRole('Change Manager');

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('Admin');

    [$user, $rfc, $task] = releaseTaskProgressSetup([], [
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ]);

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 5);
    $service->reviseEstimate($task, $user, 8, 'Tambahan pengujian integrasi antarsistem.');

    $assertRevisionNotification = fn ($notification): bool => $notification->update->type === 'estimate_revised'
        && $notification->update->old_estimated_days === 5
        && $notification->update->new_estimated_days === 8;

    Notification::assertSentTo($pemohon, RfcReleaseProgressNotification::class, $assertRevisionNotification);
    Notification::assertSentTo($changeManager, RfcReleaseProgressNotification::class, $assertRevisionNotification);
    Notification::assertNotSentTo($admin, RfcReleaseProgressNotification::class);
});

it('menampilkan badge melewati estimasi saat target selesai terlampaui', function () {
    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    [$user, $rfc, $task] = releaseTaskProgressSetup([], [
        'submitted_by_id' => $pemohon->id,
        'created_by_id' => $pemohon->id,
    ]);

    Carbon::setTestNow(Carbon::create(2026, 11, 7, 9, 0, 0, 'Asia/Jakarta'));

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 3);
    $service->start($task, $user);

    Carbon::setTestNow(Carbon::create(2026, 11, 20, 9, 0, 0, 'Asia/Jakarta'));

    Livewire::actingAs($pemohon)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Progres Tim Rilis')
        ->assertSee('Melewati estimasi');
});

it('menolak aktor yang bukan pemilik tugas', function (string $actorType) {
    [$owner, $rfc, $task] = releaseTaskProgressSetup();

    $actor = match ($actorType) {
        'anggota lain' => tap(
            User::factory()->create(['is_active' => true]),
            function (User $user): void {
                $user->assignRole('Tim Rilis');
                ReleaseTeamMember::factory()->create([
                    'user_id' => $user->id,
                    'is_active' => true,
                ]);
            },
        ),
        'pemohon' => $rfc->submittedBy,
        'change manager' => tap(
            User::factory()->create(['is_active' => true]),
            fn (User $user) => $user->assignRole('Change Manager'),
        ),
    };

    expect(fn () => app(ReleaseTaskProgressService::class)->setEstimate($task, $actor, 5))
        ->toThrow(ReleaseTaskProgressException::class);

    expect($task->fresh()->estimated_days)->toBeNull()
        ->and($owner)->not->toBeNull();
})->with(['anggota lain', 'pemohon', 'change manager']);

it('menampilkan tombol selesaikan pekerjaan dan rollback untuk tugas in progress milik Tim Rilis', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 3);
    $service->start($task, $user);

    Livewire::actingAs($user)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Selesaikan Pekerjaan')
        ->assertSee('Isi laporan hasil. Setelah dikirim, RFC masuk review Change Manager.')
        ->assertSee('Wajib menuliskan alasan rollback.')
        ->assertSee('hasil=success')
        ->assertSee('hasil=rolled_back');
});

it('tidak menampilkan aksi penyelesaian saat tugas masih menunggu dimulai', function () {
    [$user, $rfc] = releaseTaskProgressSetup();

    Livewire::actingAs($user)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Mulai Pengerjaan')
        ->assertDontSee('Isi laporan hasil. Setelah dikirim, RFC masuk review Change Manager.')
        ->assertDontSee('Wajib menuliskan alasan rollback.')
        ->assertDontSee('hasil=rolled_back');
});

it('tidak menampilkan tombol aksi pengerjaan kepada change manager dan pemohon', function (string $role) {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->assignRole($role);

    $rfcAttributes = $role === 'Pemohon'
        ? ['submitted_by_id' => $viewer->id, 'created_by_id' => $viewer->id]
        : [];

    [$owner, $rfc, $task] = releaseTaskProgressSetup([], $rfcAttributes);

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $owner, 3);
    $service->start($task, $owner);

    Livewire::actingAs($viewer)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Isi laporan hasil. Setelah dikirim, RFC masuk review Change Manager.')
        ->assertDontSee('Wajib menuliskan alasan rollback.')
        ->assertDontSee('hasil=success')
        ->assertDontSee('hasil=rolled_back');
})->with(['Change Manager', 'Pemohon']);

it('mengisi hasil implementasi dari parameter kueri hasil', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 3);
    $service->start($task, $user);

    Livewire::actingAs($user)
        ->withQueryParams(['hasil' => 'rolled_back'])
        ->test(ImplementChange::class, ['rfc' => $rfc])
        ->assertSet('implementation_result', 'rolled_back');
});

it('menyimpan rencana mulai pada estimasi', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $plannedStart = now('Asia/Jakarta')->startOfDay()->addDays(3);

    app(ReleaseTaskProgressService::class)->setEstimate($task, $user, 5, $plannedStart);

    expect($task->fresh()->planned_start_on->toDateString())->toBe($plannedStart->toDateString());
});

it('menolak rencana mulai yang sudah lewat', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    expect(fn () => app(ReleaseTaskProgressService::class)
        ->setEstimate($task, $user, 5, now('Asia/Jakarta')->startOfDay()->subDay()))
        ->toThrow(ReleaseTaskProgressException::class);

    expect($task->fresh()->planned_start_on)->toBeNull();
});

it('menolak rencana mulai lebih dari 60 hari ke depan', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    expect(fn () => app(ReleaseTaskProgressService::class)
        ->setEstimate($task, $user, 5, now('Asia/Jakarta')->startOfDay()->addDays(61)))
        ->toThrow(ReleaseTaskProgressException::class);

    expect($task->fresh()->planned_start_on)->toBeNull();
});

it('menghitung jendela jadwal sesuai status tugas', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));

    $withoutEstimate = ReleaseTask::factory()->make([
        'execution_status' => 'pending',
        'estimated_days' => null,
        'planned_start_on' => null,
    ]);
    expect($withoutEstimate->scheduleWindow())->toBeNull();

    $planned = ReleaseTask::factory()->make([
        'execution_status' => 'pending',
        'estimated_days' => 3,
        'planned_start_on' => '2026-11-12',
    ]);
    [$plannedStart, $plannedEnd] = $planned->scheduleWindow();
    expect($plannedStart->toDateString())->toBe('2026-11-12')
        ->and($plannedEnd->toDateString())->toBe('2026-11-15');

    $todayStart = ReleaseTask::factory()->make([
        'execution_status' => 'pending',
        'estimated_days' => 2,
        'planned_start_on' => null,
    ]);
    [$todayStartDate, $todayEndDate] = $todayStart->scheduleWindow();
    expect($todayStartDate->toDateString())->toBe('2026-11-11')
        ->and($todayEndDate->toDateString())->toBe('2026-11-13');

    $running = ReleaseTask::factory()->make([
        'execution_status' => 'in_progress',
        'scheduled_start' => '2026-11-10 08:00:00',
        'scheduled_end' => '2026-11-12 08:00:00',
    ]);
    [$runningStart, $runningEnd] = $running->scheduleWindow();
    expect($runningStart->format('Y-m-d H:i'))->toBe('2026-11-10 08:00')
        ->and($runningEnd->format('Y-m-d H:i'))->toBe('2026-11-12 08:00');

    $finished = ReleaseTask::factory()->make([
        'execution_status' => 'successful',
        'scheduled_start' => '2026-11-10 08:00:00',
        'scheduled_end' => '2026-11-12 08:00:00',
    ]);
    expect($finished->scheduleWindow())->toBeNull();
});

it('mendeteksi bentrok jadwal pada aplikasi yang sama', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $application = Application::factory()->create();
    $rfc->update(['application_id' => $application->id]);

    $otherRfc = ChangeRequest::factory()->create(['application_id' => $application->id]);
    $otherMember = ReleaseTeamMember::factory()->create(['is_active' => true]);

    ReleaseTask::factory()->create([
        'change_request_id' => $otherRfc->id,
        'release_team_member_id' => $otherMember->id,
        'execution_status' => 'pending',
        'estimated_days' => 5,
        'planned_start_on' => '2026-11-12',
    ]);

    $conflicts = app(ReleaseTaskProgressService::class)->conflictsFor(
        $task->fresh(['changeRequest']),
        Carbon::parse('2026-11-10'),
        Carbon::parse('2026-11-14'),
    );

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts->first()->change_request_id)->toBe($otherRfc->id);
});

it('mengabaikan jadwal pada aplikasi berbeda saat mendeteksi bentrok', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $rfc->update(['application_id' => Application::factory()->create()->id]);

    $otherRfc = ChangeRequest::factory()->create([
        'application_id' => Application::factory()->create()->id,
    ]);
    $otherMember = ReleaseTeamMember::factory()->create(['is_active' => true]);

    ReleaseTask::factory()->create([
        'change_request_id' => $otherRfc->id,
        'release_team_member_id' => $otherMember->id,
        'execution_status' => 'pending',
        'estimated_days' => 5,
        'planned_start_on' => '2026-11-12',
    ]);

    $conflicts = app(ReleaseTaskProgressService::class)->conflictsFor(
        $task->fresh(['changeRequest']),
        Carbon::parse('2026-11-10'),
        Carbon::parse('2026-11-14'),
    );

    expect($conflicts)->toBeEmpty();
});

it('mengabaikan RFC terhapus saat mendeteksi bentrok', function () {
    [$user, $rfc, $task] = releaseTaskProgressSetup();

    $application = Application::factory()->create();
    $rfc->update(['application_id' => $application->id]);

    $otherRfc = ChangeRequest::factory()->create(['application_id' => $application->id]);
    $otherMember = ReleaseTeamMember::factory()->create(['is_active' => true]);

    ReleaseTask::factory()->create([
        'change_request_id' => $otherRfc->id,
        'release_team_member_id' => $otherMember->id,
        'execution_status' => 'pending',
        'estimated_days' => 5,
        'planned_start_on' => '2026-11-12',
    ]);

    $otherRfc->delete();

    $conflicts = app(ReleaseTaskProgressService::class)->conflictsFor(
        $task->fresh(['changeRequest']),
        Carbon::parse('2026-11-10'),
        Carbon::parse('2026-11-14'),
    );

    expect($conflicts)->toBeEmpty();
});
