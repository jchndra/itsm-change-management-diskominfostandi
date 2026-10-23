<?php

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Livewire\ChangeRequests\CancelRfc;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Requester\Dashboard;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create(['name' => 'Pemohon Pembatalan']);
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create(['name' => 'Pemohon Lain']);
    $this->pemohonLain->assignRole('Pemohon');

    $this->admin = User::factory()->create(['name' => 'Admin Pembatalan']);
    $this->admin->assignRole('Admin');

    $this->application = Application::factory()->create();

    $this->makeRfc = function (
        RfcStatus $status = RfcStatus::DRAFT,
        array $attributes = [],
    ): ChangeRequest {
        return ChangeRequest::factory()->create(array_merge([
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'application_id' => $this->application->id,
            'status' => $status->value,
        ], $attributes));
    };
});

it('membatalkan RFC draft dan needs_revision milik sendiri dengan alasan pada activity', function () {
    foreach ([RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION] as $status) {
        $reason = "Alasan pembatalan {$status->value} yang cukup jelas.";
        $rfc = ($this->makeRfc)($status, [
            'rfc_number' => "RFC-2026-91{$status->value}",
            'title' => "RFC {$status->value} untuk dibatalkan",
        ]);

        $attachment = $rfc->attachments()->create([
            'uploaded_by_id' => $this->pemohon->id,
            'original_name' => 'dokumen-pendukung.pdf',
            'path' => 'change-requests/attachments/dokumen-pendukung.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($this->pemohon);

        Livewire::test(CancelRfc::class, ['rfc' => $rfc])
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('Batalkan RFC?')
            ->assertSee('Alasan pembatalan')
            ->set('reason', $reason)
            ->call('cancel')
            ->assertHasNoErrors()
            ->assertRedirect(route('change-requests.show', $rfc))
            ->assertSessionHas('message', 'RFC berhasil dibatalkan');

        $fresh = $rfc->fresh();

        expect($fresh->status)->toBe(RfcStatus::CANCELLED->value)
            ->and($fresh->attachments()->whereKey($attachment->id)->exists())->toBeTrue();

        $activity = Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->get()
            ->first(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::CANCELLED->value);

        expect($activity)->not->toBeNull()
            ->and(data_get($activity->properties, 'old_status'))->toBe($status->value)
            ->and(data_get($activity->properties, 'new_status'))->toBe(RfcStatus::CANCELLED->value)
            ->and(data_get($activity->properties, 'note'))->toBe($reason)
            ->and($activity->causer_id)->toBe($this->pemohon->id);
    }
});

it('menolak alasan pembatalan yang kosong, terlalu pendek, atau terlalu panjang', function () {
    $rfc = ($this->makeRfc)(RfcStatus::DRAFT, ['title' => 'RFC Validasi Pembatalan']);

    $this->actingAs($this->pemohon);

    Livewire::test(CancelRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->set('reason', '')
        ->call('cancel')
        ->assertHasErrors(['reason' => 'required'])
        ->assertSet('showModal', true);

    Livewire::test(CancelRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->set('reason', 'pendek')
        ->call('cancel')
        ->assertHasErrors(['reason' => 'min'])
        ->assertSet('showModal', true);

    Livewire::test(CancelRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->set('reason', str_repeat('A', 501))
        ->call('cancel')
        ->assertHasErrors(['reason' => 'max']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value)
        ->and(Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->get()
            ->contains(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::CANCELLED->value)
        )->toBeFalse();
});

it('memberi 403 kepada pemohon lain dan admin yang memanggil aksi pembatalan', function () {
    $rfc = ($this->makeRfc)(RfcStatus::DRAFT, ['title' => 'RFC Milik Pemohon']);

    foreach ([$this->pemohonLain, $this->admin] as $user) {
        $this->actingAs($user);

        Livewire::test(CancelRfc::class, ['rfc' => $rfc])
            ->set('reason', 'Alasan pembatalan yang cukup panjang.')
            ->call('cancel')
            ->assertForbidden();

        expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value);
    }
});

it('menolak pembatalan untuk RFC dengan status selain draft dan needs_revision', function () {
    $statuses = [
        RfcStatus::SUBMITTED,
        RfcStatus::TICKET_CREATED,
        RfcStatus::RETURNED_TO_ADMIN,
        RfcStatus::APPROVED,
        RfcStatus::IN_PROGRESS,
        RfcStatus::IMPLEMENTED,
        RfcStatus::FAILED,
        RfcStatus::CLOSED,
        RfcStatus::REJECTED,
        RfcStatus::CANCELLED,
    ];

    foreach ($statuses as $status) {
        $rfc = ($this->makeRfc)($status, [
            'rfc_number' => "RFC-2026-92{$status->value}",
        ]);

        $this->actingAs($this->pemohon);

        Livewire::test(CancelRfc::class, ['rfc' => $rfc])
            ->set('reason', 'Alasan pembatalan yang cukup panjang.')
            ->call('cancel')
            ->assertForbidden();

        expect($rfc->fresh()->status)->toBe($status->value);
    }
});

it('tidak mengizinkan RFC yang sudah dibatalkan diedit, dihapus, atau dibatalkan lagi', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CANCELLED, [
        'rfc_number' => 'RFC-2026-9301',
        'title' => 'RFC Sudah Dibatalkan',
    ]);

    $this->actingAs($this->pemohon);

    $this->get(route('change-requests.edit', $rfc))->assertForbidden();

    Livewire::test(CancelRfc::class, ['rfc' => $rfc])
        ->set('reason', 'Alasan pembatalan yang cukup panjang.')
        ->call('cancel')
        ->assertForbidden();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Dibatalkan')
        ->assertDontSee('Edit RFC')
        ->assertDontSee('Revisi RFC')
        ->assertDontSee('Batalkan RFC')
        ->assertDontSee('Hapus RFC');

    Livewire::test(ListChangeRequests::class)
        ->assertSee($rfc->rfc_number)
        ->assertDontSee('Hapus RFC');

    Livewire::test(Dashboard::class)
        ->assertSee($rfc->rfc_number)
        ->assertDontSee('Hapus RFC');

    expect($rfc->fresh()->status)->toBe(RfcStatus::CANCELLED->value)
        ->and($rfc->fresh()->trashed())->toBeFalse();
});

it('menampilkan Dibatalkan dengan alasan dan pelaku serta tanpa langkah mendatang untuk pemohon dan staf', function () {
    $reason = 'Fitur tidak jadi dilaksanakan karena kebutuhan bisnis berubah.';
    $rfc = ($this->makeRfc)(RfcStatus::DRAFT, [
        'title' => 'RFC Riwayat Pembatalan',
    ]);

    $this->actingAs($this->pemohon);

    Livewire::test(CancelRfc::class, ['rfc' => $rfc])
        ->call('openModal')
        ->set('reason', $reason)
        ->call('cancel')
        ->assertHasNoErrors();

    $historyService = app(RfcHistoryService::class);

    foreach ([false, true] as $forStaff) {
        $history = $historyService->build($rfc->fresh(), $forStaff);
        $cancelled = collect($history['events'])->firstWhere('key', RfcStatus::CANCELLED->value);

        expect($cancelled)->not->toBeNull()
            ->and($cancelled['label'])->toBe('Dibatalkan')
            ->and($cancelled['note'])->toBe($reason)
            ->and($cancelled['actor_name'])->toBe($this->pemohon->name)
            ->and($cancelled['actor_role'])->toBe('Pemohon')
            ->and($history['next_steps'])->toBe([]);
    }

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Dibatalkan')
        ->assertSee('Alasan Pembatalan')
        ->assertSee($reason)
        ->assertSee($this->pemohon->name)
        ->assertDontSee('Langkah Berikutnya');

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Dibatalkan')
        ->assertSee('Alasan Pembatalan')
        ->assertSee($reason)
        ->assertSee($this->pemohon->name)
        ->assertDontSee('Langkah Berikutnya');
});

it('menampilkan RFC dibatalkan di daftar tetapi tidak di kartu atau widget dashboard', function () {
    $rfc = ($this->makeRfc)(RfcStatus::CANCELLED, [
        'rfc_number' => 'RFC-2026-9401',
        'title' => 'RFC Dibatalkan di Daftar',
    ]);

    $this->actingAs($this->pemohon);

    Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertSee($rfc->rfc_number)
        ->assertSee('Dibatalkan')
        ->set('statusFilter', RfcStatus::CANCELLED->value)
        ->assertSee($rfc->rfc_number)
        ->assertSee('Dibatalkan');

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee($rfc->rfc_number)
        ->assertSee('Tidak ada RFC aktif yang sedang diproses.')
        ->assertViewHas('stats', fn (array $stats): bool => collect($stats)->sum() === 0
            && ! array_key_exists(RfcStatus::CANCELLED->value, $stats))
        ->assertViewHas('latestActiveRfc', fn ($active): bool => $active === null);
});

it('tidak mengubah status jika transisi pembatalan gagal', function () {
    $rfc = ($this->makeRfc)(RfcStatus::DRAFT, [
        'title' => 'RFC Gagal Dibatalkan',
    ]);

    $failTransition = true;

    ChangeRequest::updating(function (ChangeRequest $model) use (&$failTransition): void {
        if ($failTransition && $model->isDirty('status') && $model->status === RfcStatus::CANCELLED->value) {
            throw new InvalidRfcStatusTransitionException('Simulasi transisi pembatalan gagal.');
        }
    });

    $this->actingAs($this->pemohon);

    try {
        Livewire::test(CancelRfc::class, ['rfc' => $rfc])
            ->call('openModal')
            ->set('reason', 'Alasan pembatalan yang cukup panjang.')
            ->call('cancel')
            ->assertHasErrors('reason');
    } finally {
        $failTransition = false;
    }

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value)
        ->and(Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->get()
            ->contains(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::CANCELLED->value)
        )->toBeFalse();
});

it('hanya menampilkan tombol pembatalan untuk pemilik dengan status yang tepat', function () {
    $draft = ($this->makeRfc)(RfcStatus::DRAFT, ['title' => 'Draft dapat dibatalkan']);
    $revision = ($this->makeRfc)(RfcStatus::NEEDS_REVISION, ['title' => 'Revisi dapat dibatalkan']);
    $submitted = ($this->makeRfc)(RfcStatus::SUBMITTED, ['title' => 'Sudah diajukan']);
    $approved = ($this->makeRfc)(RfcStatus::APPROVED, ['title' => 'Sudah disetujui']);
    $cancelled = ($this->makeRfc)(RfcStatus::CANCELLED, ['title' => 'Sudah dibatalkan']);

    $this->actingAs($this->pemohon);

    expect($this->pemohon->can('cancel', $draft))->toBeTrue()
        ->and($this->pemohon->can('cancel', $revision))->toBeTrue()
        ->and($this->pemohon->can('cancel', $submitted))->toBeFalse()
        ->and($this->pemohon->can('cancel', $approved))->toBeFalse()
        ->and($this->pemohon->can('cancel', $cancelled))->toBeFalse();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $draft])
        ->assertSee('Batalkan RFC');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $revision])
        ->assertSee('Batalkan RFC');

    foreach ([$submitted, $approved, $cancelled] as $rfc) {
        Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
            ->assertOk()
            ->assertDontSee('Batalkan RFC');
    }

    Livewire::test(ShowChangeRequest::class, ['rfc' => $draft])
        ->assertSee('Batalkan RFC');

    $this->get(route('change-requests.edit', $draft))
        ->assertOk()
        ->assertSee('Batalkan RFC');

    $this->actingAs($this->admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $draft])
        ->assertOk()
        ->assertDontSee('Batalkan RFC');

    $this->actingAs($this->pemohonLain);

    Livewire::test(CancelRfc::class, ['rfc' => $draft])
        ->assertDontSee('Batalkan RFC');

    $this->actingAs($this->pemohon);

    Livewire::test(EditChangeRequest::class, ['rfc' => $draft])
        ->assertSee('Batalkan RFC');
});
