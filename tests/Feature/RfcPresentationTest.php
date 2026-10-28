<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\ChangeManagerDashboard;
use App\Livewire\Requester\Dashboard as RequesterDashboard;
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
});

afterEach(function () {
    Carbon::setTestNow();
});

function rfcPresentationUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function rfcPresentationRisk(ChangeRequest $rfc, User $assessor, array $attributes = []): void
{
    $rfc->riskAssessment()->create(array_merge([
        'assessed_by_id' => $assessor->id,
        'system_criticality_score' => 3,
        'impact_scope_score' => 3,
        'rollback_readiness_score' => 2,
        'total_score' => 8,
        'risk_level' => 'Tinggi',
        'impact_level' => 'Dampak-Uji',
        'urgency_level' => 'Tinggi',
        'classification' => 'Emergency Change',
        'analysis_notes' => 'Catatan analisis internal.',
        'mitigation_plan' => 'Mitigasi.',
        'rollback_plan' => 'Rollback.',
    ], $attributes));
}

function rfcPresentationImplementation(ChangeRequest $rfc, User $recorder, ?Carbon $evaluatedAt = null): void
{
    $rfc->implementation()->create([
        'recorded_by_id' => $recorder->id,
        'implementation_result' => 'successful',
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'executor_name' => 'Pelaksana Uji',
        'summary' => 'Ringkasan implementasi.',
        'test_results' => 'Pengujian lulus.',
        'evaluated_at' => $evaluatedAt,
        'evaluated_by_id' => $evaluatedAt ? $recorder->id : null,
    ]);
}

it('memakai komponen kategori di daftar RFC dan dashboard tanpa menambah kolom', function () {
    $requester = rfcPresentationUser('Pemohon');
    $admin = rfcPresentationUser('Admin');

    $normalRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::SUBMITTED->value,
        'change_type' => 'normal',
    ]);

    $legacyRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::SUBMITTED->value,
        'change_type' => null,
    ]);

    $this->actingAs($admin);

    $listHtml = Livewire::test(ListChangeRequests::class)
        ->assertSee($normalRfc->title)
        ->assertSee('Normal')
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertSeeHtml('data-category="normal"')
        ->assertSeeHtml('data-category="uncategorized"')
        ->html();

    expect(substr_count($listHtml, '<th'))->toBe(7);

    $this->actingAs($requester);

    $requesterHtml = Livewire::test(RequesterDashboard::class)
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertSeeHtml('data-category="uncategorized"')
        ->html();

    expect(substr_count($requesterHtml, '<th'))->toBe(6);

    $this->actingAs($admin);

    $adminHtml = Livewire::test(AdminDashboard::class)
        ->assertSee('Kategori')
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertSeeHtml('data-category="uncategorized"')
        ->html();

    expect(substr_count($adminHtml, 'Kategori</th>'))->toBe(1);

    $manager = rfcPresentationUser('Change Manager');
    $managerRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => null,
    ]);

    $this->actingAs($manager);

    $managerHtml = Livewire::test(ChangeManagerDashboard::class)
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertSeeHtml('data-category="uncategorized"')
        ->html();

    expect(substr_count($managerHtml, 'Kategori</th>'))->toBe(1)
        ->and($managerRfc->displayIdentifier())->toContain('RFC-');
});

it('menampilkan kotak Emergency Pemohon hanya selama belum dikonfirmasi', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Anda mengajukan kategori Emergency.')
        ->assertSee('Change Manager akan menilai apakah perubahan ini memenuhi syarat darurat.');

    $rfc->update(['emergency_unconfirmed' => false]);
    rfcPresentationRisk($rfc, $manager, ['classification' => 'Emergency Change']);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('riskAssessment')])
        ->assertOk()
        ->assertDontSee('Anda mengajukan kategori Emergency.')
        ->assertDontSee('Change Manager akan menilai apakah perubahan ini memenuhi syarat darurat.');
});

it('menampilkan tiga varian teks Emergency untuk staf dan teks netral untuk status akhir', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Emergency — belum dikonfirmasi Change Manager')
        ->assertDontSee('Emergency — dikonfirmasi Change Manager');

    $rfc->update(['emergency_unconfirmed' => false]);
    rfcPresentationRisk($rfc, $manager, ['classification' => 'Emergency Change']);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('riskAssessment')])
        ->assertSee('Emergency — dikonfirmasi Change Manager')
        ->assertDontSee('Diusulkan Emergency, ditetapkan');

    $rfc->riskAssessment()->update(['classification' => 'Normal Change']);
    $rfc->refresh()->load('riskAssessment');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertSee('Diusulkan Emergency, ditetapkan Normal Change')
        ->assertDontSee('Emergency — dikonfirmasi Change Manager');

    $rfc->update(['status' => RfcStatus::CLOSED->value]);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('riskAssessment')])
        ->assertSee('RFC diajukan dengan kategori Emergency.')
        ->assertDontSee('belum dikonfirmasi Change Manager');
});

it('tidak menampilkan kotak Emergency untuk RFC non-emergency', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::APPROVED->value,
        'change_type' => 'normal',
        'emergency_unconfirmed' => false,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertDontSee('Anda mengajukan kategori Emergency.')
        ->assertDontSee('Emergency —');

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertDontSee('Emergency —')
        ->assertDontSee('RFC diajukan dengan kategori Emergency.');
});

it('menyembunyikan risiko, prioritas, konfirmasi Emergency, klasifikasi, dan skor dari Pemohon', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
        'priority' => 'high',
    ]);

    rfcPresentationRisk($rfc, $manager, [
        'risk_level' => 'Tingkat-Risiko-Uji',
        'impact_level' => 'Dampak-Uji',
        'classification' => 'Klasifikasi-Intern-Uji',
        'total_score' => 9,
    ]);

    $this->actingAs($requester);

    $requesterHtml = Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('riskAssessment')])
        ->assertSee('Kategori yang diajukan:')
        ->assertSee('Emergency')
        ->assertDontSee('Risiko: Tingkat-Risiko-Uji')
        ->assertDontSee('Prioritas: Tinggi')
        ->assertDontSee('Dampak-Uji')
        ->assertDontSee('Klasifikasi-Intern-Uji')
        ->assertDontSee('Klasifikasi ditetapkan Change Manager')
        ->assertDontSee('Emergency belum dikonfirmasi')
        ->assertDontSee('Skor')
        ->html();

    expect(substr_count($requesterHtml, 'Kategori yang diajukan:'))->toBe(1);

    expect($requesterHtml)
        ->not->toContain('data-risk-level')
        ->not->toContain('data-priority');

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('riskAssessment')])
        ->assertSee('Risiko: Tingkat-Risiko-Uji')
        ->assertSee('Prioritas: Tinggi')
        ->assertSee('Dampak-Uji')
        ->assertSee('Klasifikasi-Intern-Uji')
        ->assertSee('Klasifikasi ditetapkan Change Manager')
        ->assertSee('Emergency belum dikonfirmasi')
        ->assertSeeHtml('data-risk-level="unknown"')
        ->assertSeeHtml('data-priority="high"');
});

it('menghitung indikator target dalam hari penuh zona waktu Jakarta', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00', 'Asia/Jakarta'));

    $cases = [
        ['2026-11-02 09:00:00', '5 hari lagi', 'neutral', 'upcoming'],
        ['2026-10-31 09:00:00', '3 hari lagi', 'warning', 'due-soon'],
        ['2026-10-29 09:00:00', '1 hari lagi', 'warning', 'due-soon'],
        ['2026-10-28 09:00:00', 'Hari ini', 'warning', 'due-soon'],
        ['2026-10-25 09:00:00', 'Terlambat 3 hari', 'danger', 'overdue'],
    ];

    foreach ($cases as [$target, $label, $tone, $state]) {
        $rfc = ChangeRequest::factory()->make([
            'status' => RfcStatus::TICKET_CREATED->value,
            'target_implementation_date' => Carbon::parse($target, 'Asia/Jakarta'),
        ]);

        $indicator = $rfc->targetDateIndicator();

        expect($indicator)->not->toBeNull()
            ->and($indicator['label'])->toBe($label)
            ->and($indicator['tone'])->toBe($tone)
            ->and($indicator['state'])->toBe($state);
    }
});

it('menyembunyikan indikator target untuk draft, status akhir, dan target kosong', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00', 'Asia/Jakarta'));

    foreach ([RfcStatus::DRAFT, RfcStatus::CLOSED, RfcStatus::REJECTED, RfcStatus::CANCELLED] as $status) {
        $rfc = ChangeRequest::factory()->make([
            'status' => $status->value,
            'target_implementation_date' => Carbon::parse('2026-10-30 09:00:00', 'Asia/Jakarta'),
        ]);

        expect($rfc->targetDateIndicator())->toBeNull();
    }

    $withoutTarget = ChangeRequest::factory()->make([
        'status' => RfcStatus::TICKET_CREATED->value,
        'target_implementation_date' => null,
    ]);

    expect($withoutTarget->targetDateIndicator())->toBeNull();
});

it('menghitung tengah malam WIB sebagai hari yang sama dan menampilkan usia RFC', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-28 23:45:00', 'Asia/Jakarta'));

    $rfc = ChangeRequest::factory()->make([
        'status' => RfcStatus::TICKET_CREATED->value,
        'target_implementation_date' => Carbon::parse('2026-10-29 00:30:00', 'Asia/Jakarta'),
        'created_at' => Carbon::parse('2026-10-24 23:50:00', 'Asia/Jakarta'),
    ]);

    expect($rfc->targetDateIndicator())
        ->toMatchArray(['label' => '1 hari lagi', 'days' => 1])
        ->and($rfc->runningDays())->toBe(4);
});

it('menampilkan indikator target dan usia RFC pada baris ringkasan detail', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00', 'Asia/Jakarta'));

    $requester = rfcPresentationUser('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => 'normal',
        'target_implementation_date' => Carbon::parse('2026-10-30 09:00:00', 'Asia/Jakarta'),
        'created_at' => Carbon::parse('2026-10-24 09:00:00', 'Asia/Jakarta'),
    ]);

    $newRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
        'change_type' => 'normal',
        'target_implementation_date' => null,
        'created_at' => Carbon::parse('2026-10-28 09:00:00', 'Asia/Jakarta'),
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Target: 2 hari lagi')
        ->assertSee('Sudah berjalan 4 hari');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $newRfc->fresh()])
        ->assertSee('Baru diajukan')
        ->assertDontSee('Sudah berjalan 0 hari');
});

it('mengembalikan posisi RFC sesuai status dan sub-tahap', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager');

    $cases = [
        [RfcStatus::DRAFT, 'Pemohon', 'belum diajukan'],
        [RfcStatus::NEEDS_REVISION, 'Pemohon', 'revisi'],
        [RfcStatus::SUBMITTED, 'Admin', 'pemeriksaan'],
        [RfcStatus::RETURNED_TO_ADMIN, 'Admin', 'pemeriksaan'],
        [RfcStatus::IN_PROGRESS, 'Tim Rilis', 'pelaksanaan'],
    ];

    foreach ($cases as [$status, $role, $stage]) {
        $rfc = ChangeRequest::factory()->create([
            'submitted_by_id' => $requester->id,
            'created_by_id' => $requester->id,
            'status' => $status->value,
        ]);

        expect($rfc->fresh()->currentPosition())
            ->toMatchArray(['role' => $role, 'stage' => $stage]);
    }

    $ticketRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    expect($ticketRfc->fresh()->currentPosition())->toMatchArray([
        'role' => 'Change Manager',
        'stage' => 'analisis risiko',
    ]);

    rfcPresentationRisk($ticketRfc, $manager);

    expect($ticketRfc->fresh()->load('riskAssessment')->currentPosition())->toMatchArray([
        'role' => 'Change Manager',
        'stage' => 'keputusan',
    ]);

    $approvedRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    expect($approvedRfc->fresh()->currentPosition())->toMatchArray([
        'role' => 'Change Manager',
        'stage' => 'disposisi',
    ]);

    $member = ReleaseTeamMember::factory()->create();
    ReleaseTask::factory()->forRfc($approvedRfc)->assignedTo($member)->create();

    expect($approvedRfc->fresh()->load('releaseTasks')->currentPosition())->toMatchArray([
        'role' => 'Tim Rilis',
        'stage' => 'pelaksanaan',
    ]);

    $implementedRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::IMPLEMENTED->value,
    ]);
    rfcPresentationImplementation($implementedRfc, $manager);

    expect($implementedRfc->fresh()->load('implementation')->currentPosition())->toMatchArray([
        'role' => 'Change Manager',
        'stage' => 'evaluasi',
    ]);

    $implementedRfc->implementation()->update(['evaluated_at' => now(), 'evaluated_by_id' => $manager->id]);

    expect($implementedRfc->fresh()->load('implementation')->currentPosition())->toMatchArray([
        'role' => 'Change Manager',
        'stage' => 'penutupan',
    ]);

    foreach ([RfcStatus::CLOSED, RfcStatus::REJECTED, RfcStatus::CANCELLED] as $status) {
        $finalRfc = ChangeRequest::factory()->create([
            'submitted_by_id' => $requester->id,
            'status' => $status->value,
        ]);

        expect($finalRfc->fresh()->currentPosition())->toBeNull();
    }

    $legacyWaitingRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $legacyWaitingRfc->fresh()])
        ->assertSee('Menunggu pemeriksaan');

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $legacyWaitingRfc->fresh()])
        ->assertSee('Menunggu pemeriksaan');
});

it('menyembunyikan sub-tahap dari Pemohon dan tidak menampilkan nama orang', function () {
    $requester = rfcPresentationUser('Pemohon');
    $manager = rfcPresentationUser('Change Manager', ['name' => 'Nama-Staf-Rahasia']);
    $staffOnly = User::factory()->create(['name' => 'Staf-Tanpa-Nama']);
    $staffOnly->givePermissionTo('rfc.view_all');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Menunggu: Change Manager')
        ->assertDontSee('analisis risiko')
        ->assertDontSee('keputusan')
        ->assertDontSee('Nama-Staf-Rahasia');

    $this->actingAs($staffOnly);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Posisi saat ini:')
        ->assertSee('Change Manager')
        ->assertSee('analisis risiko')
        ->assertDontSee('Nama-Staf-Rahasia');

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Posisi saat ini:')
        ->assertSee('analisis risiko');
});

it('menampilkan RFC lama tanpa kategori, target, atau analisis tanpa error dan tanpa tanda minus', function () {
    $requester = rfcPresentationUser('Pemohon');
    $admin = rfcPresentationUser('Admin');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::LEGACY_COMPLETE,
        'change_type' => null,
        'target_implementation_date' => null,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertDontSee('Kategori Perubahan')
        ->assertDontSee('Target Tanggal Pelaksanaan')
        ->assertDontSee('Penilaian Risiko (Change Manager)');

    $this->actingAs($admin);

    $adminHtml = Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Belum dikategorikan')
        ->assertDontSee('Tidak tercatat')
        ->assertDontSee('Kategori Perubahan')
        ->assertDontSee('Target Tanggal Pelaksanaan')
        ->assertDontSee('Penilaian Risiko (Change Manager)')
        ->html();

    expect($adminHtml)->not->toContain('>-<');
});
