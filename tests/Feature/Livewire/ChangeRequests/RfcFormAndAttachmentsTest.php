<?php

use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\User;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    $this->pemohon = User::factory()->create([
        'name' => 'Pemohon Utama',
        'position' => 'Staf Operasional',
        'phone_number' => '081234567890',
    ]);
    $this->pemohon->assignRole('Pemohon');

    $this->pemohonLain = User::factory()->create([
        'name' => 'Pemohon Lain',
        'position' => 'Staf Lain',
        'phone_number' => '081298765432',
    ]);
    $this->pemohonLain->assignRole('Pemohon');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->application = Application::factory()->create();

    $this->actingAs($this->pemohon);
});

function makeRfcForAttachmentTests(User $owner, Application $application, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'created_by_id' => $owner->id,
        'application_id' => $application->id,
        'status' => RfcStatus::DRAFT->value,
        'target_implementation_date' => now()->addDays(3),
        ...$attributes,
    ]);
}

function makeStoredAttachmentForTests(
    ChangeRequest $rfc,
    User $owner,
    string $name = 'surat-pengajuan.pdf',
    string $content = 'konten lampiran',
): ChangeRequestAttachment {
    $path = 'rfc-attachments/'.$rfc->id.'/'.Str::uuid()->toString().'.pdf';
    Storage::disk('local')->put($path, $content);

    return ChangeRequestAttachment::create([
        'change_request_id' => $rfc->id,
        'uploaded_by_id' => $owner->id,
        'original_name' => $name,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => strlen($content),
    ]);
}

it('menyimpan seluruh field baru dan lampiran dari form create lalu menampilkannya di detail', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pembaruan Layanan Persuratan')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->set('reason_for_change', 'Proses tanda tangan manual membutuhkan waktu terlalu lama.')
        ->set('impact_description', 'Layanan persuratan berhenti sementara saat pemeliharaan.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('attachments', [UploadedFile::fake()->create('surat-pengajuan.pdf', 120, 'application/pdf')])
        ->call('save', RfcStatus::SUBMITTED->value)
        ->assertHasNoErrors()
        ->assertRedirect(route('requester.dashboard'));

    $rfc = ChangeRequest::latest('id')->firstOrFail();
    $attachment = $rfc->attachments()->firstOrFail();

    expect($rfc->description)->toBe('Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->and($rfc->change_type)->toBe('normal')
        ->and($rfc->impact_description)->toBe('Layanan persuratan berhenti sementara saat pemeliharaan.')
        ->and($attachment->original_name)->toBe('surat-pengajuan.pdf');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Deskripsi Perubahan')
        ->assertSee('Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->assertSee('Normal')
        ->assertSee('surat-pengajuan.pdf');

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'Lampiran RFC ditambahkan')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'file_name'))->toBe('surat-pengajuan.pdf');

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $event = collect($history['events'])->firstWhere('key', 'attachment_added');

    expect($event)->not->toBeNull()
        ->and($event['note'])->toContain('surat-pengajuan.pdf')
        ->and($event['actor_name'])->toBe($this->pemohon->name);
});

it('menyimpan lampiran di disk privat, bukan disk public', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Lampiran Privat')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'standard')
        ->set('description', 'Lampiran ini harus tersimpan di penyimpanan privat.')
        ->set('reason_for_change', 'Dokumen pendukung bersifat internal.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('attachments', [UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf')])
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $attachment = ChangeRequestAttachment::latest('id')->firstOrFail();

    Storage::disk('local')->assertExists($attachment->path);
    Storage::disk('public')->assertMissing($attachment->path);
});

it('menandai emergency_unconfirmed untuk kategori emergency dan tidak untuk kategori lain', function () {
    $emergency = Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Perbaikan Darurat')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'emergency')
        ->set('description', 'Perbaikan darurat pada layanan yang sedang terganggu.')
        ->set('reason_for_change', 'Layanan utama sedang tidak dapat digunakan.')
        ->set('target_implementation_date', now()->addDays(1)->format('Y-m-d\TH:i'))
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $emergencyRfc = ChangeRequest::latest('id')->firstOrFail();

    expect($emergencyRfc->change_type)->toBe('emergency')
        ->and($emergencyRfc->emergency_unconfirmed)->toBeTrue();

    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Perubahan Normal')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Perubahan normal yang sudah direncanakan.')
        ->set('reason_for_change', 'Peningkatan kualitas layanan secara berkala.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    expect(ChangeRequest::latest('id')->firstOrFail()->emergency_unconfirmed)->toBeFalse();
});

it('menolak lampiran exe, php, lebih dari 5 MB, dan lebih dari 5 file', function () {
    $newForm = fn () => Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengujian Validasi Lampiran')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Menguji validasi jenis dan ukuran lampiran.')
        ->set('reason_for_change', 'Memastikan hanya dokumen pendukung yang aman.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'));

    $newForm()
        ->set('attachments', [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors('attachments.0');

    $newForm()
        ->set('attachments', [UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors('attachments.0');

    $newForm()
        ->set('attachments', [UploadedFile::fake()->create('terlalu-besar.pdf', 5121, 'application/pdf')])
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors('attachments.0');

    $newForm()
        ->set('attachments', collect(range(1, 6))
            ->map(fn (int $index) => UploadedFile::fake()->create("lampiran-{$index}.pdf", 10, 'application/pdf'))
            ->all())
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors('attachments');

    expect(ChangeRequest::count())->toBe(0);
});

it('mengizinkan pemilik dan staf mengunduh lampiran serta menolak pemohon lain', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $attachment = makeStoredAttachmentForTests($rfc, $this->pemohon);

    $this->actingAs($this->pemohon)
        ->get(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]))
        ->assertOk();

    $this->actingAs($this->pemohonLain)
        ->get(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]))
        ->assertForbidden();
});

it('mengembalikan 404 jika lampiran bukan milik RFC pada URL', function () {
    $rfcPemilik = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $rfcLain = makeRfcForAttachmentTests($this->pemohon, $this->application, [
        'title' => 'RFC Lain',
    ]);
    $attachment = makeStoredAttachmentForTests($rfcPemilik, $this->pemohon);

    $this->actingAs($this->admin)
        ->get(route('change-requests.attachments.show', ['rfc' => $rfcLain, 'attachment' => $attachment]))
        ->assertNotFound();
});

it('mewajibkan lampiran surat fisik saat diajukan tetapi tidak saat simpan draft', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Surat Fisik')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Pengajuan dengan surat fisik tanpa lampiran saat draft.')
        ->set('reason_for_change', 'Dokumen fisik akan dilengkapi kemudian.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('is_physical_letter', true)
        ->set('physical_letter_number', 'ND-001/2026')
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->attachments()->count())->toBe(0);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('saveChanges')
        ->assertHasNoErrors();

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('submit')
        ->assertHasErrors('attachments');

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value);

    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Surat Fisik Tanpa Lampiran')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Pengajuan ini tidak menyertakan lampiran.')
        ->set('reason_for_change', 'Menguji kewajiban lampiran surat fisik.')
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('is_physical_letter', true)
        ->set('physical_letter_number', 'ND-002/2026')
        ->call('save', RfcStatus::SUBMITTED->value)
        ->assertHasErrors('attachments');

    expect(ChangeRequest::count())->toBe(1);
});

it('menghapus lampiran dari database dan disk serta mencatat activity untuk staf', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $attachment = makeStoredAttachmentForTests($rfc, $this->pemohon, 'akan-dihapus.pdf');
    $path = $attachment->path;

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('deleteAttachment', $attachment->id)
        ->assertHasNoErrors();

    expect(ChangeRequestAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'Lampiran RFC dihapus')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'event'))->toBe('attachment_deleted')
        ->and(data_get($activity->properties, 'file_name'))->toBe('akan-dihapus.pdf');

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $event = collect($history['events'])->firstWhere('key', 'attachment_deleted');

    expect($event)->not->toBeNull()
        ->and($event['note'])->toContain('akan-dihapus.pdf')
        ->and($event['actor_name'])->toBe($this->pemohon->name);
});

it('menampilkan RFC lama tanpa deskripsi dan lampiran tanpa error', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application, [
        'title' => 'RFC Lama Tanpa Deskripsi',
        'description' => null,
        'impact_description' => null,
        'change_type' => null,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('RFC Lama Tanpa Deskripsi')
        ->assertDontSee('Deskripsi Perubahan')
        ->assertDontSee('Kategori Perubahan')
        ->assertDontSee('>Lampiran</h3>');
});

it('menampilkan data pemohon dan petunjuk lengkapi profil tanpa memblokir form', function () {
    $pemohonTanpaProfil = User::factory()->create([
        'position' => null,
        'phone_number' => null,
    ]);
    $pemohonTanpaProfil->assignRole('Pemohon');

    $this->actingAs($pemohonTanpaProfil);

    Livewire::test(CreateChangeRequest::class)
        ->assertSee('Data Pemohon')
        ->assertSee('Lengkapi profil')
        ->assertSee(route('profile.show'))
        ->assertSee(route('requester.dashboard'))
        ->assertSee('Batal');
});

it('membawa field baru dan lampiran lama pada form edit', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application, [
        'change_type' => 'emergency',
        'description' => 'Deskripsi yang sudah tersimpan sebelumnya.',
        'emergency_unconfirmed' => true,
    ]);
    $attachment = makeStoredAttachmentForTests($rfc, $this->pemohon, 'lampiran-lama.pdf');

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->assertSet('change_type', 'emergency')
        ->assertSet('description', 'Deskripsi yang sudah tersimpan sebelumnya.')
        ->assertSee('lampiran-lama.pdf')
        ->assertSee(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]));
});
