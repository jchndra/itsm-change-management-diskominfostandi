<?php

use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Requester\Dashboard;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\User;
use App\Notifications\RfcStatusChangedNotification;
use App\Services\RfcClosureService;
use App\Services\RfcEvaluationService;
use App\Services\RfcHistoryService;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Hasil']);
    $this->pemohon->assignRole('Pemohon');

    $this->cm = User::factory()->create(['name' => 'CM Hasil']);
    $this->cm->assignRole('Change Manager');

    $this->makeRfc = function (array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => RfcStatus::SUBMITTED->value,
        ], $attributes));
    };

    $this->makeImplementation = function (ChangeRequest $rfc, string $result, ?string $evaluatedAt = null): void {
        ChangeRequestImplementation::create([
            'change_request_id' => $rfc->id,
            'recorded_by_id' => $this->cm->id,
            'implementation_result' => $result,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'executor_name' => 'Tim Rilis Diskominfostandi',
            'report_source' => 'Laporan tertulis Tim Rilis',
            'summary' => 'Perubahan diterapkan pada lingkungan produksi.',
            'test_results' => 'Pengujian fungsional berhasil.',
            'evaluated_at' => $evaluatedAt !== null ? now() : null,
        ]);
    };

    // Menutup RFC melalui service agar activity penutupan mengandung outcome.
    $this->makeClosed = function (string $result): ChangeRequest {
        $rfc = ChangeRequest::factory()->create([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => $result === ImplementationResult::SUCCESS->value
                ? RfcStatus::IMPLEMENTED->value
                : RfcStatus::FAILED->value,
        ]);

        ($this->makeImplementation)($rfc, $result);

        app(RfcEvaluationService::class)->record(
            $rfc->fresh(),
            $this->cm,
            'Evaluasi implementasi yang lengkap untuk pengujian tampilan.',
        );

        return app(RfcClosureService::class)->close(
            $rfc->fresh(),
            $this->cm,
            'Catatan penutupan resmi untuk pengujian hasil akhir.',
        );
    };

    // Membawa RFC dari approved hingga implemented/failed lewat matriks
    // transisi sehingga activity dan notifikasi terbentuk seperti produksi.
    $this->recordOutcome = function (string $target, string $result): ChangeRequest {
        $rfc = ($this->makeRfc)(['status' => RfcStatus::APPROVED->value]);
        ($this->makeImplementation)($rfc, $result);

        RfcStatusTransition::transitionTo($rfc, RfcStatus::IN_PROGRESS, $this->cm);
        RfcStatusTransition::transitionTo(
            $rfc->fresh(),
            RfcStatus::from($target),
            $this->cm,
            null,
            ['implementation_result' => $result],
        );

        return $rfc->fresh();
    };
});

test('label dan tone status akhir membedakan hasil implementasi', function () {
    $implemented = ($this->recordOutcome)(RfcStatus::IMPLEMENTED->value, ImplementationResult::SUCCESS->value);
    $failed = ($this->recordOutcome)(RfcStatus::FAILED->value, ImplementationResult::FAILED->value);

    $closedSuccess = ($this->makeClosed)(ImplementationResult::SUCCESS->value);
    $closedFailed = ($this->makeClosed)(ImplementationResult::FAILED->value);
    $closedRollback = ($this->makeClosed)(ImplementationResult::ROLLED_BACK->value);

    $closedLegacy = ($this->makeRfc)(['status' => RfcStatus::CLOSED->value]);
    $inProgress = ($this->makeRfc)(['status' => RfcStatus::IN_PROGRESS->value]);

    expect($implemented->displayStatusLabel())->toBe('Menunggu Evaluasi')
        ->and($implemented->displayStatusTone())->toBe('bg-amber-100 text-amber-700')
        ->and($implemented->isImplementationUnsuccessful())->toBeFalse()
        ->and($failed->displayStatusLabel())->toBe('Gagal / Rollback')
        ->and($failed->displayStatusTone())->toBe('bg-red-700 text-white')
        ->and($failed->isImplementationUnsuccessful())->toBeTrue()
        ->and($closedSuccess->displayStatusLabel())->toBe('Ditutup — Berhasil')
        ->and($closedSuccess->displayStatusTone())->toBe('bg-emerald-100 text-emerald-700')
        ->and($closedSuccess->isImplementationUnsuccessful())->toBeFalse()
        ->and($closedFailed->displayStatusLabel())->toBe('Ditutup — Tidak Berhasil')
        ->and($closedFailed->displayStatusTone())->toBe('bg-rose-100 text-rose-700')
        ->and($closedFailed->isImplementationUnsuccessful())->toBeTrue()
        ->and($closedRollback->displayStatusLabel())->toBe('Ditutup — Tidak Berhasil')
        ->and($closedRollback->displayStatusTone())->toBe('bg-rose-100 text-rose-700')
        ->and($closedRollback->isImplementationUnsuccessful())->toBeTrue()
        ->and($closedLegacy->displayStatusLabel())->toBe('Ditutup')
        ->and($closedLegacy->displayStatusTone())->toBe('bg-slate-200 text-slate-700')
        ->and($closedLegacy->isImplementationUnsuccessful())->toBeFalse()
        ->and($inProgress->displayStatusLabel())->toBe('Sedang Dikerjakan')
        ->and($inProgress->displayStatusTone())->toBe('bg-blue-100 text-blue-700');
});

test('statistik kartu memperhitungkan hasil closed dan widget menahan RFC failed', function () {
    ($this->makeRfc)(['status' => RfcStatus::APPROVED->value, 'title' => 'RFC Disetujui']);
    $implemented = ($this->makeRfc)(['status' => RfcStatus::IMPLEMENTED->value, 'title' => 'RFC Menunggu Evaluasi']);
    ($this->makeImplementation)($implemented, ImplementationResult::SUCCESS->value);
    ($this->makeClosed)(ImplementationResult::SUCCESS->value);
    ($this->makeClosed)(ImplementationResult::FAILED->value);
    ($this->makeClosed)(ImplementationResult::ROLLED_BACK->value);
    $failed = ($this->makeRfc)(['status' => RfcStatus::FAILED->value, 'title' => 'RFC Gagal Belum Ditutup']);
    ($this->makeRfc)(['status' => RfcStatus::REJECTED->value, 'title' => 'RFC Ditolak']);

    DB::table('change_requests')->where('id', $failed->id)->update(['updated_at' => now()->addMinutes(2)]);

    Livewire::actingAs($this->pemohon)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['approved'] === 3 && $stats['rejected'] === 4)
        // RFC gagal yang belum ditutup tetap muncul di widget dengan hasil merah.
        ->assertViewHas('latestActiveRfc', fn (?ChangeRequest $rfc): bool => $rfc?->id === $failed->id)
        ->assertSee('Implementasi gagal')
        ->assertSee('Menunggu evaluasi')
        ->assertDontSee('Selesai');

    // Penutupan service mensyaratkan catatan implementasi dan evaluasi.
    ($this->makeImplementation)($failed, ImplementationResult::FAILED->value);
    app(RfcEvaluationService::class)->record(
        $failed->fresh(),
        $this->cm,
        'Evaluasi kegagalan dicatat sebelum penutupan uji widget.',
    );

    // Baru setelah ditutup RFC gagal keluar dari widget.
    $done = app(RfcClosureService::class)->close(
        $failed->fresh(),
        $this->cm,
        'Catatan penutupan untuk RFC gagal yang baru ditutup.',
    );

    Livewire::actingAs($this->pemohon)
        ->test(Dashboard::class)
        ->assertOk()
        // Widget masih terisi oleh RFC aktif lain (approved/implemented),
        // tetapi RFC gagal yang sudah ditutup bukan lagi isinya.
        ->assertViewHas('latestActiveRfc', fn (?ChangeRequest $rfc): bool => $rfc !== null
            && $rfc->id !== $failed->id);

    // Angka kartu sama dengan jumlah baris filter terkait di daftar (approved
    // 3 dan rejected 4 tetap terhitung dari seluruh RFC Pemohon).
    Livewire::actingAs($this->pemohon)
        ->test(ListChangeRequests::class)
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['my_rfc'] === 7
            && $stats['approved'] === 3
            && $stats['rejected'] === 4)
        ->set('statusFilter', RfcStatus::CLOSED->value)
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['approved'] === 3
            && $stats['rejected'] === 4)
        ->assertSee('Ditutup — Berhasil')
        ->assertSee('Ditutup — Tidak Berhasil');
});

test('RFC failed yang ditutup tidak pernah tampil sebagai berhasil atau selesai', function () {
    $evaluationNotes = 'Evaluasi implementasi yang lengkap untuk pengujian tampilan.';
    $closureNotes = 'Catatan penutupan resmi untuk pengujian hasil akhir.';

    $closedFailed = ($this->makeClosed)(ImplementationResult::ROLLED_BACK->value);

    // Detail Pemohon: badge hasil, tetapi catatan evaluasi/penutupan tetap
    // tersembunyi dan tidak ada kata "Selesai" sebagai status.
    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $closedFailed->fresh()])
        ->assertOk()
        ->assertSee($closedFailed->rfc_number)
        ->assertSee('Ditutup — Tidak Berhasil')
        ->assertSee('RFC ditutup — tidak berhasil')
        ->assertDontSee('Selesai')
        ->assertDontSee($evaluationNotes)
        ->assertDontSee($closureNotes);

    // Detail staf: label sama, catatan internal tampil untuk staf saja.
    $this->actingAs($this->cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $closedFailed->fresh()])
        ->assertOk()
        ->assertSee('Ditutup — Tidak Berhasil')
        ->assertSee('RFC ditutup — tidak berhasil')
        ->assertSee($evaluationNotes)
        ->assertSee($closureNotes);

    // Riwayat: label kejadian hasil tepat, bukan label "Selesai" lama.
    foreach ([false, true] as $forStaff) {
        $history = app(RfcHistoryService::class)->build($closedFailed->fresh(), $forStaff);
        $closureEvent = collect($history['events'])->firstWhere('key', RfcStatus::CLOSED->value);

        expect($closureEvent['label'])->toBe('RFC ditutup — tidak berhasil')
            ->and(json_encode($history))->not->toContain('ditutup — berhasil');
    }

    // Daftar: nilai filter status tidak berubah dan menampilkan badge hasil.
    Livewire::actingAs($this->pemohon)
        ->test(ListChangeRequests::class)
        ->set('statusFilter', RfcStatus::CLOSED->value)
        ->assertOk()
        ->assertSee($closedFailed->rfc_number)
        ->assertSee('Ditutup — Tidak Berhasil')
        ->assertDontSee('Selesai');

    // Dashboard: RFC closed tidak dipakai widget Status Permohonan Saya.
    $this->actingAs($this->pemohon);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertViewHas('latestActiveRfc', fn (?ChangeRequest $rfc): bool => $rfc === null);
});

test('riwayat dan langkah berikutnya untuk approved, implemented, failed, dan closed', function () {
    $implementation = ['key' => 'implementation', 'label' => 'Implementasi'];
    $evaluation = ['key' => 'evaluation', 'label' => 'Evaluasi'];
    $closure = ['key' => 'closure', 'label' => 'Penutupan'];

    // APPROVED: Implementasi, Evaluasi, Penutupan.
    $approved = ($this->makeRfc)(['status' => RfcStatus::APPROVED->value]);
    expect(app(RfcHistoryService::class)->build($approved, false)['next_steps'])
        ->toBe([$implementation, $evaluation, $closure]);

    // RFC implemented tanpa activity (data lama) tetap memakai label hasil
    // yang sama, bukan label status generik.
    $implementedWithoutActivity = ($this->makeRfc)(['status' => RfcStatus::IMPLEMENTED->value]);
    $syntheticHistory = app(RfcHistoryService::class)->build($implementedWithoutActivity, false);
    $syntheticEvent = collect($syntheticHistory['events'])->firstWhere('key', RfcStatus::IMPLEMENTED->value);

    expect($syntheticEvent['label'])->toBe('Implementasi berhasil dicatat — menunggu evaluasi')
        ->and($syntheticEvent['tone'])->toBe('success');

    // IMPLEMENTED: kejadian label baru dan langkah Evaluasi, Penutupan.
    $implemented = ($this->recordOutcome)(RfcStatus::IMPLEMENTED->value, ImplementationResult::SUCCESS->value);

    $historyImplemented = app(RfcHistoryService::class)->build($implemented, true);
    $implementedEvent = collect($historyImplemented['events'])->firstWhere('key', RfcStatus::IMPLEMENTED->value);

    expect($implementedEvent['label'])->toBe('Implementasi berhasil dicatat — menunggu evaluasi')
        ->and($historyImplemented['next_steps'])->toBe([$evaluation, $closure]);

    // Setelah evaluasi tercatat, langkah tersisa hanya penutupan.
    app(RfcEvaluationService::class)->record(
        $implemented->fresh(),
        $this->cm,
        'Evaluasi implementasi berhasil untuk pengujian langkah penutupan.',
    );

    expect(app(RfcHistoryService::class)->build($implemented->fresh(), false)['next_steps'])
        ->toBe([$closure]);

    // Timeline juga memindahkan langkah berikutnya ke penutupan setelah
    // evaluasi tercatat, bukan tetap menampilkan "menunggu evaluasi".
    $timeline = collect(app(RfcHistoryService::class)->dashboardTimeline($implemented->fresh(), false))
        ->keyBy('key');

    expect($timeline['evaluation']['state'])->toBe('done')
        ->and($timeline['evaluation']['description'])->toBe('Evaluasi tercatat')
        ->and($timeline['closed']['state'])->toBe('current')
        ->and($timeline['closed']['description'])->toBe('Menunggu penutupan');

    // FAILED belum evaluasi: Evaluasi lalu Penutupan; bukan status akhir.
    $failed = ($this->recordOutcome)(RfcStatus::FAILED->value, ImplementationResult::FAILED->value);

    $historyFailed = app(RfcHistoryService::class)->build($failed, false);
    $failedEvent = collect($historyFailed['events'])->firstWhere('key', RfcStatus::FAILED->value);

    expect($failedEvent['label'])->toBe('Implementasi gagal atau rollback — menunggu evaluasi')
        ->and($historyFailed['next_steps'])->toBe([$evaluation, $closure]);

    // FAILED setelah evaluasi tercatat: hanya Penutupan.
    app(RfcEvaluationService::class)->record(
        $failed->fresh(),
        $this->cm,
        'Evaluasi kegagalan yang dicatat sebelum penutupan RFC.',
    );

    expect(app(RfcHistoryService::class)->build($failed->fresh(), false)['next_steps'])
        ->toBe([$closure]);

    // CLOSED: tidak ada langkah berikutnya, termasuk RFC yang ditutup gagal.
    $closed = app(RfcClosureService::class)->close(
        $failed->fresh(),
        $this->cm,
        'Penutupan RFC gagal yang dievaluasi untuk pengujian riwayat.',
    );

    $historyClosed = app(RfcHistoryService::class)->build($closed->fresh(), false);
    $closureEvent = collect($historyClosed['events'])->firstWhere('key', RfcStatus::CLOSED->value);

    expect($historyClosed['next_steps'])->toBe([])
        ->and($closureEvent['label'])->toBe('RFC ditutup — tidak berhasil');
});

test('notifikasi baru membawa outcome dan pesan hasil yang benar', function () {
    $implemented = ($this->recordOutcome)(RfcStatus::IMPLEMENTED->value, ImplementationResult::SUCCESS->value);

    $failed = ($this->recordOutcome)(RfcStatus::FAILED->value, ImplementationResult::FAILED->value);

    app(RfcEvaluationService::class)->record($failed->fresh(), $this->cm, 'Evaluasi kegagalan lengkap sebelum penutupan.');

    $closed = app(RfcClosureService::class)->close(
        $failed->fresh(),
        $this->cm,
        'Penutupan RFC gagal dengan hasil tidak berhasil untuk uji notifikasi.',
    );

    $notifications = $this->pemohon->notifications()->get();

    $implementedNotification = $notifications->first(
        fn ($notification): bool => data_get($notification->data, 'status') === RfcStatus::IMPLEMENTED->value,
    );
    $failedNotification = $notifications->first(
        fn ($notification): bool => data_get($notification->data, 'status') === RfcStatus::FAILED->value,
    );
    $closedNotification = $notifications->first(
        fn ($notification): bool => data_get($notification->data, 'status') === RfcStatus::CLOSED->value,
    );

    expect($implementedNotification)->not->toBeNull()
        ->and(data_get($implementedNotification->data, 'outcome'))->toBe('successful')
        ->and(data_get($implementedNotification->data, 'message'))
        ->toBe("RFC {$implemented->rfc_number} selesai diimplementasikan, menunggu evaluasi")
        ->and($failedNotification)->not->toBeNull()
        ->and(data_get($failedNotification->data, 'outcome'))->toBe('unsuccessful')
        ->and(data_get($failedNotification->data, 'message'))
        ->toBe("RFC {$failed->rfc_number} gagal atau rollback dilaksanakan, menunggu evaluasi")
        ->and($closedNotification)->not->toBeNull()
        ->and(data_get($closedNotification->data, 'outcome'))->toBe('unsuccessful')
        ->and(data_get($closedNotification->data, 'message'))
        ->toBe("RFC {$closed->rfc_number} ditutup — tidak berhasil");

    // Ikon hasil pada halaman notifikasi dan lonceng header: merah untuk gagal
    // dan closed tidak berhasil, biru untuk implemented milik Pemohon.
    $this->actingAs($this->pemohon)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('bg-red-100 text-red-600')
        ->assertDontSee('bg-emerald-100 text-emerald-600')
        ->assertSee("RFC {$closed->rfc_number} ditutup — tidak berhasil")
        ->assertSee("RFC {$failed->rfc_number} gagal atau rollback dilaksanakan, menunggu evaluasi");
});

test('notifikasi implemented berwarna kuning untuk staf dan biru untuk Pemohon', function () {
    $rfc = ($this->makeRfc)(['status' => RfcStatus::IMPLEMENTED->value]);
    $message = "RFC {$rfc->rfc_number} selesai diimplementasikan, menunggu evaluasi";

    foreach ([
        [$this->pemohon->id, 'bg-blue-100 text-blue-600'],
        [$this->cm->id, 'bg-amber-100 text-amber-600'],
    ] as [$notifiableId, $expectedTone]) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => RfcStatusChangedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $notifiableId,
            'data' => json_encode([
                'rfc_id' => $rfc->id,
                'rfc_number' => $rfc->rfc_number,
                'title' => $rfc->title,
                'status' => RfcStatus::IMPLEMENTED->value,
                'outcome' => 'successful',
                'message' => $message,
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($notifiableId);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($message)
            ->assertSee($expectedTone);
    }
});

test('notifikasi lama tanpa outcome tetap dirender tanpa error dan tanpa ditulis ulang', function () {
    $rfc = ($this->makeRfc)(['status' => RfcStatus::CLOSED->value]);
    $legacyMessage = "Status dokumen RFC {$rfc->rfc_number} telah selesai.";

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => RfcStatusChangedNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->pemohon->id,
        'data' => json_encode([
            'rfc_id' => $rfc->id,
            'rfc_number' => $rfc->rfc_number,
            'title' => $rfc->title,
            'status' => RfcStatus::CLOSED->value,
            'message' => $legacyMessage,
        ]),
        'read_at' => null,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($this->pemohon)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($legacyMessage)
        // Perilaku lama: ikon emerald untuk closed tanpa data outcome.
        ->assertSee('bg-emerald-100 text-emerald-600');
});

test('RFC lama closed tanpa implementasi tampil Ditutup tanpa error', function () {
    $rfc = ($this->makeRfc)(['status' => RfcStatus::CLOSED->value, 'title' => 'RFC Lama Ditutup']);

    foreach ([$this->cm, $this->pemohon] as $user) {
        $this->actingAs($user);

        Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
            ->assertOk()
            ->assertSee($rfc->rfc_number)
            ->assertSee('Ditutup');

        Livewire::test(ListChangeRequests::class)
            ->assertOk()
            ->assertSee($rfc->rfc_number)
            ->assertSee('Ditutup');
    }

    // Pemohon tidak melihat catatan evaluasi maupun penutupan; label hasil
    // tetap terlihat. Ini juga memastikan detail tanpa data implementasi.
    $this->actingAs($this->pemohon);

    expect($rfc->displayStatusLabel())->toBe('Ditutup');
});
