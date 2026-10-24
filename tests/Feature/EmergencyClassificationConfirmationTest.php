<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\AssessRisk;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->cm = User::factory()->create(['name' => 'Change Manager Emergency']);
    $this->cm->assignRole('Change Manager');

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Emergency']);
    $this->pemohon->assignRole('Pemohon');

    $this->admin = User::factory()->create(['name' => 'Admin Emergency']);
    $this->admin->assignRole('Admin');

    $this->makeRfc = function (array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => RfcStatus::TICKET_CREATED->value,
            'change_type' => 'normal',
            'emergency_unconfirmed' => false,
        ], $attributes));
    };

    $this->makeRisk = function (ChangeRequest $rfc, array $attributes = []): void {
        RiskAssessment::create(array_merge([
            'change_request_id' => $rfc->id,
            'assessed_by_id' => $this->cm->id,
            'system_criticality_score' => 2,
            'impact_scope_score' => 2,
            'rollback_readiness_score' => 1,
            'total_score' => 6,
            'risk_level' => 'medium',
            'mitigation_plan' => 'Mitigasi contoh.',
            'rollback_plan' => 'Rollback contoh.',
        ], $attributes));
    };

    $this->submitRisk = function (ChangeRequest $rfc, string $classification) {
        return Livewire::actingAs($this->cm)
            ->test(AssessRisk::class, ['rfc' => $rfc])
            ->set('risk_level', 'Sedang')
            ->set('impact_level', 'Sedang')
            ->set('urgency_level', 'Normal')
            ->set('classification', $classification)
            ->set('analysis_notes', 'Catatan analisis risiko untuk pengujian emergency.')
            ->call('submitAssessment');
    };
});

it('mengisi klasifikasi bawaan dari kategori Pemohon dan menampilkan peringatan emergency', function () {
    $emergencyRfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);
    $normalRfc = ($this->makeRfc)([
        'change_type' => 'normal',
        'emergency_unconfirmed' => false,
    ]);

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $emergencyRfc])
        ->assertSet('classification', 'Emergency Change')
        ->assertSee('Kategori yang diusulkan Pemohon:')
        ->assertSee('Pemohon mengusulkan kategori Emergency. Konfirmasi dengan mempertahankan klasifikasi Emergency Change, atau pilih klasifikasi lain bila tidak memenuhi syarat darurat.');

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $normalRfc])
        ->assertSet('classification', 'Normal Change')
        ->assertSee('Kategori yang diusulkan Pemohon:')
        ->assertDontSee('Pemohon mengusulkan kategori Emergency. Konfirmasi dengan mempertahankan klasifikasi Emergency Change, atau pilih klasifikasi lain bila tidak memenuhi syarat darurat.');
});

it('mengonfirmasi Emergency Change dan membersihkan flag tanpa mengubah change_type', function () {
    $rfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    ($this->submitRisk)($rfc, 'Emergency Change')->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'Kategori Emergency dikonfirmasi oleh Change Manager')
        ->first();

    expect($fresh->emergency_unconfirmed)->toBeFalse()
        ->and($fresh->change_type)->toBe('emergency')
        ->and($fresh->riskAssessment)->not->toBeNull()
        ->and($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'proposed_category'))->toBe('Emergency')
        ->and(data_get($activity->properties, 'classification'))->toBe('Emergency Change');
});

it('mengubah usulan Emergency menjadi Normal Change dan mencatat perubahannya', function () {
    $rfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    ($this->submitRisk)($rfc, 'Normal Change')->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'Usulan Emergency diubah Change Manager menjadi Normal Change')
        ->first();

    expect($fresh->emergency_unconfirmed)->toBeFalse()
        ->and($fresh->change_type)->toBe('emergency')
        ->and($fresh->riskAssessment->classification)->toBe('Normal Change')
        ->and($activity)->not->toBeNull();
});

it('tetap memakai klasifikasi analisis yang sudah ada saat halaman dibuka', function () {
    $rfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);
    ($this->makeRisk)($rfc, [
        'classification' => 'Standard Change',
        'impact_level' => 'Sedang',
        'urgency_level' => 'Normal',
        'analysis_notes' => 'Analisis lama yang harus dipertahankan.',
    ]);

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $rfc])
        ->assertSet('classification', 'Standard Change')
        ->assertSet('analysis_notes', 'Analisis lama yang harus dipertahankan.');
});

it('label opsi tidak menyebut CAB atau ECAB dan nilai opsi tetap', function () {
    $rfc = ($this->makeRfc)();

    $response = $this->actingAs($this->cm)
        ->get(route('change-requests.assess-risk', $rfc))
        ->assertOk()
        ->assertSee('Normal Change (Melalui review dan persetujuan Change Manager)')
        ->assertSee('Emergency Change (Penanganan segera dan diprioritaskan)')
        ->assertDontSee('CAB')
        ->assertDontSee('ECAB');

    $content = $response->getContent();

    expect($content)->toContain('value="Normal Change"')
        ->and($content)->toContain('value="Emergency Change"');
});

it('menampilkan usulan dan klasifikasi terpisah untuk staf tanpa membocorkannya ke Pemohon', function () {
    $rfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'emergency_unconfirmed' => true,
    ]);

    ($this->submitRisk)($rfc, 'Normal Change')->assertHasNoErrors();

    Livewire::actingAs($this->cm)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Kategori diusulkan Pemohon')
        ->assertSee('Klasifikasi ditetapkan Change Manager')
        ->assertSee('Normal Change');

    $staffHistory = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $staffRiskEvent = collect($staffHistory['events'])->firstWhere('key', 'risk_assessment');

    expect($staffRiskEvent)->not->toBeNull()
        ->and($staffRiskEvent['note'])->toContain('Usulan Emergency diubah Change Manager menjadi Normal Change');

    Livewire::actingAs($this->pemohon)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertDontSee('Kategori diusulkan Pemohon')
        ->assertDontSee('Klasifikasi ditetapkan Change Manager')
        ->assertDontSee('Usulan Emergency diubah Change Manager menjadi Normal Change');

    $ownerHistory = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $ownerRiskEvent = collect($ownerHistory['events'])->firstWhere('key', 'risk_assessment');

    expect($ownerRiskEvent)->not->toBeNull()
        ->and($ownerRiskEvent['note'])->toBeNull();
});

it('menolak Admin dan Pemohon serta analisis di luar status ticket created', function () {
    $rfc = ($this->makeRfc)();

    $this->actingAs($this->admin)
        ->get(route('change-requests.assess-risk', $rfc))
        ->assertForbidden();

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.assess-risk', $rfc))
        ->assertForbidden();

    $submittedRfc = ($this->makeRfc)(['status' => RfcStatus::SUBMITTED->value]);

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $submittedRfc])
        ->set('risk_level', 'Sedang')
        ->set('impact_level', 'Sedang')
        ->set('urgency_level', 'Normal')
        ->set('classification', 'Normal Change')
        ->set('analysis_notes', 'Analisis risiko di luar status ticket created.')
        ->call('submitAssessment')
        ->assertHasErrors(['status']);

    expect($submittedRfc->fresh()->riskAssessment)->toBeNull();
});
