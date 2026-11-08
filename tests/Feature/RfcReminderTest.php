<?php

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\RfcReminderLog;
use App\Models\User;
use App\Services\RfcReminderService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-04 08:00:00', 'Asia/Jakarta'));

    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Pengingat', 'is_active' => true]);
    $this->pemohon->assignRole('Pemohon');

    $this->cm = User::factory()->create(['name' => 'CM Pengingat', 'is_active' => true]);
    $this->cm->assignRole('Change Manager');

    $this->cmNonaktif = User::factory()->create(['name' => 'CM Nonaktif', 'is_active' => false]);
    $this->cmNonaktif->assignRole('Change Manager');

    $this->timRilis = User::factory()->create(['name' => 'Tim Rilis Pengingat', 'is_active' => true]);
    $this->timRilis->assignRole('Tim Rilis');

    $this->makeRfc = function (RfcStatus $status, array $attributes = []): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'status' => $status->value,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ], $attributes));
    };

    $this->assignRelease = function (ChangeRequest $rfc, array $attributes = []): ReleaseTask {
        $member = ReleaseTeamMember::factory()->linkedTo($this->timRilis)->create();

        return ReleaseTask::factory()->forRfc($rfc)->assignedTo($member)->create(array_merge([
            'assigned_by_id' => $this->cm->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ], $attributes));
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

it('mengingatkan Change Manager untuk RFC yang menunggu review melewati ambang', function () {
    $rfc = ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(4),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['pending_review'])->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(1)
        ->and($this->cmNonaktif->notifications()->count())->toBe(0);

    $data = $this->cm->notifications()->firstOrFail()->data;

    expect(data_get($data, 'event'))->toBe('reminder_pending_review')
        ->and(data_get($data, 'message'))
        ->toBe("RFC {$rfc->rfc_number} sudah 4 hari menunggu review Anda")
        ->and(data_get($data, 'url'))->toBe(route('change-requests.show', ['rfc' => $rfc->id]))
        ->and(data_get($data, 'status'))->toBe(RfcStatus::TICKET_CREATED->value);

    $log = RfcReminderLog::query()->firstOrFail();

    expect($log->kind)->toBe('pending_review')
        ->and($log->change_request_id)->toBe($rfc->id)
        ->and($log->sent_at)->not->toBeNull();
});

it('tidak mengirim pengingat sebelum ambang hari terpenuhi', function () {
    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(2),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['pending_review'])->toBe(0)
        ->and($this->cm->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0);
});

it('mengingatkan Change Manager untuk RFC disetujui tanpa disposisi', function () {
    $rfc = ($this->makeRfc)(RfcStatus::APPROVED);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['approved_unassigned'])->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(1);

    expect(data_get($this->cm->notifications()->firstOrFail()->data, 'message'))
        ->toBe("RFC {$rfc->rfc_number} sudah 10 hari disetujui tanpa disposisi Tim Rilis");
});

it('mengingatkan Tim Rilis tertugas dan Change Manager untuk tugas yang belum dimulai', function () {
    $rfc = ($this->makeRfc)(RfcStatus::APPROVED);

    ($this->assignRelease)($rfc, [
        'execution_status' => 'pending',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['task_not_started'])->toBe(2)
        ->and($this->timRilis->notifications()->count())->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(1)
        ->and($this->pemohon->notifications()->count())->toBe(0);

    expect(data_get($this->timRilis->notifications()->firstOrFail()->data, 'message'))
        ->toBe("Tugas RFC {$rfc->rfc_number} belum dimulai selama 3 hari");
});

it('mengingatkan Tim Rilis, Change Manager, dan Pemohon untuk tugas yang melewati estimasi', function () {
    $rfc = ($this->makeRfc)(RfcStatus::IN_PROGRESS);

    ($this->assignRelease)($rfc, [
        'execution_status' => 'in_progress',
        'scheduled_start' => now()->subDays(5),
        'scheduled_end' => now()->subDays(2),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['task_overdue'])->toBe(3)
        ->and($this->timRilis->notifications()->count())->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(1)
        ->and($this->pemohon->notifications()->count())->toBe(1);

    expect(data_get($this->timRilis->notifications()->firstOrFail()->data, 'message'))
        ->toBe("Tugas RFC {$rfc->rfc_number} melewati estimasi 2 hari");
});

it('mengingatkan Change Manager untuk RFC yang menunggu evaluasi', function () {
    $rfc = ($this->makeRfc)(RfcStatus::IMPLEMENTED, [
        'updated_at' => now()->subDays(4),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['waiting_evaluation'])->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(1);

    expect(data_get($this->cm->notifications()->firstOrFail()->data, 'message'))
        ->toBe("RFC {$rfc->rfc_number} sudah 4 hari menunggu evaluasi Anda");
});

it('mencegah duplikat selama cooldown dan mengirim ulang setelahnya', function () {
    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(4),
    ]);

    $service = app(RfcReminderService::class);

    $service->run();
    expect($this->cm->notifications()->count())->toBe(1);

    Carbon::setTestNow(now()->addDay());
    $service->run();
    expect($this->cm->notifications()->count())->toBe(1);

    Carbon::setTestNow(now()->addDay());
    $service->run();
    expect($this->cm->notifications()->count())->toBe(2)
        ->and(RfcReminderLog::query()->count())->toBe(1);
});

it('mengirim lagi ketika episode baru memiliki since_at berbeda', function () {
    $rfc = ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(4),
    ]);

    $service = app(RfcReminderService::class);

    $service->run();
    expect($this->cm->notifications()->count())->toBe(1);

    $rfc->forceFill(['ticket_created_at' => now()->subDays(5)])->save();

    $service->run();

    expect($this->cm->notifications()->count())->toBe(2)
        ->and(RfcReminderLog::query()->count())->toBe(2);
});

it('mengabaikan RFC terhapus, pengguna nonaktif, dan RFC yang sudah berpindah status', function () {
    $trashed = ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(6),
    ]);
    $trashed->delete();

    $moved = ($this->makeRfc)(RfcStatus::APPROVED, [
        'ticket_created_at' => now()->subDays(6),
        'updated_at' => now()->subDay(),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['pending_review'])->toBe(0)
        ->and($summary['approved_unassigned'])->toBe(0)
        ->and($this->cm->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0)
        ->and($moved->status)->toBe(RfcStatus::APPROVED->value);
});

it('tidak mengirim ke pengguna nonaktif', function () {
    $this->cm->update(['is_active' => false]);

    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(5),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect($summary['pending_review'])->toBe(0)
        ->and($this->cm->notifications()->count())->toBe(0)
        ->and($this->cmNonaktif->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0);
});

it('tidak mengirim atau menulis log pada mode dry-run', function () {
    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(4),
    ]);

    $summary = app(RfcReminderService::class)->run(true);

    expect($summary['pending_review'])->toBe(1)
        ->and($this->cm->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0);
});

it('tidak mengirim apa pun ketika pengingat dinonaktifkan', function () {
    config(['rfc.reminders.enabled' => false]);

    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(6),
    ]);

    $summary = app(RfcReminderService::class)->run();

    expect(array_sum($summary))->toBe(0)
        ->and($this->cm->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0);
});

it('mencetak ringkasan dan mengirim melalui command pengingat', function () {
    ($this->makeRfc)(RfcStatus::TICKET_CREATED, [
        'ticket_created_at' => now()->subDays(4),
    ]);

    $this->artisan('rfc:send-reminders', ['--dry-run' => true])
        ->expectsOutputToContain('Menunggu review')
        ->expectsOutputToContain('Total')
        ->assertExitCode(0);

    expect($this->cm->notifications()->count())->toBe(0)
        ->and(RfcReminderLog::query()->count())->toBe(0);

    $this->artisan('rfc:send-reminders')->assertExitCode(0);

    expect($this->cm->notifications()->count())->toBe(1)
        ->and(RfcReminderLog::query()->count())->toBe(1);
});
