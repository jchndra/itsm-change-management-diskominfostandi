<?php

use App\Enums\RfcStatus;
use App\Exceptions\ReleaseTaskProgressException;
use App\Livewire\ChangeRequests\ImplementChange;
use App\Livewire\ChangeRequests\ShowChangeRequest;
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
