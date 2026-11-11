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
