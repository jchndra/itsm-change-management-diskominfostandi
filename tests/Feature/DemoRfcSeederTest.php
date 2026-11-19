<?php

use App\Enums\RfcStatus;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\RfcHistoryService;
use Database\Seeders\DemoRfcSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $opd = Opd::factory()->create(['code' => 'PD01']);

    $this->pemohon = User::factory()->create([
        'is_active' => true,
        'opd_id' => $opd->id,
    ]);
    $this->pemohon->assignRole('Pemohon');

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('Admin');

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Change Manager');

    Application::factory()->count(2)->create(['opd_id' => $opd->id]);

    $this->releaseTeamMembers = collect([
        'Tim Rilis Aplikasi',
        'Tim Rilis Pusat Data',
        'Tim Rilis Infrastruktur Jaringan',
    ])->map(function (string $name): ReleaseTeamMember {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole('Tim Rilis');

        return ReleaseTeamMember::factory()
            ->linkedTo($user)
            ->create(['name' => $name, 'is_active' => true]);
    });

    $this->seed(DemoRfcSeeder::class);
});

it('membuat RFC demo untuk seluruh status dengan pemilik akun Pemohon', function () {
    $rfcs = ChangeRequest::query()->get();

    $statuses = collect(RfcStatus::cases())
        ->map(fn (RfcStatus $status): string => $status->value)
        ->sort()
        ->values()
        ->all();

    expect($rfcs)->toHaveCount(14)
        ->and($rfcs->pluck('status')->unique()->sort()->values()->all())->toBe($statuses)
        ->and($rfcs->pluck('submitted_by_id')->unique()->values()->all())->toBe([$this->pemohon->id])
        ->and($rfcs->pluck('created_by_id')->unique()->values()->all())->toBe([$this->pemohon->id]);
});

it('memberi setiap akun Tim Rilis tugas yang dapat dikerjakan', function () {
    $metrics = app(DashboardMetricsService::class);

    $states = $this->releaseTeamMembers->mapWithKeys(function (ReleaseTeamMember $member) use ($metrics): array {
        $overview = $metrics->releaseTeamOverview($member->user);

        return [
            $member->name => [
                'states' => collect($overview['tasks'])->pluck('state')->all(),
                'done_this_month' => $overview['counts']['done_this_month'],
            ],
        ];
    });

    expect($states['Tim Rilis Aplikasi']['states'])->toContain('pending')
        ->and($states['Tim Rilis Pusat Data']['states'])->toContain('doing')
        ->and($states['Tim Rilis Infrastruktur Jaringan']['states'])->toContain('late');

    $this->releaseTeamMembers->each(function (ReleaseTeamMember $member) use ($states): void {
        expect($states[$member->name]['states'])->not->toBeEmpty()
            ->and($states[$member->name]['done_this_month'])->toBeGreaterThanOrEqual(1);
    });
});

it('menyiapkan antrean Change Manager dan tugas yang belum dianalisis', function () {
    $overview = app(DashboardMetricsService::class)->changeManagerOverview();

    expect($overview['total'])->toBe(14)
        ->and($overview['pending_review'])->toBe(2)
        ->and($overview['approved'])->toBe(1);

    $approvedTask = ReleaseTask::query()
        ->whereHas('changeRequest', function ($query): void {
            $query->where('status', RfcStatus::APPROVED->value);
        })
        ->first();

    expect($approvedTask)->not->toBeNull()
        ->and($approvedTask->estimated_days)->toBeNull()
        ->and($approvedTask->riskAnalysis()->exists())->toBeFalse();
});

it('dapat menyusun riwayat seluruh RFC demo', function () {
    $history = app(RfcHistoryService::class);

    ChangeRequest::query()
        ->with(['riskAssessment', 'decisions', 'submittedBy'])
        ->get()
        ->each(function (ChangeRequest $rfc) use ($history): void {
            expect($history->build($rfc, true)['events'])->not->toBeEmpty();
        });
});

it('menampilkan dashboard setiap peran tanpa galat', function () {
    $this->releaseTeamMembers->each(function (ReleaseTeamMember $member): void {
        $this->actingAs($member->user)
            ->get(route('release-team.dashboard'))
            ->assertOk();
    });

    $this->actingAs(User::role('Change Manager')->first())
        ->get(route('change-manager.dashboard'))
        ->assertOk();

    $this->actingAs(User::role('Admin')->first())
        ->get(route('admin.dashboard'))
        ->assertOk();

    $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk();
});

it('menampilkan halaman detail seluruh RFC demo untuk pemohon', function () {
    ChangeRequest::query()->each(function (ChangeRequest $rfc): void {
        $this->actingAs($this->pemohon)
            ->get(route('change-requests.show', $rfc))
            ->assertOk();
    });
});

it('dapat dijalankan ulang tanpa menggandakan RFC dan tanpa menghapus akun atau aplikasi', function () {
    $userCount = User::count();
    $applicationCount = Application::count();

    $this->seed(DemoRfcSeeder::class);

    expect(ChangeRequest::count())->toBe(14)
        ->and(ReleaseTask::count())->toBe(6)
        ->and(User::count())->toBe($userCount)
        ->and(Application::count())->toBe($applicationCount);
});
