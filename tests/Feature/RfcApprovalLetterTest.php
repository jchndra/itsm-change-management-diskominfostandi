<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
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

    $this->pemohon = User::factory()->create();
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
        'description' => 'Deskripsi perubahan untuk pengujian surat persetujuan.',
        'reason_for_change' => 'Alasan perubahan yang cukup jelas untuk pengujian.',
        'impact_description' => 'Dampak perubahan yang perlu diketahui pemohon.',
        'target_implementation_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
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
        'target_implementation_date' => now()->addDays(5),
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

it('menyimpan draft tanpa nomor resmi dan tanpa surat persetujuan', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Draft Tanpa Nomor'))
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->rfc_number)->toBeNull()
        ->and($rfc->displayIdentifier())->toBe('Draft #'.$rfc->id)
        ->and($rfc->approvalLetters()->count())->toBe(0);
});

it('mewajibkan surat persetujuan pdf dan menerbitkan nomor resmi saat pengajuan', function () {
    $newForm = fn () => Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Pengajuan Dengan Surat Persetujuan'));

    $newForm()
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors('approval_letter');

    expect(ChangeRequest::count())->toBe(0);

    $newForm()
        ->set('approval_letter', UploadedFile::fake()->create(
            'surat-persetujuan.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ))
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors('approval_letter');

    expect(ChangeRequest::count())->toBe(0);

    $newForm()
        ->set('approval_letter', UploadedFile::fake()->create('surat-persetujuan.pdf', 100, 'application/pdf'))
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->rfc_number)->toBe('RFC-'.date('Y').'-0001')
        ->and($rfc->displayIdentifier())->toBe($rfc->rfc_number)
        ->and($rfc->approvalLetters()->count())->toBe(1)
        ->and($rfc->supportingAttachments()->count())->toBe(0);
});

it('menampilkan nomor draft lama sebagai identitas draft, bukan nomor resmi', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application, [
        'rfc_number' => 'RFC-2026-0002',
    ]);

    expect($rfc->displayIdentifier())->toBe('Draft #'.$rfc->id);
});

it('menolak surat persetujuan pdf yang lebih dari 5 mb', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set(approvalLetterFormFields($this->application, 'Surat Persetujuan Terlalu Besar'))
        ->set('approval_letter', UploadedFile::fake()->create('surat-besar.pdf', 5121, 'application/pdf'))
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors('approval_letter');

    expect(ChangeRequest::count())->toBe(0);
});

it('menolak pengajuan draft tanpa surat persetujuan dan menerbitkan nomor saat surat tersedia', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('submit')
        ->assertHasErrors('approval_letter');

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->fresh()->rfc_number)->toBeNull();

    storedApprovalLetter($rfc, $this->pemohon);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->call('submit')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->rfc_number)->toBe('RFC-'.date('Y').'-0001')
        ->and($fresh->approvalLetters()->count())->toBe(1);
});

it('menolak penghapusan surat persetujuan dan menyimpan surat pengganti sebagai riwayat', function () {
    $rfc = approvalLetterDraft($this->pemohon, $this->application);
    $oldLetter = storedApprovalLetter($rfc, $this->pemohon, 'surat-lama.pdf');

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('deleteAttachment', $oldLetter->id)
        ->assertHasErrors('attachment');

    expect($oldLetter->fresh())->not->toBeNull();

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->set('approval_letter', UploadedFile::fake()->create('surat-baru.pdf', 120, 'application/pdf'))
        ->call('saveChanges')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $letters = $fresh->approvalLetters()->get();

    expect($letters)->toHaveCount(2)
        ->and($letters->first()->original_name)->toBe('surat-baru.pdf')
        ->and($fresh->latestApprovalLetter?->original_name)->toBe('surat-baru.pdf');

    // Surat lama tetap tersimpan sebagai bukti audit, bukan ditimpa.
    Storage::disk('local')->assertExists($oldLetter->path);
    Storage::disk('local')->assertExists($letters->first()->path);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $fresh->load('attachments')])
        ->assertOk()
        ->assertSee('Surat Persetujuan Bertanda Tangan')
        ->assertSee('Berlaku')
        ->assertSee('Riwayat surat persetujuan (1)')
        ->assertSee('surat-baru.pdf')
        ->assertSee('surat-lama.pdf');
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
