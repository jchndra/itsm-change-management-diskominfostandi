<?php

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
function adminMetricsUser(array $roles = ['Admin']): User
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
function adminMetricsRfc(array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create($attributes);
}

/**
 * @param  array<string, mixed>  $timestamps
 */
function adminMetricsTimestamp(ChangeRequest $rfc, array $timestamps): ChangeRequest
{
    DB::table('change_requests')->where('id', $rfc->getKey())->update($timestamps);

    return $rfc->refresh();
}

/**
 * @param  array<string, mixed>  $properties
 */
function adminMetricsActivity(
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

it('menghitung metrik antrean, distribusi, dan tren tanpa RFC terhapus', function () {
    $oldestSubmitted = adminMetricsRfc([
        'rfc_number' => 'RFC-METRIK-001',
        'status' => RfcStatus::SUBMITTED->value,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    adminMetricsRfc(['status' => RfcStatus::SUBMITTED->value]);
    adminMetricsRfc(['status' => RfcStatus::RETURNED_TO_ADMIN->value]);
    adminMetricsRfc(['status' => RfcStatus::NEEDS_REVISION->value]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-METRIK-001',
        'ticket_created_at' => now()->subDay(),
    ]);
    adminMetricsRfc(['status' => RfcStatus::DRAFT->value]);

    $deleted = adminMetricsRfc([
        'status' => RfcStatus::SUBMITTED->value,
        'ticket_created_at' => now()->subDay(),
    ]);
    $deleted->delete();

    $overview = app(DashboardMetricsService::class)->adminOverview();

    expect($overview['waiting_ticket'])->toBe(3)
        ->and($overview['returned_to_admin'])->toBe(1)
        ->and($overview['needs_revision'])->toBe(1)
        ->and($overview['tickets_created']['current'])->toBe(1)
        ->and(array_sum($overview['trend']['created']))->toBe(5)
        ->and(array_sum($overview['trend']['tickets']))->toBe(1)
        ->and($overview['queue'])->toHaveCount(3)
        ->and($overview['queue'][0]['id'])->toBe($oldestSubmitted->id)
        ->and(array_sum(array_column($overview['distribution'], 'value')))->toBe(6);

    $distribution = collect($overview['distribution'])->keyBy('status');

    expect($distribution[RfcStatus::SUBMITTED->value]['value'])->toBe(2)
        ->and($distribution[RfcStatus::DRAFT->value]['value'])->toBe(1)
        ->and(collect($overview['queue'])->pluck('id'))->not->toContain($deleted->id);
});

it('menghitung tiket 30 hari dan persentase terhadap periode sebelumnya', function () {
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-NOW-001',
        'ticket_created_at' => now()->subDays(2),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-NOW-002',
        'ticket_created_at' => now()->subDays(5),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-PREV-001',
        'ticket_created_at' => now()->subDays(35),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-OLD-001',
        'ticket_created_at' => now()->subDays(90),
    ]);

    $deleted = adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-DELETED-001',
        'ticket_created_at' => now()->subDay(),
    ]);
    $deleted->delete();

    $overview = app(DashboardMetricsService::class)->adminOverview();

    expect($overview['tickets_created']['current'])->toBe(2)
        ->and($overview['tickets_created']['previous'])->toBe(1)
        ->and($overview['tickets_created']['change_percent'])->toBe(100.0);
});

it('merinci atribusi penolakan dari activity dan menghitung RFC tanpa jejak', function () {
    $admin = adminMetricsUser();
    $manager = adminMetricsUser(['Change Manager']);

    $rejectedByAdmin = adminMetricsRfc(['status' => RfcStatus::REJECTED->value]);
    adminMetricsActivity(
        $rejectedByAdmin,
        ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::REJECTED->value, 'note' => 'rahasia admin'],
        now()->subDays(2),
        $admin,
    );

    $rejectedByManager = adminMetricsRfc(['status' => RfcStatus::REJECTED->value]);
    adminMetricsActivity(
        $rejectedByManager,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::REJECTED->value],
        now()->subDay(),
        $manager,
    );

    adminMetricsRfc([
        'status' => RfcStatus::REJECTED->value,
        'updated_at' => now()->subHours(4),
    ]);

    $deleted = adminMetricsRfc(['status' => RfcStatus::REJECTED->value]);
    adminMetricsActivity(
        $deleted,
        ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::REJECTED->value],
        now()->subHour(),
        $admin,
    );
    $deleted->delete();

    $overview = app(DashboardMetricsService::class)->adminOverview();
    $breakdown = collect($overview['rejected']['breakdown'])->keyBy('key');

    expect($overview['rejected']['current'])->toBe(3)
        ->and($breakdown['admin']['count'])->toBe(1)
        ->and($breakdown['change_manager']['count'])->toBe(1)
        ->and($breakdown['unattributed']['count'])->toBe(1)
        ->and($breakdown->sum('count'))->toBe($overview['rejected']['current'])
        ->and(ChangeRequest::query()->where('status', RfcStatus::REJECTED->value)->count())->toBe(3);
});

it('memakai batas 30 hari yang tepat dan waktu penolakan dari activity', function () {
    $service = app(DashboardMetricsService::class);
    $start = $service->periodStart();

    expect($start->toDateTimeString())->toBe('2026-09-27 00:00:00');

    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-BATAS-001',
        'ticket_created_at' => $start->copy(),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-BATAS-002',
        'ticket_created_at' => $start->copy()->addHour(),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-BATAS-003',
        'ticket_created_at' => $start->copy()->subSecond(),
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-BATAS-004',
        'ticket_created_at' => $start->copy()->subDays(31),
    ]);

    $currentRejection = adminMetricsRfc(['status' => RfcStatus::REJECTED->value, 'updated_at' => now()]);
    adminMetricsActivity(
        $currentRejection,
        ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::REJECTED->value],
        $start->copy(),
    );

    $previousRejection = adminMetricsRfc(['status' => RfcStatus::REJECTED->value, 'updated_at' => now()]);
    adminMetricsActivity(
        $previousRejection,
        ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::REJECTED->value],
        $start->copy()->subSecond(),
    );

    $overview = $service->adminOverview();

    expect($overview['tickets_created']['current'])->toBe(2)
        ->and($overview['tickets_created']['previous'])->toBe(1)
        ->and($overview['tickets_created']['change_percent'])->toBe(100.0)
        ->and($overview['rejected']['current'])->toBe(1)
        ->and($overview['rejected']['previous'])->toBe(1)
        ->and($overview['rejected']['change_percent'])->toBe(0.0)
        ->and($overview['trend']['created'])->toHaveCount(30);
});

it('mengelompokkan RFC tengah malam menurut zona waktu WIB dan mengisi hari kosong', function () {
    $service = app(DashboardMetricsService::class);

    $lateNight = adminMetricsRfc(['status' => RfcStatus::SUBMITTED->value]);
    adminMetricsTimestamp($lateNight, [
        'created_at' => Carbon::parse('2026-10-25 23:30:00', 'Asia/Jakarta'),
        'updated_at' => Carbon::parse('2026-10-25 23:30:00', 'Asia/Jakarta'),
    ]);

    $afterMidnight = adminMetricsRfc(['status' => RfcStatus::SUBMITTED->value]);
    adminMetricsTimestamp($afterMidnight, [
        'created_at' => Carbon::parse('2026-10-26 00:30:00', 'Asia/Jakarta'),
        'updated_at' => Carbon::parse('2026-10-26 00:30:00', 'Asia/Jakarta'),
    ]);

    $overview = $service->adminOverview();
    $labels = collect($overview['trend']['table_labels']);
    $october25 = $labels->search('25 Oktober 2026');
    $october26 = $labels->search('26 Oktober 2026');

    expect($october25)->not->toBeFalse()
        ->and($october26)->not->toBeFalse()
        ->and($overview['trend']['created'][$october25])->toBe(1)
        ->and($overview['trend']['created'][$october26])->toBe(1);

    $series = $service->dailySeries(
        [Carbon::parse('2026-10-25 23:30:00', 'Asia/Jakarta')],
        Carbon::parse('2026-10-25 00:00:00', 'Asia/Jakarta'),
        Carbon::parse('2026-10-27 23:59:59', 'Asia/Jakarta'),
    );

    expect($series)->toHaveCount(3)
        ->and($series[0]['date'])->toBe('2026-10-25')
        ->and($series[0]['value'])->toBe(1)
        ->and($series[1]['value'])->toBe(0)
        ->and($series[2]['value'])->toBe(0);
});

it('mengembalikan persentase null dan menampilkan pesan pembanding saat periode sebelumnya kosong', function () {
    $service = app(DashboardMetricsService::class);

    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-PEMBANDING-001',
        'ticket_created_at' => now()->subDay(),
    ]);

    $overview = $service->adminOverview();
    $admin = adminMetricsUser();

    expect($service->percentChange(10, 0))->toBeNull()
        ->and($service->percentChange(10, 5))->toBe(100.0)
        ->and($overview['tickets_created']['change_percent'])->toBeNull()
        ->and($overview['rejected']['change_percent'])->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Belum ada data pembanding')
        ->assertDontSee('+100,0%')
        ->assertDontSee('NaN');
});

it('menampilkan antrean terlama maksimal lima dengan usia tunggu dan tautan review', function () {
    $admin = adminMetricsUser();

    $returnedWithoutActivity = adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-001',
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $returnedWithActivity = adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-002',
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'created_at' => now()->subDays(12),
        'updated_at' => now(),
    ]);
    adminMetricsActivity(
        $returnedWithActivity,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::RETURNED_TO_ADMIN->value],
        now()->subDays(10),
    );
    adminMetricsActivity(
        $returnedWithActivity,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::RETURNED_TO_ADMIN->value],
        now()->subDays(6),
    );

    $submittedFiveDays = adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-003',
        'status' => RfcStatus::SUBMITTED->value,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    $submittedFourDays = adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-004',
        'status' => RfcStatus::SUBMITTED->value,
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);
    $submittedThreeDays = adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-005',
        'status' => RfcStatus::SUBMITTED->value,
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    adminMetricsRfc([
        'rfc_number' => 'RFC-ANTREAN-006',
        'status' => RfcStatus::SUBMITTED->value,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $overview = app(DashboardMetricsService::class)->adminOverview();
    $queue = collect($overview['queue']);

    expect($queue)->toHaveCount(5)
        ->and($queue->pluck('id')->all())->toBe([
            $returnedWithoutActivity->id,
            $returnedWithActivity->id,
            $submittedFiveDays->id,
            $submittedFourDays->id,
            $submittedThreeDays->id,
        ])
        ->and($queue->pluck('wait_days')->all())->toBe([8, 6, 5, 4, 3])
        ->and($queue->firstWhere('id', $returnedWithActivity->id)['waiting_since'])
        ->toContain('2026-10-20');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Buat Tiket')
        ->assertSee(route('change-requests.review', $returnedWithoutActivity->id))
        ->assertSee(route('change-requests.review', $returnedWithActivity->id))
        ->assertSee(route('change-requests.index', ['queue' => 'incoming']))
        ->assertSee('Sedang Ditinjau')
        ->assertSee('Diajukan')
        ->assertDontSee('RFC-ANTREAN-006');
});

it('mengecualikan autentikasi dan RFC terhapus dari aktivitas tanpa membocorkan catatan atau identitas sensitif', function () {
    $admin = adminMetricsUser();
    $actor = adminMetricsUser();
    $actor->forceFill([
        'name' => 'Pelaku Aman',
        'nip' => '198812312010011234',
        'email' => 'pelaku-rahasia@example.test',
    ])->save();

    $active = adminMetricsRfc([
        'rfc_number' => 'RFC-AKTIVITAS-001',
        'status' => RfcStatus::TICKET_CREATED->value,
    ]);
    adminMetricsActivity(
        $active,
        [
            'old_status' => RfcStatus::SUBMITTED->value,
            'new_status' => RfcStatus::TICKET_CREATED->value,
            'note' => 'Catatan rahasia yang tidak boleh tampil',
        ],
        now()->subMinutes(1),
        $actor,
    );

    foreach (range(2, 5) as $index) {
        $rfc = adminMetricsRfc([
            'rfc_number' => 'RFC-AKTIVITAS-00'.$index,
            'status' => RfcStatus::SUBMITTED->value,
        ]);
        adminMetricsActivity(
            $rfc,
            ['old_status' => RfcStatus::DRAFT->value, 'new_status' => RfcStatus::SUBMITTED->value],
            now()->subMinutes($index),
            adminMetricsUser(),
        );
    }

    $oldActivityRfc = adminMetricsRfc([
        'rfc_number' => 'RFC-TUA-001',
        'status' => RfcStatus::NEEDS_REVISION->value,
    ]);
    adminMetricsActivity(
        $oldActivityRfc,
        ['old_status' => RfcStatus::DRAFT->value, 'new_status' => RfcStatus::SUBMITTED->value],
        now()->subMinutes(10),
        $actor,
    );

    $deleted = adminMetricsRfc([
        'rfc_number' => 'RFC-HAPUS-001',
        'status' => RfcStatus::SUBMITTED->value,
    ]);
    $deleted->delete();
    adminMetricsActivity(
        $deleted,
        ['old_status' => RfcStatus::DRAFT->value, 'new_status' => RfcStatus::SUBMITTED->value],
        now(),
        $actor,
    );

    Activity::create([
        'log_name' => 'auth',
        'description' => 'Login berhasil',
        'properties' => ['email' => 'autentikasi@example.test', 'ip' => '127.0.0.1'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $overview = app(DashboardMetricsService::class)->adminOverview();
    $activities = collect($overview['activities']);

    expect($activities)->toHaveCount(5)
        ->and($activities->pluck('rfc_number'))->not->toContain('RFC-TUA-001')
        ->and($activities->pluck('rfc_number'))->not->toContain('RFC-HAPUS-001')
        ->and(array_keys($overview['activities'][0]))->toEqualCanonicalizing([
            'rfc_number',
            'label',
            'actor_name',
            'occurred_at',
        ]);

    $serialized = json_encode($overview['activities'], JSON_THROW_ON_ERROR);

    expect($serialized)->not->toContain('Catatan rahasia')
        ->not->toContain('198812312010011234')
        ->not->toContain('pelaku-rahasia@example.test')
        ->not->toContain('autentikasi@example.test');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Pelaku Aman')
        ->assertSee('menit yang lalu')
        ->assertDontSee('Catatan rahasia yang tidak boleh tampil')
        ->assertDontSee('198812312010011234')
        ->assertDontSee('pelaku-rahasia@example.test')
        ->assertDontSee('autentikasi@example.test')
        ->assertDontSee('RFC-TUA-001')
        ->assertDontSee('RFC-HAPUS-001');
});

it('merender keadaan kosong tanpa error, NaN, atau angka palsu', function () {
    $admin = adminMetricsUser();
    $overview = app(DashboardMetricsService::class)->adminOverview();

    expect($overview['waiting_ticket'])->toBe(0)
        ->and($overview['returned_to_admin'])->toBe(0)
        ->and($overview['needs_revision'])->toBe(0)
        ->and($overview['tickets_created']['current'])->toBe(0)
        ->and($overview['tickets_created']['change_percent'])->toBeNull()
        ->and($overview['rejected']['current'])->toBe(0)
        ->and($overview['rejected']['change_percent'])->toBeNull()
        ->and($overview['queue'])->toBe([])
        ->and($overview['distribution'])->toBe([])
        ->and($overview['activities'])->toBe([])
        ->and(array_sum($overview['trend']['created']))->toBe(0)
        ->and(array_sum($overview['trend']['tickets']))->toBe(0);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Belum ada antrean RFC masuk')
        ->assertSee('Belum ada data grafik')
        ->assertSee('Belum ada data distribusi')
        ->assertSee('Belum ada aktivitas terkini')
        ->assertSee('Belum ada data pembanding')
        ->assertDontSee('NaN')
        ->assertDontSee('Infinity')
        ->assertDontSee('+100')
        ->assertDontSee('Sebagian RFC lama belum memiliki nomor tiket.');
});

it('menampilkan catatan kualitas data hanya saat RFC lanjutan tidak memiliki nomor tiket', function () {
    adminMetricsRfc([
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => null,
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::APPROVED->value,
        'ticket_number' => 'TKT-LENGKAP-001',
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::SUBMITTED->value,
        'ticket_number' => null,
    ]);
    adminMetricsRfc([
        'status' => RfcStatus::REJECTED->value,
        'ticket_number' => null,
    ]);

    $overview = app(DashboardMetricsService::class)->adminOverview();
    $admin = adminMetricsUser();

    expect($overview['quality']['missing_ticket_number'])->toBe(1)
        ->and($overview['quality']['note'])->toBe('Sebagian RFC lama belum memiliki nomor tiket.');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Sebagian RFC lama belum memiliki nomor tiket.');
});

it('merender grafik aksesibel dengan satu titik data dan tabel fallback', function () {
    adminMetricsRfc([
        'rfc_number' => 'RFC-GRAFIK-001',
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-GRAFIK-001',
        'ticket_created_at' => now()->subHour(),
    ]);

    $admin = adminMetricsUser();
    $packageBefore = md5_file(base_path('package.json'));

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('<figure', false)
        ->assertSee('<figcaption', false)
        ->assertSee('role="img"', false)
        ->assertSee('aria-labelledby="tren-30-hari-title tren-30-hari-description"', false)
        ->assertSee('<title>RFC dibuat dan tiket dibuat</title>', false)
        ->assertSee('<desc>Tren harian selama 30 hari terakhir.</desc>', false)
        ->assertSee('<polyline', false)
        ->assertSee('<details', false)
        ->assertSee('<caption', false)
        ->assertSee('scope="col"', false)
        ->assertSee('RFC dibuat')
        ->assertSee('Tiket dibuat')
        ->assertSee('data harian')
        ->assertDontSee('Belum ada data grafik');

    expect(md5_file(base_path('package.json')))->toBe($packageBefore);
});

it('menjaga jumlah query dashboard di bawah 25 per render', function () {
    $admin = adminMetricsUser();

    foreach (range(1, 5) as $index) {
        adminMetricsRfc([
            'rfc_number' => 'RFC-QUERY-00'.$index,
            'status' => RfcStatus::SUBMITTED->value,
            'created_at' => now()->subDays($index),
            'updated_at' => now()->subDays($index),
        ]);
    }

    $returned = adminMetricsRfc([
        'rfc_number' => 'RFC-QUERY-RETURN-001',
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
        'created_at' => now()->subDays(4),
    ]);
    adminMetricsActivity(
        $returned,
        ['old_status' => RfcStatus::TICKET_CREATED->value, 'new_status' => RfcStatus::RETURNED_TO_ADMIN->value],
        now()->subDays(2),
        $admin,
    );

    foreach (range(1, 3) as $index) {
        $rfc = adminMetricsRfc([
            'rfc_number' => 'RFC-QUERY-REJECT-00'.$index,
            'status' => RfcStatus::REJECTED->value,
        ]);
        adminMetricsActivity(
            $rfc,
            ['old_status' => RfcStatus::SUBMITTED->value, 'new_status' => RfcStatus::REJECTED->value],
            now()->subDays($index),
            $admin,
        );
    }

    adminMetricsRfc([
        'rfc_number' => 'RFC-QUERY-TICKET-001',
        'status' => RfcStatus::TICKET_CREATED->value,
        'ticket_number' => 'TKT-QUERY-001',
        'ticket_created_at' => now()->subDay(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(25);
});

it('menolak Pemohon dan Change Manager dari dashboard admin tanpa mengubah dashboard Pemohon', function () {
    $pemohon = adminMetricsUser(['Pemohon']);
    $manager = adminMetricsUser(['Change Manager']);

    $this->actingAs($pemohon)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Pemohon')
        ->assertDontSee('Antrean RFC Masuk');
});
