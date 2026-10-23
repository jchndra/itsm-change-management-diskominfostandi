<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\AssessRisk;
use App\Livewire\ChangeRequests\RecordDecision;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\User;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->makeRfc = function (array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'status' => RfcStatus::TICKET_CREATED->value,
        ], $attributes));
    };

    $this->makeRisk = function (ChangeRequest $rfc, User $cm): void {
        $rfc->riskAssessment()->create([
            'assessed_by_id' => $cm->id,
            'system_criticality_score' => 3,
            'impact_scope_score' => 3,
            'rollback_readiness_score' => 2,
            'total_score' => 8,
            'risk_level' => 'medium',
            'mitigation_plan' => 'Mitigasi contoh',
            'rollback_plan' => 'Rollback contoh',
        ]);
    };
});

test('change manager dapat mengisi analisis risiko tanpa mengubah status ticket created', function () {
    $cm = User::factory()->create();
    $cm->assignRole('Change Manager');

    $pemohon = User::factory()->create();

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    $this->actingAs($cm);

    Livewire::test(AssessRisk::class, ['rfc' => $rfc])
        ->set('risk_level', 'Sedang')
        ->set('impact_level', 'Sedang')
        ->set('urgency_level', 'Normal')
        ->set('classification', 'Normal Change')
        ->set('analysis_notes', 'Rencana mitigasi penggantian server cadangan dan pengujian pada staging.')
        ->call('submitAssessment')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value);
    expect($rfc->fresh()->riskAssessment)->not->toBeNull();
});

test('keputusan baru menyimpan authority_id null dan snapshot dari change manager yang login', function () {
    $cm = User::factory()->create([
        'name' => 'Change Manager Uji',
        'nip' => '198501012015011002',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $cm->assignRole('Change Manager');

    $pemohon = User::factory()->create();
    $rfc = ($this->makeRfc)(['submitted_by_id' => $pemohon->id]);
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui berdasarkan hasil evaluasi teknis.')
        ->call('saveDecision')
        ->assertHasNoErrors();

    $decision = $rfc->fresh()->decisions()->firstOrFail();

    expect($decision->authority_id)->toBeNull()
        ->and($decision->snapshot_authority_name)->toBe('Change Manager Uji')
        ->and($decision->snapshot_authority_nip)->toBe('198501012015011002')
        ->and($decision->snapshot_authority_position)->toBe('Pengendali Perubahan IT')
        ->and($decision->recorded_by_id)->toBe($cm->id)
        ->and($decision->decision_status)->toBe(DecisionStatus::APPROVED->value)
        ->and($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value);
});

test('jabatan kosong memakai fallback Change Manager dan NIP kosong disimpan null', function () {
    $cm = User::factory()->create([
        'name' => 'CM Tanpa Jabatan',
        'nip' => null,
        'position' => null,
    ]);
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui tanpa jabatan pada profil.')
        ->call('saveDecision')
        ->assertHasNoErrors();

    $decision = $rfc->fresh()->decisions()->firstOrFail();

    expect($decision->snapshot_authority_name)->toBe('CM Tanpa Jabatan')
        ->and($decision->snapshot_authority_nip)->toBeNull()
        ->and($decision->snapshot_authority_position)->toBe('Change Manager');
});

test('form keputusan tidak memuat pilihan pejabat dan tidak menampilkan teks CAB atau ECAB', function () {
    $cm = User::factory()->create([
        'name' => 'CM Form Keputusan',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();

    $this->actingAs($cm);

    $this->get(route('change-requests.record-decision', $rfc))
        ->assertOk()
        ->assertSee('Keputusan Change Manager')
        ->assertSee('Dicatat atas nama: CM Form Keputusan, Pengendali Perubahan IT')
        ->assertDontSee('Pejabat Penyetuju')
        ->assertDontSee('Pejabat Berwenang')
        ->assertDontSee('authorityId')
        ->assertDontSee('CAB')
        ->assertDontSee('ECAB');
});

test('catatan keputusan tetap wajib diisi', function () {
    $cm = User::factory()->create();
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', '')
        ->call('saveDecision')
        ->assertHasErrors(['notes']);

    expect($rfc->fresh()->decisions()->count())->toBe(0)
        ->and($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value);
});

test('keputusan ditolak jika analisis risiko belum ada', function () {
    $cm = User::factory()->create();
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui berdasarkan hasil evaluasi teknis.')
        ->call('saveDecision')
        ->assertHasErrors(['decisionStatus']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->fresh()->decisions()->count())->toBe(0);
});

test('keputusan lama dengan authority tampil untuk staf dan identitas pejabat disembunyikan dari pemohon', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $authority = Authority::factory()->create([
        'name' => 'Pejabat Penyetuju A',
        'position' => 'Kepala Bidang IT Sample',
    ]);

    $rfc = ($this->makeRfc)([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $authority->id,
        'recorded_by_id' => $admin->id,
        'snapshot_authority_name' => $authority->name,
        'snapshot_authority_position' => $authority->position,
        'decision_status' => DecisionStatus::APPROVED->value,
        'decision_date' => now(),
        'reason' => 'Keputusan lama berdasarkan rapat pejabat.',
    ]);

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Pejabat Penyetuju A')
        ->assertSee('Kepala Bidang IT Sample')
        ->assertSee('Keputusan lama berdasarkan rapat pejabat.');

    $this->actingAs($pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Disetujui')
        ->assertSee('Keputusan lama berdasarkan rapat pejabat.')
        ->assertDontSee('Pejabat Penyetuju A')
        ->assertDontSee('Kepala Bidang IT Sample');
});

test('keputusan baru tanpa authority dapat dirender untuk staf dan pemohon', function () {
    $cm = User::factory()->create([
        'name' => 'CM Tanpa Authority',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $cm->assignRole('Change Manager');

    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $rfc = ($this->makeRfc)(['submitted_by_id' => $pemohon->id]);
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui tanpa data pejabat lama.')
        ->call('saveDecision')
        ->assertHasNoErrors();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('CM Tanpa Authority')
        ->assertSee('Pengendali Perubahan IT')
        ->assertSee('Disetujui tanpa data pejabat lama.');

    $this->actingAs($pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Disetujui')
        ->assertSee('Disetujui tanpa data pejabat lama.')
        ->assertDontSee('CM Tanpa Authority')
        ->assertDontSee('Pengendali Perubahan IT');
});

test('admin dan pemohon mendapat 403 di halaman keputusan', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $pemohon = User::factory()->create();
    $pemohon->assignRole('Pemohon');

    $rfc = ($this->makeRfc)(['submitted_by_id' => $pemohon->id]);

    $this->actingAs($admin)
        ->get(route('change-requests.record-decision', $rfc))
        ->assertForbidden();

    $this->actingAs($pemohon)
        ->get(route('change-requests.record-decision', $rfc))
        ->assertForbidden();
});

test('transisi keputusan approved rejected dan returned_to_admin tetap berjalan', function (DecisionStatus $decisionStatus, RfcStatus $targetStatus) {
    $cm = User::factory()->create([
        'name' => 'CM Transisi',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', $decisionStatus->value)
        ->set('notes', 'Catatan transisi '.$decisionStatus->value)
        ->call('saveDecision')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe($targetStatus->value)
        ->and($rfc->fresh()->decisions()->firstOrFail()->decision_status)->toBe($decisionStatus->value);
})->with([
    'approved' => [DecisionStatus::APPROVED, RfcStatus::APPROVED],
    'rejected' => [DecisionStatus::REJECTED, RfcStatus::REJECTED],
    'returned to admin' => [DecisionStatus::RETURNED_TO_ADMIN, RfcStatus::RETURNED_TO_ADMIN],
]);

test('riwayat proses staf menyebut nama change manager dari snapshot', function () {
    $cm = User::factory()->create([
        'name' => 'CM Riwayat Snapshot',
        'position' => 'Pengendali Perubahan IT',
    ]);
    $cm->assignRole('Change Manager');

    $rfc = ($this->makeRfc)();
    ($this->makeRisk)($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui untuk pengujian riwayat.')
        ->call('saveDecision')
        ->assertHasNoErrors();

    $staffHistory = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $staffApproved = collect($staffHistory['events'])->firstWhere('key', RfcStatus::APPROVED->value);

    expect($staffApproved)->not->toBeNull()
        ->and($staffApproved['label'])->toBe('Disetujui oleh Change Manager — CM Riwayat Snapshot')
        ->and($staffApproved['actor_name'])->toBe('CM Riwayat Snapshot');

    $ownerHistory = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $ownerApproved = collect($ownerHistory['events'])->firstWhere('key', RfcStatus::APPROVED->value);

    expect($ownerApproved)->not->toBeNull()
        ->and($ownerApproved['label'])->toBe('Disetujui oleh Change Manager')
        ->and($ownerApproved['actor_name'])->toBeNull();
});
