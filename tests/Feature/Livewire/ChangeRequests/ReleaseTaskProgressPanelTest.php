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

/**
 * @return array{activity_plan: string, change_plan: string, rollback_mechanism: string}
 */
function releaseProgressPanelRiskData(): array
{
    return [
        'activity_plan' => 'Menyiapkan cadangan basis data sebelum pelaksanaan perubahan.',
        'change_plan' => 'Memperbarui konfigurasi layanan dan menjalankan pengujian fungsional.',
        'rollback_mechanism' => 'Memulihkan cadangan basis data dan konfigurasi sebelumnya.',
    ];
}

it('menyimpan analisis risiko beserta estimasi di tempat yang sama', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $data = releaseProgressPanelRiskData();

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('estimatedDays', '5')
        ->set('plannedStartOn', now('Asia/Jakarta')->toDateString())
        ->set('activityPlan', $data['activity_plan'])
        ->set('changePlan', $data['change_plan'])
        ->set('rollbackMechanism', $data['rollback_mechanism'])
        ->call('saveRiskAnalysis')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Analisis risiko berhasil disimpan.')
        ->assertSee('Tersimpan')
        ->assertDontSee('Simpan Analisis Risiko');

    expect($task->fresh()->estimated_days)->toBe(5)
        ->and($task->fresh()->riskAnalysis)->not->toBeNull()
        ->and($task->fresh()->updates()->where('type', 'risk_analysis_submitted')->count())->toBe(1);
});

it('memulai pengerjaan setelah analisis risiko tersimpan', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->saveRiskAnalysis(
        $task,
        $user,
        releaseProgressPanelRiskData(),
        3,
    );

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->call('startWork')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Pengerjaan berhasil dimulai.')
        ->assertSee('Rencana perubahan');

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
        ->assertSee('Update tersimpan.');

    expect($task->fresh()->estimated_days)->toBe(8);
});

it('menambahkan rencana perubahan dan menandai kendala', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->saveRiskAnalysis($task, $user, releaseProgressPanelRiskData(), 3);
    $service->start($task, $user);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('progressNote', 'Koordinasi dengan vendor selesai.')
        ->set('progressBlocked', true)
        ->call('addProgressNote')
        ->assertHasNoErrors()
        ->assertDispatched('release-progress-updated')
        ->assertSee('Rencana perubahan berhasil ditambahkan.');

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

it('menyimpan rencana mulai dan menampilkan ringkasan analisis risiko', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $plannedStart = now('Asia/Jakarta')->startOfDay()->addDays(2)->toDateString();
    $data = releaseProgressPanelRiskData();

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertSee('Estimasi kegiatan')
        ->set('estimatedDays', '5')
        ->set('plannedStartOn', $plannedStart)
        ->set('activityPlan', $data['activity_plan'])
        ->set('changePlan', $data['change_plan'])
        ->set('rollbackMechanism', $data['rollback_mechanism'])
        ->call('saveRiskAnalysis')
        ->assertHasNoErrors()
        ->assertSee('Tersimpan')
        ->assertSee('5 hari');

    expect($task->fresh()->planned_start_on->toDateString())->toBe($plannedStart);
});

it('menampilkan estimasi dan analisis risiko yang sudah tersimpan', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->saveRiskAnalysis($task, $user, releaseProgressPanelRiskData(), 1);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('1 hari')
        ->assertSee('Rencana kegiatan')
        ->assertSee('Mekanisme rollback');
});

it('memperbarui analisis risiko walau rencana mulai sudah lewat', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $pastDate = now('Asia/Jakarta')->startOfDay()->subDays(2);

    $task->update([
        'estimated_days' => 3,
        'planned_start_on' => $pastDate->toDateString(),
    ]);

    app(ReleaseTaskProgressService::class)->saveRiskAnalysis($task, $user, releaseProgressPanelRiskData(), 3, $pastDate);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->call('toggleRiskForm')
        ->set('activityPlan', 'Memperbarui rencana kegiatan berdasarkan koordinasi terbaru.')
        ->set('changePlan', 'Memperbarui rencana perubahan tanpa mengubah jadwal yang sudah berjalan.')
        ->set('rollbackMechanism', 'Mengembalikan konfigurasi sebelumnya sesuai prosedur rollback.')
        ->call('saveRiskAnalysis')
        ->assertHasNoErrors()
        ->assertSee('Analisis risiko berhasil disimpan.');

    expect($task->fresh()->planned_start_on->toDateString())->toBe($pastDate->toDateString())
        ->and($task->fresh()->riskAnalysis->activity_plan)->toBe('Memperbarui rencana kegiatan berdasarkan koordinasi terbaru.');
});

it('menampilkan galat rencana mulai di dalam form analisis risiko', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    app(ReleaseTaskProgressService::class)->saveRiskAnalysis($task, $user, releaseProgressPanelRiskData(), 3);

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->call('toggleRiskForm')
        ->set('plannedStartOn', now('Asia/Jakarta')->startOfDay()->subDays(5)->toDateString())
        ->call('saveRiskAnalysis')
        ->assertHasErrors('progress')
        ->assertSee('Rencana mulai tidak boleh di masa lalu.');
});

it('menampilkan riwayat revisi estimasi dengan nilai lama dan baru', function () {
    [$user, $rfc, $task] = releaseProgressPanelSetup();

    $service = app(ReleaseTaskProgressService::class);
    $service->setEstimate($task, $user, 1);
    $service->reviseEstimate($task, $user, 2, 'Tambahan pengujian integrasi antarsistem.');

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSeeHtml('1 hari &rarr; 2 hari')
        ->assertSee('Update informasi');
});

it('menampilkan peringatan bentrok setelah analisis risiko disimpan', function () {
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

    $data = releaseProgressPanelRiskData();

    Livewire::actingAs($user)
        ->test(ReleaseTaskProgressPanel::class, ['rfc' => $rfc])
        ->set('estimatedDays', '5')
        ->set('plannedStartOn', now('Asia/Jakarta')->toDateString())
        ->set('activityPlan', $data['activity_plan'])
        ->set('changePlan', $data['change_plan'])
        ->set('rollbackMechanism', $data['rollback_mechanism'])
        ->call('saveRiskAnalysis')
        ->assertHasNoErrors()
        ->assertSee('Jadwal berpotensi bentrok')
        ->assertSee($otherRfc->displayIdentifier());
});
