<?php

use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('local');

    $this->pemohon = User::factory()->create([
        'opd_id' => Opd::factory()->create(['code' => 'PD01'])->id,
    ]);
    $this->pemohon->assignRole('Pemohon');

    $this->application = Application::factory()->create();

    $this->actingAs($this->pemohon);
});

/**
 * @return array<string, string>
 */
function approvalLetterFormFields(Application $application, string $title): array
{
    return [
        'title' => $title,
        'application_id' => (string) $application->id,
        'change_type' => 'normal',
        'description' => 'Deskripsi perubahan untuk pengujian persetujuan Kepala Dinas.',
        'legal_basis' => 'Dasar hukum perubahan untuk pengujian persetujuan Kepala Dinas.',
        'risk_event' => RiskEvent::CYBER_INCIDENT->value,
        'reason_for_change' => 'Alasan perubahan yang cukup jelas untuk pengujian.',
        'change_risk' => 'Risiko perubahan untuk pengujian persetujuan Kepala Dinas.',
        'impact_description' => 'Dampak perubahan yang perlu diketahui pemohon.',
    ];
}

function approvalLetterDraft(User $owner, Application $application, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'created_by_id' => $owner->id,
        'application_id' => $application->id,
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
        'risk_event' => RiskEvent::CYBER_INCIDENT,
        ...$attributes,
    ]);
}

function storedApprovalLetter(ChangeRequest $rfc, User $uploader, string $name = 'surat-persetujuan.pdf'): ChangeRequestAttachment
{
    $path = 'rfc-attachments/'.$rfc->id.'/'.$name;
    Storage::disk('local')->put($path, 'konten surat persetujuan');

    return $rfc->attachments()->create([
        'uploaded_by_id' => $uploader->id,
        'document_type' => ChangeRequestAttachment::TYPE_APPROVAL_LETTER,
        'original_name' => $name,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

function storedSupportingAttachment(ChangeRequest $rfc, User $uploader, string $name = 'lampiran-pendukung.pdf'): ChangeRequestAttachment
{
    $path = 'rfc-attachments/'.$rfc->id.'/'.$name;
    Storage::disk('local')->put($path, 'konten lampiran pendukung');

    return $rfc->attachments()->create([
        'uploaded_by_id' => $uploader->id,
        'document_type' => ChangeRequestAttachment::TYPE_SUPPORTING,
        'original_name' => $name,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
}

it('menyimpan draft tanpa nomor resmi dan tanpa pernyataan persetujuan', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Draft Tanpa Pernyataan'))
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->rfc_number)->toBeNull()
        ->and($rfc->displayIdentifier())->toBe('Draft #'.$rfc->id)
        ->and($rfc->head_of_department_approved)->toBeFalse()
        ->and($rfc->head_of_department_approved_at)->toBeNull()
        ->and($rfc->attachments()->count())->toBe(0);
});

it('menuntut pernyataan persetujuan Pimpinan (Eselon II) dan minimal satu lampiran PDF saat pengajuan', function () {
    $newForm = fn () => Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Pengajuan Dengan Pernyataan'));

    // Tanpa pernyataan dan tanpa lampiran.
    $newForm()
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors(['head_of_department_approved', 'attachments']);

    expect(ChangeRequest::count())->toBe(0);

    // Pernyataan dicentang tetapi lampiran masih kosong.
    $newForm()
        ->set('head_of_department_approved', true)
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors(['attachments']);

    expect(ChangeRequest::count())->toBe(0);

    // Lampiran tersedia tetapi pernyataan belum dicentang.
    $newForm()
        ->set('attachments', [UploadedFile::fake()->create('lampiran.pdf', 100, 'application/pdf')])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors(['head_of_department_approved']);

    expect(ChangeRequest::count())->toBe(0);

    $newForm()
        ->set('head_of_department_approved', true)
        ->set('attachments', [UploadedFile::fake()->create('lampiran.pdf', 100, 'application/pdf')])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->rfc_number)->toBe('RFC-PD01-1')
        ->and($rfc->displayIdentifier())->toBe($rfc->rfc_number)
        ->and($rfc->head_of_department_approved)->toBeTrue()
        ->and($rfc->head_of_department_approved_at)->not->toBeNull()
        // Seluruh unggahan baru disimpan sebagai lampiran pendukung, bukan surat persetujuan.
        ->and($rfc->supportingAttachments()->count())->toBe(1)
        ->and($rfc->approvalLetters()->count())->toBe(0);
});

it('menampilkan nomor draft lama sebagai identitas draft, bukan nomor resmi', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application, [
        'rfc_number' => 'RFC-2026-0002',
    ]);

    expect($rfc->displayIdentifier())->toBe('Draft #'.$rfc->id);
});

it('menolak lampiran yang bukan pdf', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Lampiran Bukan PDF'))
        ->set('head_of_department_approved', true)
        ->set('attachments', [UploadedFile::fake()->create(
            'lampiran.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        )])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors('attachments.0');

    expect(ChangeRequest::count())->toBe(0);
});

it('menolak lampiran pdf yang lebih dari 5 mb', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Lampiran Terlalu Besar'))
        ->set('head_of_department_approved', true)
        ->set('attachments', [UploadedFile::fake()->create('lampiran-besar.pdf', 5121, 'application/pdf')])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors('attachments.0');

    expect(ChangeRequest::count())->toBe(0);
});

it('mencatat waktu persetujuan saat pernyataan dicentang dan mengosongkannya saat dibatalkan', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Waktu Persetujuan Tercatat'))
        ->set('head_of_department_approved', true)
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->head_of_department_approved)->toBeTrue()
        ->and($rfc->head_of_department_approved_at)->not->toBeNull();

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->set('head_of_department_approved', false)
        ->call('saveChanges')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->head_of_department_approved)->toBeFalse()
        ->and($fresh->head_of_department_approved_at)->toBeNull()
        // Draft tetap tidak memaksa nomor resmi atau lampiran.
        ->and($fresh->status)->toBe(RfcStatus::DRAFT->value)
        ->and($fresh->rfc_number)->toBeNull();
});

it('mewajibkan pernyataan persetujuan saat mengajukan ulang RFC dari edit', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application);
    storedSupportingAttachment($rfc, $this->pemohon);

    // Lampiran tersedia tetapi pernyataan belum dicentang.
    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->call('submit')
        ->assertHasErrors(['head_of_department_approved']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->fresh()->rfc_number)->toBeNull();

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->set('head_of_department_approved', true)
        ->call('submit')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->rfc_number)->toBe('RFC-PD01-1')
        ->and($fresh->head_of_department_approved)->toBeTrue()
        ->and($fresh->head_of_department_approved_at)->not->toBeNull()
        ->and($fresh->attachments()->count())->toBe(1);
});

it('menolak penghapusan surat persetujuan lama', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application);
    $oldLetter = storedApprovalLetter($rfc, $this->pemohon, 'surat-lama.pdf');

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->call('deleteAttachment', $oldLetter->id)
        ->assertHasErrors('attachment');

    expect($oldLetter->fresh())->not->toBeNull();

    Storage::disk('local')->assertExists($oldLetter->path);
});

it('menampilkan arsip surat persetujuan lama pada halaman detail', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application);
    storedApprovalLetter($rfc, $this->pemohon, 'surat-arsip.pdf');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()->load('attachments')])
        ->assertOk()
        ->assertSee('Arsip Surat Persetujuan Bertanda Tangan')
        ->assertSee('Arsip')
        ->assertSee('surat-arsip.pdf')
        // Panel persetujuan Pimpinan (Eselon II) selalu tampil dengan status saat ini.
        ->assertSee('Persetujuan Pimpinan (Eselon II)')
        ->assertSee('Belum ada pernyataan persetujuan');
});

it('menolak perubahan nomor RFC yang sudah terbit', function () {
    $rfc = ChangeRequest::factory()->create([
        'rfc_number' => 'RFC-2026-0001',
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    expect(fn () => $rfc->update(['rfc_number' => 'RFC-2026-0002']))
        ->toThrow(RuntimeException::class);

    expect($rfc->fresh()->rfc_number)->toBe('RFC-2026-0001');
});
