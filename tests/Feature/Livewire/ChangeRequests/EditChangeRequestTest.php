<?php

use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\Requester\Dashboard;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\User;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create();
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create();
    $this->pemohonLain->assignRole('Pemohon');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->application = Application::factory()->create();

    $this->actingAs($this->pemohon);
});

function editableRfc(
    User $owner,
    Application $application,
    RfcStatus $status = RfcStatus::DRAFT,
    array $attributes = [],
): ChangeRequest {
    return ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'created_by_id' => $owner->id,
        'status' => $status->value,
        'application_id' => $application->id,
        'target_implementation_date' => now()->addDays(5),
        ...$attributes,
    ]);
}

function attachApprovalLetterForTests(ChangeRequest $rfc, User $uploader): ChangeRequestAttachment
{
    return $rfc->attachments()->create([
        'uploaded_by_id' => $uploader->id,
        'document_type' => ChangeRequestAttachment::TYPE_APPROVAL_LETTER,
        'original_name' => 'surat-persetujuan.pdf',
        'path' => 'rfc-attachments/'.$rfc->id.'/surat-persetujuan.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

it('allows the owner to open the edit page for draft and needs_revision RFCs', function () {
    $draft = editableRfc($this->pemohon, $this->application, RfcStatus::DRAFT);
    $revision = editableRfc($this->pemohon, $this->application, RfcStatus::NEEDS_REVISION, [
        'rejection_note' => 'Lengkapi dokumen pendukung dan perjelas dampak.',
    ]);

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.edit', $draft))
        ->assertOk()
        ->assertSee('Edit RFC')
        ->assertDontSee('Alasan Permintaan Revisi dari Admin');

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.edit', $revision))
        ->assertOk()
        ->assertSee('Revisi RFC')
        ->assertSee('Alasan Permintaan Revisi dari Admin')
        ->assertSee('Lengkapi dokumen pendukung dan perjelas dampak.');
});

it('forbids another requester from opening the edit page', function () {
    $draft = editableRfc($this->pemohon, $this->application, RfcStatus::DRAFT);

    $this->actingAs($this->pemohonLain)
        ->get(route('change-requests.edit', $draft))
        ->assertForbidden();
});

it('forbids editing RFCs that are already submitted or approved', function () {
    $submitted = editableRfc($this->pemohon, $this->application, RfcStatus::SUBMITTED);
    $approved = editableRfc($this->pemohon, $this->application, RfcStatus::APPROVED);

    foreach ([$submitted, $approved] as $rfc) {
        $this->actingAs($this->pemohon)
            ->get(route('change-requests.edit', $rfc))
            ->assertForbidden();
    }
});

it('saves changes without changing the status and records the changed fields', function () {
    $rfc = editableRfc($this->pemohon, $this->application, RfcStatus::DRAFT, [
        'title' => 'Judul Awal',
    ]);
    $oldTitle = $rfc->title;

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->set('title', 'Judul Setelah Edit')
        ->call('saveChanges')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc));

    $fresh = $rfc->fresh();

    expect($fresh->title)->toBe('Judul Setelah Edit')
        ->and($fresh->status)->toBe(RfcStatus::DRAFT->value);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'RFC direvisi oleh Pemohon')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'changes.title.old'))->toBe($oldTitle)
        ->and(data_get($activity->properties, 'changes.title.new'))->toBe('Judul Setelah Edit');
});

it('submits a revision, changes status to submitted, and clears rejection_note', function () {
    $rfc = editableRfc($this->pemohon, $this->application, RfcStatus::NEEDS_REVISION, [
        'rejection_note' => 'Perbaiki alasan perubahan.',
    ]);
    attachApprovalLetterForTests($rfc, $this->pemohon);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->set('reason_for_change', 'Alasan perubahan yang sudah diperbaiki dan lengkap.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::SUBMITTED->value)
        ->and($fresh->rejection_note)->toBeNull();

    // RFC yang dikirim ulang harus kembali terlihat di antrean Admin.
    $this->actingAs($this->admin)
        ->get(route('change-requests.index'))
        ->assertOk()
        ->assertSee($rfc->rfc_number);
});

it('keeps the previous revision reason in the process history after resubmitting', function () {
    $reason = 'Lengkapi dokumen pendukung dan jelaskan dampak operasional.';

    // RFC legacy: rejection_note ada, tetapi activity kebutuhan revisi belum memiliki note.
    $rfc = editableRfc($this->pemohon, $this->application, RfcStatus::NEEDS_REVISION, [
        'rejection_note' => $reason,
    ]);
    attachApprovalLetterForTests($rfc, $this->pemohon);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('submit')
        ->assertHasNoErrors();

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $revisionEvent = collect($history['events'])->firstWhere('key', 'revision_reason');

    expect($revisionEvent)->not->toBeNull()
        ->and($revisionEvent['note'])->toContain($reason);

    $requesterHistory = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $requesterEvent = collect($requesterHistory['events'])->firstWhere('key', 'revision_reason');

    expect($requesterEvent)->not->toBeNull()
        ->and($requesterEvent['note'])->toContain($reason);
});

it('validates empty title, past date, and physical letter number over 100 characters', function () {
    $rfc = editableRfc($this->pemohon, $this->application, RfcStatus::DRAFT, [
        'title' => 'Judul Tetap',
    ]);

    $component = Livewire::test(EditChangeRequest::class, ['rfc' => $rfc]);

    $component
        ->set('title', '')
        ->call('saveChanges')
        ->assertHasErrors(['title' => 'required']);

    $component
        ->set('title', 'Judul Valid')
        ->set('target_implementation_date', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('saveChanges')
        ->assertHasErrors('target_implementation_date');

    $component
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('is_physical_letter', true)
        ->set('physical_letter_number', str_repeat('A', 101))
        ->call('saveChanges')
        ->assertHasErrors('physical_letter_number');

    expect($rfc->fresh()->title)->toBe('Judul Tetap');
});

it('uses the same 100 character limit on the create form', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan dengan nomor surat terlalu panjang')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Deskripsi perubahan yang cukup jelas.')
        ->set('reason_for_change', 'Alasan perubahan yang cukup panjang.')
        ->set('impact_description', 'Dampak perubahan yang cukup jelas.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('is_physical_letter', true)
        ->set('physical_letter_number', str_repeat('B', 101))
        ->call('save', RfcStatus::SUBMITTED->value)
        ->assertHasErrors('physical_letter_number');
});

it('rolls back data changes when the status transition fails', function () {
    $rfc = editableRfc($this->pemohon, $this->application, RfcStatus::NEEDS_REVISION, [
        'title' => 'Judul Sebelum Gagal',
        'rejection_note' => 'Alasan revisi tetap ada.',
    ]);
    attachApprovalLetterForTests($rfc, $this->pemohon);

    $failTransition = true;

    ChangeRequest::updating(function (ChangeRequest $model) use (&$failTransition): void {
        if ($failTransition && $model->isDirty('status') && $model->status === RfcStatus::SUBMITTED->value) {
            throw new InvalidRfcStatusTransitionException('Simulasi transisi gagal.');
        }
    });

    try {
        Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
            ->set('title', 'Judul Yang Harus Dibatalkan')
            ->call('submit')
            ->assertHasErrors('status');
    } finally {
        $failTransition = false;
    }

    $fresh = $rfc->fresh();

    expect($fresh->title)->toBe('Judul Sebelum Gagal')
        ->and($fresh->status)->toBe(RfcStatus::NEEDS_REVISION->value)
        ->and($fresh->rejection_note)->toBe('Alasan revisi tetap ada.');
});

it('links edit actions from the requester dashboard and list to the edit page', function () {
    $draft = editableRfc($this->pemohon, $this->application, RfcStatus::DRAFT);

    $this->actingAs($this->pemohon);

    Livewire::test(Dashboard::class)
        ->assertSee(route('change-requests.edit', $draft));

    Livewire::test(ListChangeRequests::class)
        ->assertSee(route('change-requests.edit', $draft));
});
