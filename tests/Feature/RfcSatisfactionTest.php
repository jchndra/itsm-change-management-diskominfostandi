<?php

use App\Enums\RfcStatus;
use App\Exceptions\RfcSatisfactionException;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\RfcSatisfaction;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\RfcSatisfactionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Penilai']);
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create(['name' => 'Pemohon Lain']);
    $this->pemohonLain->assignRole('Pemohon');

    $this->cmAktif = User::factory()->create(['name' => 'Change Manager Aktif', 'is_active' => true]);
    $this->cmAktif->assignRole('Change Manager');

    $this->cmAktifKedua = User::factory()->create(['name' => 'Change Manager Aktif Kedua', 'is_active' => true]);
    $this->cmAktifKedua->assignRole('Change Manager');

    $this->cmNonaktif = User::factory()->create(['name' => 'Change Manager Nonaktif', 'is_active' => false]);
    $this->cmNonaktif->assignRole('Change Manager');

    $this->admin = User::factory()->create(['name' => 'Admin Penilai']);
    $this->admin->assignRole('Admin');

    $this->timRilis = User::factory()->create(['name' => 'Tim Rilis Penilai']);
    $this->timRilis->assignRole('Tim Rilis');

    $this->makeRfc = function (string $status, ?User $owner = null): ChangeRequest {
        return ChangeRequest::factory()->create([
            'submitted_by_id' => ($owner ?? $this->pemohon)->id,
            'created_by_id' => ($owner ?? $this->pemohon)->id,
            'status' => $status,
        ]);
    };
});

it('menyimpan penilaian Pemohon pemilik RFC closed tanpa membocorkan komentar', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $comment = 'Layanan cepat dan komunikatif.';

    $this->actingAs($this->pemohon);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->set('satisfactionRating', 5)
        ->set('satisfactionComment', $comment)
        ->call('submitSatisfaction')
        ->assertHasNoErrors()
        ->assertSee('Penilaian Pemohon')
        ->assertSee('5/5')
        ->assertSee($comment);

    $satisfaction = RfcSatisfaction::query()->where('change_request_id', $rfc->id)->firstOrFail();

    expect($satisfaction->rating)->toBe(5)
        ->and($satisfaction->comment)->toBe($comment)
        ->and($satisfaction->user_id)->toBe($this->pemohon->id);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $item): bool => data_get($item->properties, 'event') === 'satisfaction_submitted');

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'rating'))->toBe(5)
        ->and(json_encode($activity->properties))->not->toContain($comment);

    $notification = $this->cmAktif->notifications()->firstOrFail();

    expect($this->cmAktif->notifications()->count())->toBe(1)
        ->and($this->cmAktifKedua->notifications()->count())->toBe(1)
        ->and($this->cmNonaktif->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(0)
        ->and(data_get($notification->data, 'message'))
        ->toBe("Pemohon memberi penilaian 5/5 untuk RFC {$rfc->rfc_number}")
        ->and(data_get($notification->data, 'url'))->toBe(route('change-requests.show', ['rfc' => $rfc->id]))
        ->and(json_encode($notification->data))->not->toContain($comment);
});

it('menolak rating di luar rentang 1 sampai 5', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    $this->actingAs($this->pemohon);

    foreach ([0, 6] as $invalidRating) {
        Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
            ->set('satisfactionRating', $invalidRating)
            ->call('submitSatisfaction')
            ->assertHasErrors('satisfactionRating');
    }

    expect(RfcSatisfaction::query()->count())->toBe(0);
});

it('menolak RFC yang belum berstatus closed pada policy dan service', function () {
    $rfc = ($this->makeRfc)(RfcStatus::APPROVED->value);

    $this->actingAs($this->pemohon);

    expect(Gate::forUser($this->pemohon)->allows('rate', $rfc))->toBeFalse();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->call('submitSatisfaction')
        ->assertForbidden();

    expect(fn () => app(RfcSatisfactionService::class)->submit($rfc, $this->pemohon, 5, null))
        ->toThrow(RfcSatisfactionException::class, 'Penilaian hanya dapat dikirim untuk RFC yang sudah ditutup.');
});

it('menolak Pemohon yang bukan pemilik RFC', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);

    expect(Gate::forUser($this->pemohonLain)->allows('rate', $rfc))->toBeFalse();

    expect(fn () => app(RfcSatisfactionService::class)->submit($rfc, $this->pemohonLain, 4, null))
        ->toThrow(RfcSatisfactionException::class, 'Hanya Pemohon pemilik RFC yang dapat memberikan penilaian.');
});

it('menolak pengiriman penilaian kedua untuk RFC yang sama', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $service = app(RfcSatisfactionService::class);

    $service->submit($rfc, $this->pemohon, 4, null);

    expect(fn () => $service->submit($rfc, $this->pemohon, 5, null))
        ->toThrow(RfcSatisfactionException::class, 'RFC ini sudah dinilai.')
        ->and(RfcSatisfaction::query()->count())->toBe(1);

    expect(Gate::forUser($this->pemohon)->allows('rate', $rfc->fresh()))->toBeFalse();
});

it('mengizinkan Change Manager dan Admin melihat penilaian tetapi tidak mengirim', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $rfc->satisfaction()->create([
        'user_id' => $this->pemohon->id,
        'rating' => 4,
        'comment' => 'Sudah cukup baik.',
    ]);

    foreach ([$this->cmAktif, $this->admin] as $staff) {
        expect(Gate::forUser($staff)->allows('viewSatisfaction', $rfc))->toBeTrue()
            ->and(Gate::forUser($staff)->allows('rate', $rfc))->toBeFalse();

        $this->actingAs($staff);

        Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
            ->assertOk()
            ->assertSee('Penilaian Pemohon')
            ->assertSee('Sudah cukup baik.')
            ->assertDontSee('Beri Penilaian Layanan');
    }
});

it('menyembunyikan penilaian dari Tim Rilis dan Pemohon lain', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $rfc->satisfaction()->create([
        'user_id' => $this->pemohon->id,
        'rating' => 5,
        'comment' => 'Sangat memuaskan.',
    ]);

    $member = ReleaseTeamMember::factory()->linkedTo($this->timRilis)->create();
    ReleaseTask::factory()->forRfc($rfc)->assignedTo($member)->create();

    expect(Gate::forUser($this->timRilis)->allows('viewSatisfaction', $rfc))->toBeFalse()
        ->and(Gate::forUser($this->pemohonLain)->allows('viewSatisfaction', $rfc))->toBeFalse();

    $this->actingAs($this->timRilis);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertDontSee('Penilaian Pemohon')
        ->assertDontSee('Beri Penilaian Layanan');
});

it('menghitung rata-rata dan jumlah penilaian pada ringkasan Change Manager', function () {
    $first = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $second = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $third = ($this->makeRfc)(RfcStatus::CLOSED->value);
    $deleted = ($this->makeRfc)(RfcStatus::CLOSED->value);

    foreach ([[$first, 5], [$second, 4], [$third, 4], [$deleted, 1]] as [$rfc, $rating]) {
        $rfc->satisfaction()->create([
            'user_id' => $this->pemohon->id,
            'rating' => $rating,
        ]);
    }

    $deleted->delete();

    $satisfaction = app(DashboardMetricsService::class)->changeManagerOverview()['satisfaction'];

    expect($satisfaction['average'])->toBe(4.3)
        ->and($satisfaction['count'])->toBe(3);

    $this->actingAs($this->cmAktif)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Kepuasan 4,3 / 5')
        ->assertSee('3 penilaian');
});

it('menampilkan keadaan kosong saat belum ada penilaian', function () {
    $this->actingAs($this->cmAktif)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Belum ada penilaian');

    $satisfaction = app(DashboardMetricsService::class)->changeManagerOverview()['satisfaction'];

    expect($satisfaction['average'])->toBeNull()
        ->and($satisfaction['count'])->toBe(0);
});
