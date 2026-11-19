<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\ReleaseTaskProgressService;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    Carbon::setTestNow(Carbon::create(2026, 11, 18, 9, 0, 0, 'Asia/Jakarta'));

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Estimasi', 'is_active' => true]);
    $this->pemohon->assignRole('Pemohon');

    $this->changeManager = User::factory()->create(['name' => 'Change Manager Estimasi', 'is_active' => true]);
    $this->changeManager->assignRole('Change Manager');

    $this->releaseUser = User::factory()->create(['name' => 'Budi Pelaksana', 'is_active' => true]);
    $this->releaseUser->assignRole('Tim Rilis');

    $this->member = ReleaseTeamMember::factory()->linkedTo($this->releaseUser)->create([
        'name' => 'Tim Rilis Aplikasi',
        'is_active' => true,
    ]);

    $this->rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $this->pemohon->id,
        'created_by_id' => $this->pemohon->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    $this->task = ReleaseTask::factory()->forRfc($this->rfc)->assignedTo($this->member)->create([
        'assigned_by_id' => $this->changeManager->id,
        'estimated_days' => null,
        'planned_start_on' => null,
        'scheduled_start' => null,
        'scheduled_end' => null,
        'execution_status' => 'pending',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function setReleaseEstimate(ReleaseTask $task, User $actor): void
{
    app(ReleaseTaskProgressService::class)->setEstimate(
        $task,
        $actor,
        5,
        Carbon::create(2026, 11, 20, 0, 0, 0, 'Asia/Jakarta'),
    );
}

it('menampilkan penetapan dan revisi estimasi Tim Rilis pada riwayat proses staf', function () {
    setReleaseEstimate($this->task, $this->releaseUser);

    app(ReleaseTaskProgressService::class)->reviseEstimate(
        $this->task,
        $this->releaseUser,
        7,
        'Tambahan pengujian integrasi antarsistem.',
    );

    $events = collect(app(RfcHistoryService::class)->build($this->rfc->fresh(), true)['events']);

    $estimateSet = $events->firstWhere('key', 'release_estimate_set');
    $estimateRevised = $events->firstWhere('key', 'release_estimate_revised');

    expect($estimateSet)->not->toBeNull()
        ->and($estimateSet['label'])->toBe('Tim Rilis Aplikasi menetapkan estimasi kegiatan')
        ->and($estimateSet['note'])->toContain('Estimasi kegiatan: 5 hari')
        ->and($estimateSet['note'])->toContain('Rencana mulai: 20 Nov 2026')
        ->and($estimateSet['actor_name'])->toBe($this->releaseUser->name)
        ->and($estimateSet['actor_role'])->toBe('Tim Rilis')
        ->and($estimateRevised)->not->toBeNull()
        ->and($estimateRevised['label'])->toBe('Tim Rilis Aplikasi memperbarui estimasi kegiatan')
        ->and($estimateRevised['note'])->toContain('Estimasi kegiatan: 5 hari → 7 hari')
        ->and($estimateRevised['note'])->toContain('Alasan: Tambahan pengujian integrasi antarsistem.');
});

it('menampilkan estimasi dan rencana mulai kepada Pemohon tanpa identitas akun Tim Rilis', function () {
    setReleaseEstimate($this->task, $this->releaseUser);

    app(ReleaseTaskProgressService::class)->reviseEstimate(
        $this->task,
        $this->releaseUser,
        7,
        'Tambahan pengujian integrasi antarsistem.',
    );

    Livewire::actingAs($this->pemohon)
        ->test(ShowChangeRequest::class, ['rfc' => $this->rfc->fresh()])
        ->assertOk()
        ->assertSee('Estimasi kegiatan')
        ->assertSee('5 hari')
        ->assertSee('Rencana mulai')
        ->assertSee('20 Nov 2026')
        ->assertSee('5 hari → 7 hari')
        ->assertSee('Alasan')
        ->assertSee('Tambahan pengujian integrasi antarsistem.')
        ->assertDontSee($this->releaseUser->name);
});

it('menampilkan estimasi dan rencana mulai kepada Tim Rilis yang ditugaskan', function () {
    setReleaseEstimate($this->task, $this->releaseUser);

    Livewire::actingAs($this->releaseUser)
        ->test(ShowChangeRequest::class, ['rfc' => $this->rfc->fresh()])
        ->assertOk()
        ->assertSee('Estimasi kegiatan')
        ->assertSee('5 hari')
        ->assertSee('Rencana mulai')
        ->assertSee('20 Nov 2026');
});

it('tidak menambahkan entri estimasi untuk RFC tanpa tugas Tim Rilis', function () {
    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $this->pemohon->id,
        'created_by_id' => $this->pemohon->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    $events = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events']);

    expect($events->whereIn('key', ['release_estimate_set', 'release_estimate_revised'])->isEmpty())->toBeTrue();
});
