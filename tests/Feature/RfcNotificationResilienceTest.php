<?php

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\RfcStatusChangedNotification;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Tes ini membutuhkan commit transaksi sungguhan agar callback afterCommit
    // berjalan, sehingga tidak memakai RefreshDatabase yang membungkus tes
    // dalam transaksi. Migrasi dijalankan ulang pada koneksi in-memory.
    $this->artisan('migrate:fresh');
    $this->seed(PermissionSeeder::class);
});

function rfcNotificationUser(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

function rfcNotificationThrowingRecipient(User $user): User
{
    $recipient = new class extends User
    {
        public function notify($instance)
        {
            throw new RuntimeException('Kanal notifikasi tidak tersedia.');
        }
    };

    $recipient->forceFill([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => true,
    ]);

    $recipient->exists = true;

    return $recipient;
}

it('tetap menyimpan perubahan status dan activity log ketika notifikasi gagal', function () {
    $pemohon = rfcNotificationUser('Pemohon');
    $admin = rfcNotificationUser('Admin');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    $rfc->setRelation('submittedBy', rfcNotificationThrowingRecipient($pemohon));

    Log::spy();

    RfcStatusTransition::transitionTo(
        $rfc,
        RfcStatus::NEEDS_REVISION,
        $admin,
        'Perlu perbaikan sebelum diproses.',
    );

    expect($rfc->fresh()->status)->toBe(RfcStatus::NEEDS_REVISION->value);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => ChangeRequest::class,
        'subject_id' => $rfc->id,
        'causer_id' => $admin->id,
    ]);

    Log::shouldHaveReceived('warning')->once();
});

it('menunda notifikasi sampai transaksi terluar selesai di-commit', function () {
    Notification::fake();
    config(['rfc.notify_after_commit' => true]);

    $pemohon = rfcNotificationUser('Pemohon');
    $admin = rfcNotificationUser('Admin');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    DB::transaction(function () use ($rfc, $admin): void {
        RfcStatusTransition::transitionTo(
            $rfc,
            RfcStatus::NEEDS_REVISION,
            $admin,
            'Perlu perbaikan sebelum diproses.',
        );

        Notification::assertNothingSent();
    });

    Notification::assertSentTo($pemohon, RfcStatusChangedNotification::class);

    $this->assertDatabaseHas('change_requests', [
        'id' => $rfc->id,
        'status' => RfcStatus::NEEDS_REVISION->value,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => ChangeRequest::class,
        'subject_id' => $rfc->id,
        'causer_id' => $admin->id,
    ]);
});
