<?php

use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Carbon::setTestNow(Carbon::create(2026, 10, 26, 14, 5, 0, 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<int, string>  $roles
 */
function cmMetricsUser(array $roles = ['Change Manager']): User
{
    $user = User::factory()->create();

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmMetricsRfc(array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create($attributes);
}

/**
 * @param  array<string, mixed>  $properties
 */
function cmMetricsActivity(
    ChangeRequest $rfc,
    array $properties,
    ?Carbon $createdAt = null,
    ?User $causer = null,
    string $logName = 'rfc',
): Activity {
    return Activity::create([
        'log_name' => $logName,
        'description' => 'Kejadian RFC untuk pengujian.',
        'subject_type' => $rfc->getMorphClass(),
        'subject_id' => $rfc->getKey(),
        'causer_type' => $causer?->getMorphClass(),
        'causer_id' => $causer?->getKey(),
        'properties' => $properties,
        'created_at' => $createdAt ?? now(),
        'updated_at' => $createdAt ?? now(),
    ]);
}

it('menghitung metrik change manager tanpa RFC terhapus', function () {
    foreach ([
        RfcStatus::TICKET_CREATED,
        RfcStatus::APPROVED,
        RfcStatus::IN_PROGRESS,
        RfcStatus::IMPLEMENTED,
        RfcStatus::FAILED,
        RfcStatus::CLOSED,
        RfcStatus::DRAFT,
        RfcStatus::SUBMITTED,
    ] as $status) {
        cmMetricsRfc(['status' => $status->value]);
    }

    $deleted = cmMetricsRfc(['status' => RfcStatus::TICKET_CREATED->value]);
    $deleted->delete();

    $overview = app(DashboardMetricsService::class)->changeManagerOverview();

    expect($overview['pending_review'])->toBe(1)
        ->and($overview['approved'])->toBe(1)
        ->and($overview['in_progress'])->toBe(1)
        ->and($overview['waiting_evaluation'])->toBe(2)
        ->and($overview['closed'])->toBe(1)
        ->and($overview['action_required'])->toBe(5)
        ->and($overview['queue'])->toHaveCount(5)
        ->and(array_sum(array_column($overview['distribution'], 'value')))->toBe(8)
        ->and(collect($overview['queue'])->pluck('id'))->not->toContain($deleted->id);
});

it('membedakan label status tiket dan pengembalian pada distribusi', function () {
    cmMetricsRfc(['status' => RfcStatus::TICKET_CREATED->value]);
    cmMetricsRfc(['status' => RfcStatus::RETURNED_TO_ADMIN->value]);

    $distribution = collect(app(DashboardMetricsService::class)->changeManagerOverview()['distribution'])
        ->keyBy('status');

    expect($distribution[RfcStatus::TICKET_CREATED->value]['label'])->toBe('Sedang Ditinjau (Tiket)')
        ->and($distribution[RfcStatus::RETURNED_TO_ADMIN->value]['label'])->toBe('Dikembalikan ke Admin');
});

it('menampilkan antrean tindakan terlama dengan aksi yang sesuai status', function () {
    $manager = cmMetricsUser();

    $oldest = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-001',
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_created_at' => now()->subDays(8),
        'updated_at' => now(),
    ]);

    $implemented = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-002',
        'status' => RfcStatus::IMPLEMENTED->value,
        'updated_at' => now(),
    ]);
    cmMetricsActivity(
        $implemented,
        ['old_status' => RfcStatus::IN_PROGRESS->value, 'new_status' => RfcStatus::IMPLEMENTED->value],
        now()->subDays(6),
    );

    $inProgress = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-003',
        'status' => RfcStatus::IN_PROGRESS->value,
        'updated_at' => now(),
    ]);
    cmMetricsActivity(
        $inProgress,
        ['old_status' => RfcStatus::APPROVED->value, 'new_status' => RfcStatus::IN_PROGRESS->value],
        now()->subDays(5),
    );

    $approved = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-004',
        'status' => RfcStatus::APPROVED->value,
        'updated_at' => now(),
    ]);
    cmMetricsActivity(
        $approved,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::APPROVED->value],
        now()->subDays(4),
    );

    $failed = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-005',
        'status' => RfcStatus::FAILED->value,
        'updated_at' => now(),
    ]);
    cmMetricsActivity(
        $failed,
        ['old_status' => RfcStatus::IN_PROGRESS->value, 'new_status' => RfcStatus::FAILED->value],
        now()->subDays(3),
    );

    cmMetricsRfc([
        'rfc_number' => 'RFC-CM-006',
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_created_at' => now()->subDays(2),
    ]);

    $overview = app(DashboardMetricsService::class)->changeManagerOverview();
    $queue = collect($overview['queue']);

    expect($queue)->toHaveCount(5)
        ->and($queue->pluck('id')->all())->toBe([
            $oldest->id,
            $implemented->id,
            $inProgress->id,
            $approved->id,
            $failed->id,
        ])
        ->and($queue->pluck('wait_days')->all())->toBe([8, 6, 5, 4, 3])
        ->and($queue->firstWhere('id', $oldest->id)['action_route'])->toBe('assess-risk')
        ->and($queue->firstWhere('id', $implemented->id)['action_route'])->toBe('evaluate')
        ->and($queue->firstWhere('id', $inProgress->id)['action_route'])->toBe('implement')
        ->and($queue->firstWhere('id', $approved->id)['action_route'])->toBe('implement')
        ->and($queue->firstWhere('id', $failed->id)['action_route'])->toBe('evaluate');

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Tinjau Risiko')
        ->assertSee('Catat Implementasi')
        ->assertSee('Evaluasi RFC')
        ->assertSee(route('change-requests.assess-risk', $oldest->id))
        ->assertSee(route('change-requests.evaluate', $implemented->id))
        ->assertSee(route('change-requests.implement', $inProgress->id))
        ->assertSee(route('change-requests.implement', $approved->id))
        ->assertSee(route('change-requests.evaluate', $failed->id))
        ->assertDontSee('RFC-CM-006');
});

it('memakai sumber waktu tunggu yang tepat untuk antrean', function () {
    $manager = cmMetricsUser();

    $ticketCreated = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-WAIT-001',
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_created_at' => now()->subDays(4),
        'updated_at' => now(),
    ]);

    $approved = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-WAIT-002',
        'status' => RfcStatus::APPROVED->value,
        'updated_at' => now()->subDays(3),
    ]);

    $inProgress = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-WAIT-003',
        'status' => RfcStatus::IN_PROGRESS->value,
        'updated_at' => now(),
    ]);
    cmMetricsActivity(
        $inProgress,
        ['old_status' => RfcStatus::APPROVED->value, 'new_status' => RfcStatus::IN_PROGRESS->value],
        now()->subDays(2),
    );

    $evaluated = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-WAIT-004',
        'status' => RfcStatus::IMPLEMENTED->value,
        'updated_at' => now(),
    ]);
    $evaluated->implementation()->create([
        'recorded_by_id' => $manager->id,
        'implementation_result' => ImplementationResult::SUCCESS->value,
        'started_at' => now()->subDays(5),
        'finished_at' => now()->subDays(2),
        'executor_name' => 'Tim Implementasi',
        'summary' => 'Implementasi selesai.',
        'test_results' => 'Pengujian berhasil.',
        'evaluated_by_id' => $manager->id,
        'evaluated_at' => now()->subDay(),
    ]);
    cmMetricsActivity(
        $evaluated,
        ['old_status' => RfcStatus::IN_PROGRESS->value, 'new_status' => RfcStatus::IMPLEMENTED->value],
        now()->subDay(),
    );

    $queue = collect(app(DashboardMetricsService::class)->changeManagerOverview()['queue'])
        ->keyBy('id');

    expect($queue[$ticketCreated->id]['wait_days'])->toBe(4)
        ->and($queue[$approved->id]['wait_days'])->toBe(3)
        ->and($queue[$inProgress->id]['wait_days'])->toBe(2)
        ->and($queue[$evaluated->id]['action_label'])->toBe('Tutup RFC')
        ->and($queue[$evaluated->id]['action_route'])->toBe('close');
});

it('mengelompokkan tiket masuk dan keputusan berdasarkan hari WIB', function () {
    foreach ([
        '2026-10-25 23:30:00',
        '2026-10-26 00:30:00',
        '2026-09-27 00:00:00',
        '2026-09-26 23:59:59',
    ] as $index => $timestamp) {
        cmMetricsRfc([
            'rfc_number' => 'RFC-CM-TREND-00'.$index,
            'status' => RfcStatus::TICKET_CREATED->value,
            'ticket_created_at' => Carbon::parse($timestamp, 'Asia/Jakarta'),
        ]);
    }

    $decisionRfc = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-DECISION-001',
        'status' => RfcStatus::APPROVED->value,
    ]);

    foreach ([
        '2026-10-25 23:30:00',
        '2026-10-26 00:30:00',
        '2026-09-27 00:00:00',
    ] as $timestamp) {
        cmMetricsActivity(
            $decisionRfc,
            ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::APPROVED->value],
            Carbon::parse($timestamp, 'Asia/Jakarta'),
        );
    }

    cmMetricsActivity(
        $decisionRfc,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::REJECTED->value],
        Carbon::parse('2026-09-26 23:59:59', 'Asia/Jakarta'),
    );

    $deleted = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-TREND-DELETED',
        'status' => RfcStatus::APPROVED->value,
        'ticket_created_at' => Carbon::parse('2026-10-26 10:00:00', 'Asia/Jakarta'),
    ]);
    cmMetricsActivity(
        $deleted,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::APPROVED->value],
        Carbon::parse('2026-10-26 10:05:00', 'Asia/Jakarta'),
    );
    $deleted->delete();

    $overview = app(DashboardMetricsService::class)->changeManagerOverview();
    $labels = collect($overview['trend']['table_labels']);

    expect(array_sum($overview['trend']['tickets']))->toBe(3)
        ->and(array_sum($overview['trend']['decisions']))->toBe(3)
        ->and($overview['trend']['labels'])->toHaveCount(30)
        ->and($overview['trend']['tickets'][$labels->search('25 Oktober 2026')])->toBe(1)
        ->and($overview['trend']['decisions'][$labels->search('25 Oktober 2026')])->toBe(1)
        ->and($overview['trend']['tickets'][$labels->search('26 Oktober 2026')])->toBe(1)
        ->and($overview['trend']['decisions'][$labels->search('26 Oktober 2026')])->toBe(1)
        ->and($overview['trend']['tickets'][$labels->search('27 September 2026')])->toBe(1)
        ->and($overview['trend']['decisions'][$labels->search('27 September 2026')])->toBe(1);
});

it('merender keadaan kosong tanpa angka palsu', function () {
    $manager = cmMetricsUser();
    $overview = app(DashboardMetricsService::class)->changeManagerOverview();

    expect($overview['pending_review'])->toBe(0)
        ->and($overview['approved'])->toBe(0)
        ->and($overview['in_progress'])->toBe(0)
        ->and($overview['waiting_evaluation'])->toBe(0)
        ->and($overview['closed'])->toBe(0)
        ->and($overview['action_required'])->toBe(0)
        ->and($overview['queue'])->toBe([])
        ->and($overview['distribution'])->toBe([])
        ->and($overview['activities'])->toBe([])
        ->and(array_sum($overview['trend']['tickets']))->toBe(0)
        ->and(array_sum($overview['trend']['decisions']))->toBe(0);

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Belum ada tindakan yang menunggu')
        ->assertSee('Belum ada data grafik')
        ->assertSee('Belum ada data distribusi')
        ->assertSee('Belum ada aktivitas terkini')
        ->assertDontSee('NaN')
        ->assertDontSee('Infinity')
        ->assertDontSee('+100');
});

it('menyaring aktivitas change manager dan menjaga data sensitif tetap privat', function () {
    $manager = cmMetricsUser();
    $actor = cmMetricsUser([]);
    $actor->forceFill([
        'name' => 'Pelaku Aman CM',
        'nip' => '198812312010011234',
        'email' => 'pelaku-cm@example.test',
    ])->save();

    $relevantStatuses = [
        RfcStatus::TICKET_CREATED,
        RfcStatus::APPROVED,
        RfcStatus::IN_PROGRESS,
        RfcStatus::IMPLEMENTED,
        RfcStatus::CLOSED,
    ];

    foreach ($relevantStatuses as $index => $status) {
        $rfc = cmMetricsRfc([
            'rfc_number' => 'RFC-CM-AKTIVITAS-00'.$index,
            'status' => $status->value,
        ]);
        cmMetricsActivity(
            $rfc,
            [
                'old_status' => RfcStatus::DRAFT->value,
                'new_status' => $status->value,
                'note' => 'Catatan rahasia yang tidak boleh tampil',
            ],
            now()->subMinutes($index + 1),
            $actor,
        );
    }

    $excludedRfcs = [
        'draft' => cmMetricsRfc([
            'rfc_number' => 'RFC-CM-DRAFT-001',
            'status' => RfcStatus::DRAFT->value,
        ]),
        'submitted' => cmMetricsRfc([
            'rfc_number' => 'RFC-CM-SUBMITTED-001',
            'status' => RfcStatus::SUBMITTED->value,
        ]),
        'deleted' => cmMetricsRfc([
            'rfc_number' => 'RFC-CM-DELETED-001',
            'status' => RfcStatus::TICKET_CREATED->value,
        ]),
    ];

    foreach ($excludedRfcs as $rfc) {
        cmMetricsActivity(
            $rfc,
            ['old_status' => RfcStatus::DRAFT->value, 'new_status' => RfcStatus::SUBMITTED->value],
            now()->subSeconds(30),
            $actor,
        );
    }
    $excludedRfcs['deleted']->delete();

    Activity::create([
        'log_name' => 'auth',
        'description' => 'Login berhasil',
        'properties' => ['email' => 'autentikasi-cm@example.test', 'ip' => '127.0.0.1'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $overview = app(DashboardMetricsService::class)->changeManagerOverview();
    $activities = collect($overview['activities']);

    expect($activities)->toHaveCount(5)
        ->and($activities->pluck('rfc_number'))->not->toContain('RFC-CM-DRAFT-001')
        ->and($activities->pluck('rfc_number'))->not->toContain('RFC-CM-SUBMITTED-001')
        ->and($activities->pluck('rfc_number'))->not->toContain('RFC-CM-DELETED-001')
        ->and(array_keys($overview['activities'][0]))->toEqualCanonicalizing([
            'rfc_number',
            'label',
            'actor_name',
            'occurred_at',
        ]);

    $serialized = json_encode($overview['activities'], JSON_THROW_ON_ERROR);

    expect($serialized)->not->toContain('Catatan rahasia')
        ->not->toContain('198812312010011234')
        ->not->toContain('pelaku-cm@example.test')
        ->not->toContain('autentikasi-cm@example.test');

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Pelaku Aman CM')
        ->assertSee('menit yang lalu')
        ->assertDontSee('Catatan rahasia yang tidak boleh tampil')
        ->assertDontSee('198812312010011234')
        ->assertDontSee('pelaku-cm@example.test')
        ->assertDontSee('autentikasi-cm@example.test')
        ->assertDontSee('RFC-CM-DRAFT-001')
        ->assertDontSee('RFC-CM-SUBMITTED-001')
        ->assertDontSee('RFC-CM-DELETED-001');
});

it('merender grafik aksesibel dengan label change manager', function () {
    $rfc = cmMetricsRfc([
        'rfc_number' => 'RFC-CM-GRAFIK-001',
        'status' => RfcStatus::APPROVED->value,
        'ticket_created_at' => now()->subHour(),
    ]);
    cmMetricsActivity(
        $rfc,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::APPROVED->value],
        now()->subMinutes(30),
    );

    $manager = cmMetricsUser();
    $packageBefore = md5_file(base_path('package.json'));

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('<figure', false)
        ->assertSee('<figcaption', false)
        ->assertSee('role="img"', false)
        ->assertSee('aria-labelledby="tren-cm-30-hari-title tren-cm-30-hari-description"', false)
        ->assertSee('<title>Tiket masuk dan keputusan Change Manager</title>', false)
        ->assertSee('<desc>Tren harian selama 30 hari terakhir.</desc>', false)
        ->assertSee('<polyline', false)
        ->assertSee('<details', false)
        ->assertSee('<caption', false)
        ->assertSee('scope="col"', false)
        ->assertSee('Tiket masuk')
        ->assertSee('Keputusan CM')
        ->assertSee('data harian')
        ->assertDontSee('Belum ada data grafik');

    expect(md5_file(base_path('package.json')))->toBe($packageBefore);
});

it('menjaga jumlah query dashboard change manager di bawah 25 per render', function () {
    $manager = cmMetricsUser();

    foreach (range(1, 5) as $index) {
        $rfc = cmMetricsRfc([
            'rfc_number' => 'RFC-CM-QUERY-00'.$index,
            'status' => RfcStatus::TICKET_CREATED->value,
            'ticket_created_at' => now()->subDays($index),
            'updated_at' => now()->subDays($index),
        ]);
        cmMetricsActivity(
            $rfc,
            ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::TICKET_CREATED->value],
            now()->subDays($index),
            $manager,
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($manager)->get(route('change-manager.dashboard'))->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(25);
});

it('membatasi dashboard change manager dan menjaga dashboard lain tetap berfungsi', function () {
    $manager = cmMetricsUser();
    $admin = cmMetricsUser(['Admin']);
    $pemohon = cmMetricsUser(['Pemohon']);
    $superAdmin = cmMetricsUser(['Super Admin']);

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Change Manager');

    $this->actingAs($superAdmin)
        ->get(route('change-manager.dashboard'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('change-manager.dashboard'))
        ->assertForbidden();

    $this->actingAs($pemohon)
        ->get(route('change-manager.dashboard'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Antrean RFC Masuk')
        ->assertDontSee('Antrean Tindakan');

    $this->actingAs($pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Pemohon')
        ->assertDontSee('Antrean Tindakan');
});
