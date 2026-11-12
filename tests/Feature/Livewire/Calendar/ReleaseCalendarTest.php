<?php

use App\Enums\RfcStatus;
use App\Livewire\Calendar\ReleaseCalendar;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: ReleaseTeamMember}
 */
function calendarTeamMember(): array
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
 * @param  array<string, mixed>  $rfcAttributes
 * @param  array<string, mixed>  $taskAttributes
 */
function calendarTask(ReleaseTeamMember $member, array $rfcAttributes = [], array $taskAttributes = []): ReleaseTask
{
    $rfc = ChangeRequest::factory()->create(array_merge([
        'status' => RfcStatus::APPROVED->value,
    ], $rfcAttributes));

    return ReleaseTask::factory()->create(array_merge([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
        'estimated_days' => 5,
        'planned_start_on' => '2026-11-12',
        'scheduled_start' => null,
        'scheduled_end' => null,
    ], $taskAttributes));
}

it('menampilkan tugas pada bulan yang benar dengan lebar segmen yang tepat', function () {
    [$user, $member] = calendarTeamMember();

    $task = calendarTask($member, [], ['estimated_days' => 3, 'planned_start_on' => '2026-11-12']);
    $otherMonth = calendarTask($member, [], ['planned_start_on' => '2026-12-10']);

    $identifier = $task->changeRequest->displayIdentifier();
    $otherIdentifier = $otherMonth->changeRequest->displayIdentifier();

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertOk()
        ->assertSee($identifier)
        ->assertDontSee($otherIdentifier)
        ->assertSee('span 3');
});

it('menampilkan tugas selesai dengan jendela jadwal terjadwal', function () {
    [$user, $member] = calendarTeamMember();

    $task = calendarTask($member, [], [
        'execution_status' => 'successful',
        'estimated_days' => null,
        'planned_start_on' => null,
        'scheduled_start' => '2026-11-10 08:00:00',
        'scheduled_end' => '2026-11-13 08:00:00',
    ]);

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee($task->changeRequest->displayIdentifier());
});

it('mengabaikan RFC yang sudah terhapus', function () {
    [$user, $member] = calendarTeamMember();

    $task = calendarTask($member, [], ['planned_start_on' => '2026-11-12']);

    $task->changeRequest->delete();

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertDontSee($task->changeRequest->displayIdentifier());
});

it('membatasi tugas Tim Rilis hanya miliknya sendiri', function () {
    [$userA, $memberA] = calendarTeamMember();
    [, $memberB] = calendarTeamMember();

    $taskA = calendarTask($memberA);
    $taskB = calendarTask($memberB);

    Livewire::actingAs($userA)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee($taskA->changeRequest->displayIdentifier())
        ->assertDontSee($taskB->changeRequest->displayIdentifier());
});

it('melihat semua tugas sebagai Change Manager dan dapat memfilter per anggota tim', function () {
    [, $memberA] = calendarTeamMember();
    [, $memberB] = calendarTeamMember();

    $taskA = calendarTask($memberA);
    $taskB = calendarTask($memberB);

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Change Manager');

    Livewire::actingAs($manager)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee($taskA->changeRequest->displayIdentifier())
        ->assertSee($taskB->changeRequest->displayIdentifier())
        ->set('team', (string) $memberA->id)
        ->assertSee($taskA->changeRequest->displayIdentifier())
        ->assertDontSee($taskB->changeRequest->displayIdentifier());
});

it('menandai bar yang bentrok dengan aplikasi yang sama', function () {
    [$user, $member] = calendarTeamMember();

    $application = Application::factory()->create();

    $first = calendarTask($member, ['application_id' => $application->id], [
        'estimated_days' => 4,
        'planned_start_on' => '2026-11-10',
    ]);
    $second = calendarTask($member, ['application_id' => $application->id], [
        'estimated_days' => 3,
        'planned_start_on' => '2026-11-12',
    ]);

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee('Bentrok')
        ->assertSee($first->changeRequest->displayIdentifier())
        ->assertSee($second->changeRequest->displayIdentifier());
});

it('menolak Pemohon dengan 403', function () {
    $requester = User::factory()->create(['is_active' => true]);
    $requester->assignRole('Pemohon');

    Livewire::actingAs($requester)
        ->test(ReleaseCalendar::class)
        ->assertForbidden();
});

it('mengabaikan parameter bulan yang tidak valid dan kembali ke bulan berjalan', function () {
    [$user] = calendarTeamMember();

    Livewire::actingAs($user)
        ->withQueryParams(['bulan' => 'bukan-bulan'])
        ->test(ReleaseCalendar::class)
        ->assertOk()
        ->assertSet('month', '2026-11')
        ->assertSee('November 2026');
});

it('menolak Admin dan Super Admin dengan 403', function () {
    foreach (['Admin', 'Super Admin'] as $role) {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('release-calendar.index'))
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(ReleaseCalendar::class)
            ->assertForbidden();
    }
});

it('mengabaikan filter tim dari Tim Rilis pada URL dan aksi Livewire', function () {
    [$userA, $memberA] = calendarTeamMember();
    [, $memberB] = calendarTeamMember();

    $taskA = calendarTask($memberA);
    $taskB = calendarTask($memberB);

    Livewire::actingAs($userA)
        ->withQueryParams(['tim' => (string) $memberB->id])
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSet('team', '')
        ->set('team', (string) $memberB->id)
        ->assertSet('team', '')
        ->assertSee($taskA->changeRequest->displayIdentifier())
        ->assertDontSee($taskB->changeRequest->displayIdentifier());
});

it('menampilkan kalender kosong untuk anggota Tim Rilis yang nonaktif', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => false,
    ]);

    $task = calendarTask($member);

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertOk()
        ->assertSee('Belum ada jadwal')
        ->assertDontSee($task->changeRequest->displayIdentifier());
});

it('memeriksa ulang otorisasi pada request Livewire lanjutan', function () {
    [$user] = calendarTeamMember();

    $component = Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertOk();

    $user->removeRole('Tim Rilis');

    $component
        ->call('nextMonth')
        ->assertForbidden();
});

it('menampilkan tugas selesai tanpa jadwal pada hari executed_at', function () {
    [$user, $member] = calendarTeamMember();

    $task = calendarTask($member, [], [
        'execution_status' => 'successful',
        'estimated_days' => null,
        'planned_start_on' => null,
        'scheduled_start' => null,
        'scheduled_end' => null,
        'executed_at' => '2026-11-15 09:00:00',
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['bulan' => '2026-11'])
        ->test(ReleaseCalendar::class)
        ->assertSee($task->changeRequest->displayIdentifier())
        ->assertSee('15 Nov 2026');
});

it('melabeli tugas rolled_back sebagai Rollback dan successful sebagai Selesai', function () {
    [$user, $member] = calendarTeamMember();

    $rolledBack = calendarTask($member, [], [
        'execution_status' => 'rolled_back',
        'scheduled_start' => '2026-11-12 08:00:00',
        'scheduled_end' => '2026-11-12 12:00:00',
    ]);

    $successful = calendarTask($member, [], [
        'execution_status' => 'successful',
        'scheduled_start' => '2026-11-13 08:00:00',
        'scheduled_end' => '2026-11-13 12:00:00',
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['bulan' => '2026-11'])
        ->test(ReleaseCalendar::class)
        ->assertSee($rolledBack->changeRequest->displayIdentifier())
        ->assertSee($successful->changeRequest->displayIdentifier())
        ->assertSee('• Rollback')
        ->assertSee('• Selesai');
});

it('mengubah label bulan saat navigasi bulan', function () {
    [$user] = calendarTeamMember();

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee('Kalender November 2026')
        ->assertSeeHtml('aria-live="polite"')
        ->call('nextMonth')
        ->assertSet('month', '2026-12')
        ->assertSee('Kalender Desember 2026')
        ->call('previousMonth')
        ->assertSee('Kalender November 2026')
        ->call('currentMonth')
        ->assertSet('month', '2026-11')
        ->assertSee('Kalender November 2026');
});

it('menampilkan tugas yang tersembunyi melalui tombol tambah lagi', function () {
    [$user, $member] = calendarTeamMember();

    $tasks = collect(range(1, 4))->map(fn (): ReleaseTask => calendarTask($member, [], [
        'estimated_days' => 1,
        'planned_start_on' => '2026-11-12',
    ]));

    $component = Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertSee('+1 lagi');

    $hiddenPanel = Str::betweenFirst($component->html(), 'data-calendar-hidden-tasks', '</ul>');

    $identifiersInPanel = $tasks
        ->map(fn (ReleaseTask $task): string => $task->changeRequest->displayIdentifier())
        ->filter(fn (string $identifier): bool => str_contains($hiddenPanel, $identifier));

    expect($identifiersInPanel)->toHaveCount(1);
});

it('tidak menampilkan tugas pending tanpa estimasi pada kalender', function () {
    [$user, $member] = calendarTeamMember();

    $task = calendarTask($member, [], [
        'estimated_days' => null,
        'planned_start_on' => '2026-11-12',
    ]);

    Livewire::actingAs($user)
        ->test(ReleaseCalendar::class)
        ->set('month', '2026-11')
        ->assertOk()
        ->assertSee('Belum ada jadwal')
        ->assertDontSee($task->changeRequest->displayIdentifier());
});
