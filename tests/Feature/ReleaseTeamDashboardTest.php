<?php

use App\Enums\RfcStatus;
use App\Livewire\Dashboard\ReleaseTeamDashboard;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: ReleaseTeamMember}
 */
function releaseTeamDashboardUser(): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    return [$user, $member];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function releaseTeamDashboardRfc(array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'status' => RfcStatus::APPROVED->value,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function releaseTeamDashboardTask(ChangeRequest $rfc, ReleaseTeamMember $member, array $attributes = []): ReleaseTask
{
    return ReleaseTask::factory()
        ->forRfc($rfc)
        ->assignedTo($member)
        ->create(array_merge([
            'execution_status' => 'pending',
            'estimated_days' => null,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'executed_at' => null,
        ], $attributes));
}

it('mengarahkan Tim Rilis ke dashboard baru tanpa mengubah perilaku peran lain', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 9, 0, 0, 'Asia/Jakarta'));

    [$releaseTeamUser] = releaseTeamDashboardUser();
    $pemohon = User::factory()->create(['is_active' => true]);
    $pemohon->assignRole('Pemohon');
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Change Manager');

    $this->actingAs($releaseTeamUser)
        ->get('/')
        ->assertRedirect(route('release-team.dashboard'));

    $this->actingAs($pemohon)
        ->get('/')
        ->assertRedirect(route('requester.dashboard'));

    $this->actingAs($manager)
        ->get('/')
        ->assertRedirect(route('change-manager.dashboard'));
});

it('menghitung ringkasan dan mengurutkan tugas aktif milik anggota sendiri', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();
    [, $otherMember] = releaseTeamDashboardUser();

    $pending = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'created_at' => now()->subDays(5),
    ]);

    $doing = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'in_progress',
        'estimated_days' => 5,
        'scheduled_start' => now()->subDays(2),
        'scheduled_end' => now()->addDays(2),
    ]);

    $late = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'in_progress',
        'estimated_days' => 4,
        'scheduled_start' => now()->subDays(10),
        'scheduled_end' => now()->subDays(3),
    ]);

    releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'successful',
        'executed_at' => now()->startOfMonth()->addDays(2),
    ]);

    releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'rolled_back',
        'executed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDay(),
    ]);

    releaseTeamDashboardTask(releaseTeamDashboardRfc(), $otherMember);

    $trashedRfc = releaseTeamDashboardRfc();
    releaseTeamDashboardTask($trashedRfc, $member);
    $trashedRfc->delete();

    $overview = app(DashboardMetricsService::class)->releaseTeamOverview($user);

    expect($overview['counts'])->toBe([
        'pending' => 1,
        'doing' => 1,
        'late' => 1,
        'done_this_month' => 1,
        'needs_action' => 2,
    ])->and(collect($overview['tasks'])->pluck('task_id')->all())
        ->toBe([$late->id, $doing->id, $pending->id]);
});

it('menentukan aksi primer setiap status tugas', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();

    $late = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'in_progress',
        'estimated_days' => 3,
        'scheduled_start' => now()->subDays(6),
        'scheduled_end' => now()->subDay(),
    ]);
    $doing = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'execution_status' => 'in_progress',
        'estimated_days' => 3,
        'scheduled_start' => now()->subDay(),
        'scheduled_end' => now()->addDays(2),
    ]);
    $ready = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member, [
        'estimated_days' => 2,
    ]);
    $needsEstimate = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member);

    $tasks = collect(app(DashboardMetricsService::class)->releaseTeamOverview($user)['tasks'])
        ->keyBy('task_id');

    expect($tasks[$late->id]['primary_action'])->toBe('finish')
        ->and($tasks[$late->id]['days_overdue'])->toBe(1)
        ->and($tasks[$doing->id]['primary_action'])->toBe('open')
        ->and($tasks[$ready->id]['primary_action'])->toBe('start')
        ->and($tasks[$ready->id]['has_estimate'])->toBeTrue()
        ->and($tasks[$needsEstimate->id]['primary_action'])->toBe('set_estimate')
        ->and($tasks[$needsEstimate->id]['has_estimate'])->toBeFalse();
});

it('memfilter daftar tugas berdasarkan status', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();

    releaseTeamDashboardTask(releaseTeamDashboardRfc(['title' => 'Perbaikan Jaringan Kantor']), $member, [
        'execution_status' => 'in_progress',
        'estimated_days' => 2,
        'scheduled_start' => now()->subDays(5),
        'scheduled_end' => now()->subDays(2),
    ]);
    releaseTeamDashboardTask(releaseTeamDashboardRfc(['title' => 'Pembaruan Basis Data']), $member);

    Livewire::actingAs($user)
        ->test(ReleaseTeamDashboard::class)
        ->assertSee('Perbaikan Jaringan Kantor')
        ->assertSee('Pembaruan Basis Data')
        ->set('filter', 'late')
        ->assertSet('filter', 'late')
        ->assertSee('Perbaikan Jaringan Kantor')
        ->assertDontSee('Pembaruan Basis Data');
});

it('memulai pengerjaan tugas dan mengubah status RFC', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();
    $rfc = releaseTeamDashboardRfc();
    $task = releaseTeamDashboardTask($rfc, $member, ['estimated_days' => 3]);

    Livewire::actingAs($user)
        ->test(ReleaseTeamDashboard::class)
        ->call('startTask', $task->id)
        ->assertSet('actionError', '');

    expect($task->fresh()->execution_status)->toBe('in_progress')
        ->and($task->fresh()->scheduled_start)->not->toBeNull()
        ->and($task->fresh()->scheduled_end)->not->toBeNull()
        ->and($rfc->fresh()->status)->toBe(RfcStatus::IN_PROGRESS->value);
});

it('menolak memulai tugas oleh anggota lain, pemohon, dan change manager', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$owner, $member] = releaseTeamDashboardUser();
    $rfc = releaseTeamDashboardRfc();
    $task = releaseTeamDashboardTask($rfc, $member, ['estimated_days' => 3]);

    [$otherMemberUser] = releaseTeamDashboardUser();
    $pemohon = User::factory()->create(['is_active' => true]);
    $pemohon->assignRole('Pemohon');
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Change Manager');

    $dashboard = Livewire::actingAs($owner)->test(ReleaseTeamDashboard::class);

    Livewire::actingAs($otherMemberUser);

    try {
        $dashboard->instance()->startTask($task->id);
        $this->fail('Anggota Tim Rilis lain seharusnya ditolak.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    Livewire::actingAs($pemohon)
        ->test(ReleaseTeamDashboard::class)
        ->assertForbidden();

    Livewire::actingAs($manager)
        ->test(ReleaseTeamDashboard::class)
        ->assertForbidden();

    expect($task->fresh()->execution_status)->toBe('pending')
        ->and($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value);
});

it('menampilkan pesan Indonesia saat tugas belum memiliki estimasi', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();
    $task = releaseTeamDashboardTask(releaseTeamDashboardRfc(), $member);

    Livewire::actingAs($user)
        ->test(ReleaseTeamDashboard::class)
        ->call('startTask', $task->id)
        ->assertSet('actionError', 'Tetapkan estimasi pengerjaan sebelum memulai.');

    expect($task->fresh()->execution_status)->toBe('pending');
});

it('menampilkan banner sambutan tanpa tanggal dan tombol muat ulang', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();

    $this->actingAs($user)
        ->get(route('release-team.dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang, '.$member->name)
        ->assertDontSee('Tugas saya')
        ->assertDontSee('Muat ulang');
});

it('menampilkan empty state saat belum ada tugas', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 9, 10, 0, 0, 'Asia/Jakarta'));

    [$user, $member] = releaseTeamDashboardUser();

    $this->actingAs($user)
        ->get(route('release-team.dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang, '.$member->name)
        ->assertSee('Belum ada tugas')
        ->assertSee('Tugas baru dari Change Manager akan muncul di sini.');
});

it('menolak pemohon dan change manager membuka dashboard Tim Rilis', function () {
    $pemohon = User::factory()->create(['is_active' => true]);
    $pemohon->assignRole('Pemohon');
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Change Manager');
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('rfc.view_assigned');

    $this->actingAs($pemohon)
        ->get(route('release-team.dashboard'))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('release-team.dashboard'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('release-team.dashboard'))
        ->assertForbidden();
});
