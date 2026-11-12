<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ReleaseTaskProgressPanel;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\ReleaseTaskProgressService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array{0: User, 1: ChangeRequest, 2: ReleaseTask}
 */
function releaseProgressPanelSetup(): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::APPROVED->value,
    ]);

    $task = ReleaseTask::factory()->create([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
        'estimated_days' => null,
        'scheduled_start' => null,
        'scheduled_end' => null,
    ]);

    return [$user, $rfc, $task];
}

it('menyimpan estimasi lalu menampilkan ringkasan di tempat yang sama', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('estimatedDays', '5')
        ->call('setEstimate')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Estimasi pengerjaan berhasil disimpan.')
        ->assertSee('Tersimpan')
        ->assertDontSee('Simpan Estimasi');

    expect($task->fresh()->estimated_days)->toBe(5);
});

it('memulai pengerjaan setelah estimasi tersimpan', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->setEstimate($task, $user, 3);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->call('startWork')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Pengerjaan berhasil dimulai.')
        ->assertSee('Catatan Kegiatan');

    expect($task->fresh()->execution_status)->toBe('in_progress');
});

it('merevisi estimasi dengan alasan yang valid', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->setEstimate($task, $user, 5);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('revisedDays', '8')
        ->set('revisionReason', 'Tambahan pengujian integrasi antarsistem.')
        ->call('reviseEstimate')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Revisi estimasi berhasil disimpan.');

    expect($task->fresh()->estimated_days)->toBe(8);
});

it('menambahkan catatan kegiatan dan menandai kendala', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 3);
    $service->start($task, $user);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('progressNote', 'Koordinasi dengan vendor selesai.')
        ->set('progressBlocked', true)
        ->call('addProgressNote')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Catatan kegiatan berhasil ditambahkan.');

    expect($task->fresh()->updates()->where('type', 'blocked')->count())->toBe(1);
});

it('menolak anggota tim rilis lain yang tidak ditugaskan pada RFC', function () {
    [, $rfc] = releaseProgressPanelSetup();

    $otherUser = User::factory()->create(['is_active' => true]);
    $otherUser->assignRole('Tim Rilis');
    ReleaseTeamMember::factory()->create([
        'user_id' => $otherUser->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($otherUser)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertForbidden();
});

it('menyimpan rencana mulai dan menampilkan pratinjau selesai', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $plannedStart = now('Asia/Jakarta')->startOfDay()->addDays(2)->toDateString();

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertSee('Rencana mulai')
        ->assertSee('Perkiraan selesai:')
        ->assertSee('5 hari')
        ->set('estimatedDays', '5')
        ->set('plannedStartOn', $plannedStart)
        ->call('setEstimate')
        ->assertHasNoErrors()
        ->assertSee('Tersimpan');

    expect($task->fresh()->planned_start_on->toDateString())->toBe($plannedStart);
});

it('menampilkan estimasi pertama tanpa placeholder kosong', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->setEstimate($task, $user, 1);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Estimasi: 1 hari')
        ->assertDontSee('— hari');
});

it('menampilkan riwayat revisi estimasi dengan nilai lama dan baru', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 1);
    $service->reviseEstimate($task, $user, 2, 'Tambahan pengujian integrasi antarsistem.');

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSeeHtml('1 hari &rarr; 2 hari');
});

it('menampilkan peringatan bentrok setelah estimasi disimpan', function () {
    [$user, $rfc] = releaseProgressPanelSetup();

    $application = Application::factory()->create();
    $rfc->update(['application_id' => $application->id]);

    $otherRfc = ChangeRequest::factory()->create(['application_id' => $application->id]);
    $otherMember = ReleaseTeamMember::factory()->create(['is_active' => true]);

    ReleaseTask::factory()->create([
        'change_request_id' => $otherRfc->id,
        'release_team_member_id' => $otherMember->id,
        'execution_status' => 'pending',
        'estimated_days' => 5,
        'planned_start_on' => now('Asia/Jakarta')->startOfDay()->addDay()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('estimatedDays', '5')
        ->set('plannedStartOn', now('Asia/Jakarta')->toDateString())
        ->call('setEstimate')
        ->assertHasNoErrors()
        ->assertSee('Jadwal berpotensi bentrok')
        ->assertSee($otherRfc->displayIdentifier());
});
