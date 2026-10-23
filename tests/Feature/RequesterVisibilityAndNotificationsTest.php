<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Notifications;
use App\Livewire\Requester\Dashboard;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\User;
use App\Notifications\RfcStatusChangedNotification;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create([
        'name' => 'Pemohon Notifikasi',
        'position' => 'Staf Operasional',
    ]);
    $this->pemohon->assignRole('Pemohon');

    $this->admin = User::factory()->create(['name' => 'Admin Notifikasi']);
    $this->admin->assignRole('Admin');

    $this->cm = User::factory()->create(['name' => 'Change Manager Notifikasi']);
    $this->cm->assignRole('Change Manager');

    $this->authority = Authority::factory()->create([
        'name' => 'Pejabat Penyetuju A',
        'position' => 'Kepala Bidang IT Sample',
    ]);

    $this->makeRfc = function (array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => RfcStatus::SUBMITTED->value,
            'change_type' => 'normal',
        ], $attributes));
    };

    $this->makeRisk = function (ChangeRequest $rfc, array $attributes = []): void {
        $rfc->riskAssessment()->create(array_merge([
            'assessed_by_id' => $this->cm->id,
            'system_criticality_score' => 3,
            'impact_scope_score' => 3,
            'rollback_readiness_score' => 2,
            'total_score' => 8,
            'risk_level' => 'Tingkat-Risiko-Uji',
            'impact_level' => 'Dampak-Uji',
            'urgency_level' => 'Normal',
            'classification' => 'Klasifikasi-Intern-Uji',
            'analysis_notes' => 'Catatan analisis risiko internal.',
            'mitigation_plan' => 'Mitigasi contoh.',
            'rollback_plan' => 'Rollback contoh.',
        ], $attributes));
    };

    $this->makeDecision = function (
        ChangeRequest $rfc,
        string $status,
        string $reason,
        array $attributes = [],
    ): Decision {
        return Decision::create(array_merge([
            'change_request_id' => $rfc->id,
            'authority_id' => $this->authority->id,
            'recorded_by_id' => $this->cm->id,
            'snapshot_authority_name' => $this->authority->name,
            'snapshot_authority_position' => $this->authority->position,
            'decision_status' => $status,
            'decision_date' => now(),
            'reason' => $reason,
        ], $attributes));
    };
});

it('menyembunyikan penilaian risiko dan identitas pejabat dari Pemohon tetapi menampilkannya untuk staf', function () {
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::APPROVED->value,
    ]);
    ($this->makeRisk)($rfc);
    ($this->makeDecision)(
        $rfc,
        DecisionStatus::APPROVED->value,
        'Keputusan akhir untuk pemohon.',
    );

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Kategori Perubahan')
        ->assertSee('Normal')
        ->assertSee('Disetujui')
        ->assertSee('Keputusan akhir untuk pemohon.')
        ->assertDontSee('Penilaian Risiko (Change Manager)')
        ->assertDontSee('Tingkat Risiko')
        ->assertDontSee('Tingkat-Risiko-Uji')
        ->assertDontSee('Dampak-Uji')
        ->assertDontSee('Klasifikasi-Intern-Uji')
        ->assertDontSee('Pejabat Penyetuju A')
        ->assertDontSee('Kepala Bidang IT Sample')
        ->assertDontSee('Ditetapkan Petugas')
        ->assertDontSee('Jenis Perubahan');

    foreach ([$this->admin, $this->cm] as $staff) {
        $this->actingAs($staff);

        Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
            ->assertOk()
            ->assertSee('Penilaian Risiko (Change Manager)')
            ->assertSee('Tingkat-Risiko-Uji')
            ->assertSee('Dampak-Uji')
            ->assertSee('Klasifikasi-Intern-Uji')
            ->assertSee('Pejabat Penyetuju A')
            ->assertSee('Kepala Bidang IT Sample')
            ->assertSee('Ditetapkan Petugas');
    }
});

it('tidak menampilkan keputusan returned_to_admin dalam kartu keputusan Pemohon', function () {
    $internalNote = 'Catatan internal CM yang tidak boleh dilihat Pemohon.';
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
    ]);
    ($this->makeRisk)($rfc);
    ($this->makeDecision)($rfc, DecisionStatus::RETURNED_TO_ADMIN->value, $internalNote);

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Sedang ditinjau')
        ->assertDontSee('Dikembalikan ke Admin')
        ->assertDontSee('Penilaian Risiko (Change Manager)')
        ->assertDontSee('Keputusan Change Manager')
        ->assertDontSee('Pejabat Penyetuju A')
        ->assertDontSee($internalNote);

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Dikembalikan ke Admin')
        ->assertSee('Pejabat Penyetuju A')
        ->assertSee($internalNote);
});

it('tidak membuat notifikasi Pemohon untuk status returned_to_admin', function () {
    $rfc = ($this->makeRfc)([
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);

    RfcStatusTransition::transitionTo(
        $rfc,
        RfcStatus::RETURNED_TO_ADMIN,
        $this->cm,
        'Catatan internal untuk Admin.',
    );

    expect($rfc->fresh()->status)->toBe(RfcStatus::RETURNED_TO_ADMIN->value)
        ->and($this->pemohon->notifications()->count())->toBe(0);
});

it('mengirim notifikasi needs_revision dan rejected beserta catatan kepada pemilik RFC', function () {
    $needsRevisionRfc = ($this->makeRfc)();

    RfcStatusTransition::transitionTo(
        $needsRevisionRfc,
        RfcStatus::NEEDS_REVISION,
        $this->admin,
        'Lengkapi lampiran surat pengajuan.',
    );

    $rejectedRfc = ($this->makeRfc)();

    RfcStatusTransition::transitionTo(
        $rejectedRfc,
        RfcStatus::REJECTED,
        $this->admin,
        'Ditolak karena ruang lingkup perubahan tidak jelas.',
    );

    $notifications = $this->pemohon->notifications()
        ->get()
        ->keyBy(fn ($notification) => data_get($notification->data, 'rfc_id'));

    expect($notifications)->toHaveCount(2);

    $needsRevisionNotification = $notifications->get($needsRevisionRfc->id);
    $rejectedNotification = $notifications->get($rejectedRfc->id);

    expect(data_get($needsRevisionNotification->data, 'message'))
        ->toBe("RFC {$needsRevisionRfc->rfc_number} perlu direvisi: Lengkapi lampiran surat pengajuan.")
        ->and($needsRevisionNotification->notifiable_id)->toBe($this->pemohon->id)
        ->and(data_get($rejectedNotification->data, 'message'))
        ->toBe("RFC {$rejectedRfc->rfc_number} ditolak: Ditolak karena ruang lingkup perubahan tidak jelas.")
        ->and($rejectedNotification->notifiable_id)->toBe($this->pemohon->id);
});

it('menolak penandaan notifikasi milik pengguna lain', function () {
    $rfc = ($this->makeRfc)();
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::APPROVED));
    $notification = $this->pemohon->notifications()->firstOrFail();

    $otherRequester = User::factory()->create();
    $otherRequester->assignRole('Pemohon');

    $this->actingAs($otherRequester);

    Livewire::test(Notifications::class)
        ->call('markAsRead', $notification->id)
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('menandai notifikasi milik sendiri sebagai dibaca lalu membuka RFC terkait', function () {
    $rfc = ($this->makeRfc)();
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::APPROVED));
    $notification = $this->pemohon->notifications()->firstOrFail();

    $this->actingAs($this->pemohon);

    Livewire::test(Notifications::class)
        ->call('markAsRead', $notification->id)
        ->assertRedirect(route('change-requests.show', ['rfc' => $rfc]));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('membuka tautan notifikasi hanya untuk pemilik dan mengarah ke RFC yang benar', function () {
    $rfc = ($this->makeRfc)();
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::APPROVED));
    $notification = $this->pemohon->notifications()->firstOrFail();

    $this->actingAs($this->pemohon)
        ->get(route('notifications.index', ['open' => $notification->id]))
        ->assertRedirect(route('change-requests.show', ['rfc' => $rfc]));

    expect($notification->fresh()->read_at)->not->toBeNull();

    $otherRequester = User::factory()->create();
    $otherRequester->assignRole('Pemohon');

    $this->actingAs($otherRequester)
        ->get(route('notifications.index', ['open' => $notification->id]))
        ->assertForbidden();
});

it('tetap menampilkan notifikasi lama tanpa error dan tidak menulis ulang teksnya', function () {
    $rfc = ($this->makeRfc)();
    $legacyMessage = "Status dokumen RFC {$rfc->rfc_number} telah diperbarui menjadi Disetujui.";

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => RfcStatusChangedNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->pemohon->id,
        'data' => json_encode([
            'rfc_id' => $rfc->id,
            'rfc_number' => $rfc->rfc_number,
            'title' => $rfc->title,
            'status' => 'status_lama_tidak_dikenal',
            'message' => $legacyMessage,
        ]),
        'read_at' => null,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($this->pemohon)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($legacyMessage);
});

it('menyamakan jumlah belum dibaca pada lencana sidebar dan lonceng dengan halaman notifikasi', function () {
    $rfc = ($this->makeRfc)();

    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::APPROVED));
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::REJECTED));
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::CLOSED));

    $this->pemohon->notifications()->firstOrFail()->markAsRead();

    $response = $this->actingAs($this->pemohon)
        ->get(route('notifications.index'));

    $response->assertOk()
        ->assertSeeText('2 belum dibaca');

    preg_match_all('/data-notification-badge[^>]*>\s*2\s*</', $response->getContent(), $badges);

    expect($badges[0])->toHaveCount(2);
});

it('memakai ikon notifikasi sesuai jenis pesan dan tanggal Indonesia', function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));

    $rfc = ($this->makeRfc)();

    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::SUBMITTED));
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::APPROVED));
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::NEEDS_REVISION));
    $this->pemohon->notify(new RfcStatusChangedNotification($rfc, RfcStatus::REJECTED));

    $this->pemohon->notifications()
        ->get()
        ->each(fn ($notification) => $notification->forceFill([
            'created_at' => Carbon::parse('2026-10-19 04:11:00'),
        ])->save());

    $response = $this->actingAs($this->pemohon)
        ->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('19 Okt 2026, 04:11')
        ->assertSee('4 hari yang lalu');

    $html = $response->getContent();

    expect($html)
        ->toContain('bg-blue-100 text-blue-600')
        ->toContain('bg-emerald-100 text-emerald-600')
        ->toContain('bg-amber-100 text-amber-600')
        ->toContain('bg-rose-100 text-rose-600');
});

it('menampilkan kartu Ditolak yang ringkas di dashboard Pemohon', function () {
    ($this->makeRfc)([
        'status' => RfcStatus::REJECTED->value,
    ]);

    $this->actingAs($this->pemohon);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Ditolak')
        ->assertSee('Ditolak atau tidak terlaksana')
        ->assertDontSee('Ditolak / Tidak Terlaksana')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['rejected'] === 1);
});

it('tetap menampilkan kategori dan lampiran yang diisi Pemohon', function () {
    $rfc = ($this->makeRfc)([
        'change_type' => 'emergency',
        'description' => 'Deskripsi perubahan yang diketik Pemohon.',
    ]);

    $rfc->attachments()->create([
        'uploaded_by_id' => $this->pemohon->id,
        'original_name' => 'lampiran-pemohon.pdf',
        'path' => "rfc-attachments/{$rfc->id}/lampiran.pdf",
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Kategori Perubahan')
        ->assertSee('Emergency')
        ->assertSee('Deskripsi perubahan yang diketik Pemohon.')
        ->assertSee('Lampiran')
        ->assertSee('lampiran-pemohon.pdf')
        ->assertDontSee('Penilaian Risiko (Change Manager)');
});
