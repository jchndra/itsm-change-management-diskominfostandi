<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\RecordDecision;
use App\Livewire\ChangeRequests\ReviewCompleteness;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Requester\Dashboard;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\RfcHistoryService;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Riwayat']);
    $this->pemohon->assignRole('Pemohon');

    $this->admin = User::factory()->create(['name' => 'Admin Riwayat']);
    $this->admin->assignRole('Admin');

    $this->cm = User::factory()->create(['name' => 'Change Manager Riwayat']);
    $this->cm->assignRole('Change Manager');

    $this->authority = Authority::factory()->create();

    $this->makeRfc = function (array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => RfcStatus::SUBMITTED->value,
        ], $attributes));
    };

    $this->makeRisk = function (ChangeRequest $rfc): void {
        RiskAssessment::create([
            'change_request_id' => $rfc->id,
            'assessed_by_id' => $this->cm->id,
            'system_criticality_score' => 2,
            'impact_scope_score' => 2,
            'rollback_readiness_score' => 1,
            'total_score' => 7,
            'risk_level' => 'Sedang',
            'impact_level' => 'Sedang',
            'urgency_level' => 'Normal',
            'classification' => 'Normal Change',
            'analysis_notes' => 'Analisis risiko untuk pengujian riwayat.',
            'mitigation_plan' => 'Rencana mitigasi untuk pengujian.',
            'rollback_plan' => 'Rencana rollback untuk pengujian.',
        ]);
    };
});

test('rejected RFC tidak menampilkan persetujuan dan tidak punya langkah mendatang', function () {
    $rfc = ($this->makeRfc)(['title' => 'RFC Ditolak Uji']);

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->pemohon)
        ->withProperties(['old_status' => null, 'new_status' => RfcStatus::SUBMITTED->value])
        ->log('RFC resmi diajukan');

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $this->admin);
    ($this->makeRisk)($rfc);
    RfcStatusTransition::transitionTo($rfc, RfcStatus::RETURNED_TO_ADMIN, $this->cm, 'Catatan internal CM untuk Admin.');
    RfcStatusTransition::transitionTo($rfc, RfcStatus::REJECTED, $this->admin, 'Ditolak karena dokumen tidak lengkap.');
    $rfc->update(['rejection_note' => 'Ditolak karena dokumen tidak lengkap.']);

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Ditolak oleh Admin')
        ->assertSee('Ditolak karena dokumen tidak lengkap.')
        ->assertDontSee('Disetujui')
        ->assertDontSee('Persetujuan')
        ->assertDontSee('Langkah Berikutnya');
});

test('keputusan returned_to_admin tidak menandai persetujuan', function () {
    $rfc = ($this->makeRfc)();

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->pemohon)
        ->withProperties(['old_status' => null, 'new_status' => RfcStatus::SUBMITTED->value])
        ->log('RFC resmi diajukan');

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $this->admin);
    ($this->makeRisk)($rfc);

    Livewire::actingAs($this->cm)
        ->test(RecordDecision::class, ['rfc' => $rfc->fresh()])
        ->set('decisionStatus', DecisionStatus::RETURNED_TO_ADMIN->value)
        ->set('notes', 'Catatan internal CM untuk Admin.')
        ->call('saveDecision')
        ->assertHasNoErrors();

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Sedang ditinjau')
        ->assertSee('Dikembalikan ke Admin')
        ->assertDontSee('Disetujui')
        ->assertDontSee('Persetujuan');
});

test('tampilan Pemohon menyembunyikan catatan internal CM', function () {
    $rfc = ($this->makeRfc)();
    $internalNote = 'Catatan internal CM yang tidak boleh dilihat Pemohon.';

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->pemohon)
        ->withProperties(['old_status' => null, 'new_status' => RfcStatus::SUBMITTED->value])
        ->log('RFC resmi diajukan');

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $this->admin);
    ($this->makeRisk)($rfc);

    Livewire::actingAs($this->cm)
        ->test(RecordDecision::class, ['rfc' => $rfc->fresh()])
        ->set('decisionStatus', DecisionStatus::RETURNED_TO_ADMIN->value)
        ->set('notes', $internalNote)
        ->call('saveDecision')
        ->assertHasNoErrors();

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertDontSee($internalNote);

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Dikembalikan ke Admin')
        ->assertSee($internalNote)
        ->assertSee($this->cm->name);
});

test('penolakan oleh Admin tercatat dengan pelaku dan catatan', function () {
    $rfc = ($this->makeRfc)(['status' => RfcStatus::RETURNED_TO_ADMIN->value]);
    $note = 'Tidak memenuhi ketentuan administrasi.';

    $this->actingAs($this->admin);

    Livewire::test(ReviewCompleteness::class, ['rfc' => $rfc])
        ->set('action', RfcStatus::REJECTED->value)
        ->set('note', $note)
        ->call('submitReview')
        ->assertHasNoErrors();

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $rejected = collect($history['events'])->firstWhere('key', RfcStatus::REJECTED->value);

    expect($rejected)->not->toBeNull()
        ->and($rejected['label'])->toBe('Ditolak oleh Admin')
        ->and($rejected['note'])->toBe($note)
        ->and($rejected['actor_name'])->toBe($this->admin->name);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Ditolak oleh Admin')
        ->assertSee($this->admin->name)
        ->assertSee($note);
});

test('RFC dengan status legacy yang tidak dikenal tetap dapat dirender', function () {
    $rfc = ($this->makeRfc)(['status' => 'mystery_status']);

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->admin)
        ->withProperties(['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => 'mystery_status'])
        ->log('Status lama tidak dikenal.');

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Pembaruan status (legacy)')
        ->assertSee('Mystery status');
});

test('widget dashboard tidak menampilkan RFC berstatus closed atau rejected', function () {
    $active = ($this->makeRfc)(['title' => 'RFC Aktif Widget']);
    $closed = ($this->makeRfc)(['title' => 'RFC Ditutup Widget', 'status' => RfcStatus::CLOSED->value]);
    $rejected = ($this->makeRfc)(['title' => 'RFC Ditolak Widget', 'status' => RfcStatus::REJECTED->value]);

    DB::table('change_requests')->where('id', $closed->id)->update(['updated_at' => now()->addMinutes(5)]);
    DB::table('change_requests')->where('id', $rejected->id)->update(['updated_at' => now()->addMinutes(4)]);
    DB::table('change_requests')->where('id', $active->id)->update(['updated_at' => now()->subMinute()]);

    Livewire::actingAs($this->pemohon)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertViewHas('latestActiveRfc', fn (?ChangeRequest $rfc): bool => $rfc?->id === $active->id)
        ->assertSee('Diajukan')
        ->assertSee('Diperiksa Admin')
        ->assertSee('Review Change Manager')
        ->assertSee('Dikerjakan')
        ->assertSee('Selesai');
});
