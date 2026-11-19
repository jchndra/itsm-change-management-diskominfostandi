<?php

use App\Enums\DecisionStatus;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use App\Livewire\ChangeRequests\ProcessHistoryTimeline;
use App\Livewire\ChangeRequests\ReviewCompleteness;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Requester\Dashboard;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Decision;
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
        ->assertSee('Ditolak')
        ->assertDontSee('Ditolak pada tahap pemeriksaan')
        ->assertSee('Ditolak karena dokumen tidak lengkap.')
        ->assertDontSee('Disetujui oleh Change Manager')
        ->assertDontSee('Persetujuan Pimpinan (Eselon II)')
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

    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $this->authority->id,
        'recorded_by_id' => $this->cm->id,
        'snapshot_authority_name' => $this->authority->name,
        'snapshot_authority_position' => $this->authority->position,
        'decision_status' => DecisionStatus::RETURNED_TO_ADMIN->value,
        'decision_date' => now(),
        'reason' => 'Catatan internal CM untuk Admin.',
    ]);
    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::RETURNED_TO_ADMIN, $this->cm, 'Catatan internal CM untuk Admin.');

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Sedang ditinjau')
        ->assertDontSee('Dikembalikan untuk pemeriksaan')
        ->assertDontSee('Disetujui oleh Change Manager')
        ->assertDontSee('Persetujuan Pimpinan (Eselon II)');
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

    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $this->authority->id,
        'recorded_by_id' => $this->cm->id,
        'snapshot_authority_name' => $this->authority->name,
        'snapshot_authority_position' => $this->authority->position,
        'decision_status' => DecisionStatus::RETURNED_TO_ADMIN->value,
        'decision_date' => now(),
        'reason' => $internalNote,
    ]);
    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::RETURNED_TO_ADMIN, $this->cm, $internalNote);

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertDontSee($internalNote);

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Dikembalikan untuk pemeriksaan')
        ->assertSee($internalNote)
        ->assertSee($this->cm->name);
});

test('penolakan oleh Admin tidak ditampilkan sebagai baris riwayat', function () {
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

    // Baris penolakan oleh Admin hilang; status akhir tetap tampil netral
    // tanpa nama pelaku melalui ensureCurrentStatusEvent().
    expect($rejected)->not->toBeNull()
        ->and($rejected['label'])->toBe('Ditolak')
        ->and($rejected['actor_name'])->toBeNull();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Ditolak')
        ->assertDontSee($this->admin->name)
        ->assertSee($note);
});

test('kejadian Admin disembunyikan dan pengajuan legacy tetap tampil sebagai kejadian Pemohon', function () {
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::APPROVED->value,
        'ticket_number' => 'CHG-2026-0100',
        'ticket_created_at' => now()->subHour(),
    ]);

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->pemohon)
        ->withProperties(['old_status' => null, 'new_status' => RfcStatus::DRAFT->value])
        ->log('Draft RFC disimpan');

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->admin)
        ->withProperties([
            'event' => 'ticket_created',
            'ticket_number' => 'CHG-2026-0100',
            'priority' => TicketPriority::HIGH->value,
            'category' => ServiceCategory::SECURITY->value,
        ])
        ->log('Tiket Change dibuat');

    $events = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events']);
    $submission = $events->firstWhere('key', RfcStatus::SUBMITTED->value);

    expect($events->pluck('label')->implode(' | '))->not->toContain('Tiket Change')
        ->and($events->pluck('actor_name')->contains($this->admin->name))->toBeFalse()
        ->and($submission)->not->toBeNull()
        ->and($submission['actor_name'])->toBe($this->pemohon->name)
        ->and($submission['actor_role'])->toBe('Pemohon');
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

test('riwayat proses pemohon mengikuti aksi Change Manager tanpa muat ulang', function () {
    $rfc = ($this->makeRfc)(['title' => 'RFC Integrasi Riwayat']);

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->pemohon)
        ->withProperties(['old_status' => null, 'new_status' => RfcStatus::SUBMITTED->value])
        ->log('RFC resmi diajukan');

    $this->actingAs($this->pemohon);

    $component = Livewire::test(ProcessHistoryTimeline::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('RFC diajukan')
        ->assertDontSee('Diverifikasi dan diteruskan ke Change Manager');

    expect($component->html())->toContain('wire:poll.30s.visible="refreshProcessHistory"');

    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::TICKET_CREATED, $this->admin);

    // Transisi yang dicatat Admin tidak lagi muncul pada riwayat proses.
    $component->call('refreshProcessHistory')
        ->assertDontSee('Diverifikasi dan diteruskan ke Change Manager')
        ->assertDontSee($this->admin->name);

    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::APPROVED, $this->cm);

    $component->call('refreshProcessHistory')
        ->assertSee('Disetujui oleh Change Manager');
});

test('timeline dashboard pemohon ikut diperbarui otomatis setelah status berubah', function () {
    $rfc = ($this->makeRfc)(['status' => RfcStatus::TICKET_CREATED->value]);

    $component = Livewire::actingAs($this->pemohon)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee('Menunggu keputusan Change Manager');

    expect($component->html())->toContain('wire:poll.30s');

    RfcStatusTransition::transitionTo($rfc->fresh(), RfcStatus::APPROVED, $this->cm);

    expect($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value);

    $component->call('$refresh')
        ->assertSee('Review selesai')
        ->assertDontSee('Menunggu keputusan Change Manager');
});

test('catatan riwayat dipecah menjadi baris label dan nilai untuk tampilan', function () {
    $rfc = ($this->makeRfc)();

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->cm)
        ->withProperties([
            'event' => 'ticket_created',
            'ticket_number' => 'CHG-2026-0099',
            'priority' => TicketPriority::HIGH->value,
            'category' => ServiceCategory::SECURITY->value,
            'admin_note' => 'Catatan untuk Change Manager.',
        ])
        ->log('Tiket dibuat');

    $event = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events'])
        ->firstWhere('key', RfcStatus::TICKET_CREATED->value);

    expect($event)->not->toBeNull()
        ->and($event['details'])->toContain(['label' => 'Prioritas', 'value' => 'Tinggi', 'long' => false])
        ->and($event['details'])->toContain(['label' => 'Kategori layanan', 'value' => 'Keamanan', 'long' => false])
        ->and($event['details'])->toContain(['label' => 'Catatan', 'value' => 'Catatan untuk Change Manager.', 'long' => false]);
});

test('rincian level risiko gabungan dipecah menjadi beberapa baris', function () {
    $rfc = ($this->makeRfc)();
    ($this->makeRisk)($rfc);

    $event = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events'])
        ->firstWhere('key', 'risk_assessment');

    expect($event['details'])->toContain(['label' => 'Risiko', 'value' => 'Sedang', 'long' => false])
        ->and($event['details'])->toContain(['label' => 'Dampak', 'value' => 'Sedang', 'long' => false])
        ->and($event['details'])->toContain(['label' => 'Klasifikasi', 'value' => 'Normal Change', 'long' => false]);
});

test('nilai catatan yang panjang ditandai agar dapat dilipat pada tampilan', function () {
    $rfc = ($this->makeRfc)();

    activity('rfc')
        ->performedOn($rfc)
        ->causedBy($this->cm)
        ->withProperties([
            'event' => 'evaluation_recorded',
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'evaluation_notes' => str_repeat('Rincian evaluasi panjang. ', 6),
        ])
        ->log('Evaluasi dicatat');

    $details = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events'])
        ->firstWhere('key', 'evaluation_recorded')['details'];

    $evaluation = collect($details)->firstWhere('label', 'Catatan evaluasi');

    expect($evaluation)->not->toBeNull()
        ->and($evaluation['long'])->toBeTrue()
        ->and(collect($details)->firstWhere('label', 'Hasil implementasi')['long'])->toBeFalse();
});

test('widget dashboard tidak menampilkan RFC closed, rejected, atau draft', function () {
    $active = ($this->makeRfc)(['title' => 'RFC Aktif Widget']);
    $closed = ($this->makeRfc)(['title' => 'RFC Ditutup Widget', 'status' => RfcStatus::CLOSED->value]);
    $rejected = ($this->makeRfc)(['title' => 'RFC Ditolak Widget', 'status' => RfcStatus::REJECTED->value]);
    $draft = ($this->makeRfc)(['title' => 'RFC Draft Widget', 'status' => RfcStatus::DRAFT->value]);

    DB::table('change_requests')->where('id', $draft->id)->update(['updated_at' => now()->addMinutes(6)]);
    DB::table('change_requests')->where('id', $closed->id)->update(['updated_at' => now()->addMinutes(5)]);
    DB::table('change_requests')->where('id', $rejected->id)->update(['updated_at' => now()->addMinutes(4)]);
    DB::table('change_requests')->where('id', $active->id)->update(['updated_at' => now()->subMinute()]);

    Livewire::actingAs($this->pemohon)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertViewHas('latestActiveRfc', fn (?ChangeRequest $rfc): bool => $rfc?->id === $active->id)
        ->assertSee('Diajukan')
        ->assertSee('Ditinjau Change Manager')
        ->assertSee('Dikerjakan Tim Rilis')
        ->assertSee('Evaluasi')
        ->assertSee('Ditutup')
        ->assertDontSee('Selesai');
});
