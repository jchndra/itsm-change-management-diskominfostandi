<?php

use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcAttachmentService;
use App\Services\RfcDeletionService;
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
        ...$attributes,
    ]);
}

function makeStoredAttachmentForTests(
    ChangeRequest $rfc,
    User $owner,
    string $name = 'surat-pengajuan.pdf',
    string $content = 'konten lampiran',
    string $documentType = ChangeRequestAttachment::TYPE_SUPPORTING,
): ChangeRequestAttachment {
    $path = 'rfc-attachments/'.$rfc->id.'/'.Str::uuid()->toString().'.pdf';
    Storage::disk('local')->put($path, $content);

    return ChangeRequestAttachment::create([
        'change_request_id' => $rfc->id,
        'uploaded_by_id' => $owner->id,
        'document_type' => $documentType,
        'original_name' => $name,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => strlen($content),
    ]);
}

function makeStoredApprovalLetterForTests(ChangeRequest $rfc, User $owner): ChangeRequestAttachment
{
    return makeStoredAttachmentForTests(
        $rfc,
        $owner,
        'surat-persetujuan.pdf',
        'konten surat persetujuan',
        ChangeRequestAttachment::TYPE_APPROVAL_LETTER,
    );
}

it('menyimpan seluruh field baru dan lampiran dari form create lalu menampilkannya di detail', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pembaruan Layanan Persuratan')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->set('risk_event', RiskEvent::PERSONAL_DATA_LEAK->value)
        ->set('reason_for_change', 'Proses tanda tangan manual membutuhkan waktu terlalu lama.')
        ->set('legal_basis', 'Peraturan Wali Kota tentang Penyelenggaraan SPBE.')
        ->set('change_risk', 'Kesalahan integrasi dapat menghambat proses persuratan.')
        ->set('impact_description', 'Layanan persuratan berhenti sementara saat pemeliharaan.')
        ->set('head_of_department_approved', true)
        ->set('attachments', [
            UploadedFile::fake()->create('surat-persetujuan.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->create('surat-pengajuan.pdf', 120, 'application/pdf'),
        ])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasNoErrors()
        ->assertRedirect(route('requester.dashboard'));

    $rfc = ChangeRequest::latest('id')->firstOrFail();
    $attachments = $rfc->supportingAttachments()->get();

    // Seluruh unggahan disimpan sebagai lampiran pendukung; tidak ada lagi
    // penunjukan otomatis Surat Persetujuan Bertanda Tangan.
    expect($rfc->description)->toBe('Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->and($rfc->legal_basis)->toBe('Peraturan Wali Kota tentang Penyelenggaraan SPBE.')
        ->and($rfc->change_risk)->toBe('Kesalahan integrasi dapat menghambat proses persuratan.')
        ->and($rfc->change_type)->toBe('normal')
        ->and($rfc->impact_description)->toBe('Layanan persuratan berhenti sementara saat pemeliharaan.')
        ->and($rfc->rfc_number)->not->toBeNull()
        ->and($rfc->head_of_department_approved)->toBeTrue()
        ->and($rfc->head_of_department_approved_at)->not->toBeNull()
        ->and($attachments)->toHaveCount(2)
        ->and($attachments->first()->original_name)->toBe('surat-persetujuan.pdf')
        ->and($attachments->first()->isApprovalLetter())->toBeFalse()
        ->and($rfc->approvalLetters()->count())->toBe(0);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Detail Perubahan')
        ->assertSee('Modul persuratan akan diperbarui agar mendukung tanda tangan digital.')
        ->assertSee('Dasar Hukum')
        ->assertSee('Peraturan Wali Kota tentang Penyelenggaraan SPBE.')
        ->assertSee('Risiko Perubahan')
        ->assertSee('Kesalahan integrasi dapat menghambat proses persuratan.')
        ->assertSee('Normal')
        ->assertSee('surat-pengajuan.pdf')
        ->assertSee('surat-persetujuan.pdf')
        ->assertSee('Persetujuan Kepala Dinas')
        ->assertSee('Disetujui Kepala Dinas');

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->where('description', 'Lampiran RFC ditambahkan')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'file_name'))->toBe('surat-pengajuan.pdf');

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $attachmentNotes = collect($history['events'])
        ->where('key', 'attachment_added')
        ->pluck('note');

    expect($attachmentNotes)->toHaveCount(2)
        ->and($attachmentNotes->implode(' | '))
        ->toContain('surat-pengajuan.pdf')
        ->toContain('surat-persetujuan.pdf')
        ->and(collect($history['events'])->firstWhere('key', 'attachment_added')['actor_name'])
        ->toBe($this->pemohon->name);
});

it('memberikan tautan lampiran pada riwayat proses untuk staf dan tim rilis yang ditugaskan', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $attachment = app(RfcAttachmentService::class)->store(
        $rfc,
        UploadedFile::fake()->create('dokumen-perubahan.pdf', 80, 'application/pdf'),
        $this->pemohon,
    );
    $expectedUrl = route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]);

    // Pemohon: tetap memakai daftar lampiran pada kartu detail, bukan tautan riwayat.
    $this->actingAs($this->pemohon);

    $requesterEvent = collect(app(RfcHistoryService::class)->build($rfc->fresh())['events'])
        ->firstWhere('key', 'attachment_added');

    expect($requesterEvent)->not->toBeNull()
        ->and($requesterEvent['attachment_url'] ?? null)->toBeNull();

    // Staf: nama berkas dan tautan keduanya tersedia.
    $staffEvent = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events'])
        ->firstWhere('key', 'attachment_added');

    expect($staffEvent)->not->toBeNull()
        ->and($staffEvent['note'])->toContain('dokumen-perubahan.pdf')
        ->and($staffEvent['attachment_url'])->toBe($expectedUrl);

    // Tim Rilis yang ditugaskan: tautan tersedia meski catatan tetap disembunyikan.
    $releaseUser = User::factory()->create(['is_active' => true, 'name' => 'Tim Rilis Lampiran']);
    $releaseUser->assignRole('Tim Rilis');
    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $releaseUser->id,
        'is_active' => true,
    ]);
    ReleaseTask::factory()->create([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'pending',
    ]);

    $this->actingAs($releaseUser);

    $releaseEvent = collect(app(RfcHistoryService::class)->build($rfc->fresh(), false, true, true)['events'])
        ->firstWhere('key', 'attachment_added');

    expect($releaseEvent)->not->toBeNull()
        ->and($releaseEvent['attachment_url'])->toBe($expectedUrl)
        ->and($releaseEvent['note'])->toBeNull();

    $this->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->assertSee('Lihat lampiran')
        ->assertSee($expectedUrl, false);

    $this->get($expectedUrl)->assertOk();
});

it('tidak memberikan tautan lampiran pada riwayat untuk berkas yang sudah dihapus', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $service = app(RfcAttachmentService::class);
    $attachment = $service->store(
        $rfc,
        UploadedFile::fake()->create('lampiran-dihapus.pdf', 60, 'application/pdf'),
        $this->pemohon,
    );

    $service->delete($attachment, $this->pemohon);

    $event = collect(app(RfcHistoryService::class)->build($rfc->fresh(), true)['events'])
        ->firstWhere('key', 'attachment_added');

    expect($attachment->fresh())->toBeNull()
        ->and($event)->not->toBeNull()
        ->and($event['attachment_url'] ?? null)->toBeNull();
});

it('menyimpan lampiran di disk privat, bukan disk public', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Lampiran Privat')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'standard')
        ->set('description', 'Lampiran ini harus tersimpan di penyimpanan privat.')
        ->set('risk_event', RiskEvent::REDUNDANT_APPLICATIONS->value)
        ->set('reason_for_change', 'Dokumen pendukung bersifat internal.')
        ->set('legal_basis', 'Kebijakan pengelolaan dokumen internal.')
        ->set('change_risk', 'Akses dokumen yang tidak tepat dapat membocorkan data.')
        ->set('attachments', [UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf')])
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $attachment = ChangeRequestAttachment::latest('id')->firstOrFail();

    Storage::disk('local')->assertExists($attachment->path);
    Storage::disk('public')->assertMissing($attachment->path);

    // Draft juga menyimpan unggahan sebagai lampiran pendukung.
    expect($attachment->document_type)->toBe(ChangeRequestAttachment::TYPE_SUPPORTING)
        ->and($attachment->changeRequest->rfc_number)->toBeNull();
});

it('mencegah double submit membuat dua RFC dan lampiran ganda', function () {
    $component = Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Double Submit')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Pengujian pengiriman form dua kali.')
        ->set('risk_event', RiskEvent::VENDOR_DEPENDENCY->value)
        ->set('reason_for_change', 'Memastikan klik ganda tidak membuat data ganda.')
        ->set('legal_basis', 'Prosedur pengendalian perubahan layanan.')
        ->set('change_risk', 'Pengajuan ganda dapat menimbulkan duplikasi data.')
        ->set('attachments', [UploadedFile::fake()->create('lampiran.pdf', 20, 'application/pdf')]);

    $component->call('save', RfcStatus::DRAFT->value)->assertHasNoErrors();
    $component->call('save', RfcStatus::DRAFT->value)->assertHasNoErrors();

    $rfc = ChangeRequest::sole();

    expect(ChangeRequestAttachment::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('rfc-attachments/'.$rfc->id))->toHaveCount(1);
});

it('mempertahankan file lampiran dan riwayat saat draft dihapus', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $attachment = app(RfcAttachmentService::class)->store(
        $rfc,
        UploadedFile::fake()->create('bukti-audit.pdf', 20, 'application/pdf'),
        $this->pemohon,
    );
    $path = $attachment->path;

    RfcDeletionService::delete($rfc, $this->pemohon, 'Dihapus untuk pengujian retensi.');

    Storage::disk('local')->assertExists($path);
    expect(ChangeRequestAttachment::find($attachment->id))->not->toBeNull();

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => $rfc->getMorphClass(),
        'subject_id' => $rfc->id,
        'description' => 'Lampiran RFC ditambahkan',
    ]);
});

it('menandai emergency_unconfirmed untuk kategori emergency dan tidak untuk kategori lain', function () {
    $emergency = Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Perbaikan Darurat')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'emergency')
        ->set('description', 'Perbaikan darurat pada layanan yang sedang terganggu.')
        ->set('risk_event', RiskEvent::DATACENTER_DISRUPTION->value)
        ->set('reason_for_change', 'Layanan utama sedang tidak dapat digunakan.')
        ->set('legal_basis', 'Prosedur penanganan gangguan layanan prioritas.')
        ->set('change_risk', 'Perbaikan darurat berpotensi menimbulkan gangguan lain.')
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
        ->set('risk_event', RiskEvent::SPBE_TARGET_NOT_MET->value)
        ->set('reason_for_change', 'Peningkatan kualitas layanan secara berkala.')
        ->set('legal_basis', 'Rencana pemeliharaan layanan tahunan.')
        ->set('change_risk', 'Gangguan singkat dapat terjadi saat pemeliharaan.')
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
        ->set('risk_event', RiskEvent::CYBER_INCIDENT->value)
        ->set('reason_for_change', 'Memastikan hanya dokumen pendukung yang aman.')
        ->set('legal_basis', 'Kebijakan keamanan informasi.')
        ->set('change_risk', 'Lampiran berbahaya dapat membahayakan sistem.');

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

it('mengalihkan tamu ke login saat mengunduh lampiran', function () {
    $rfc = makeRfcForAttachmentTests($this->pemohon, $this->application);
    $attachment = makeStoredAttachmentForTests($rfc, $this->pemohon);

    auth()->logout();

    $this->get(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]))
        ->assertRedirect(route('login'));
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

it('mewajibkan lampiran PDF saat diajukan tetapi tidak saat simpan draft', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Tanpa Lampiran')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Pengajuan dengan draft tanpa lampiran.')
        ->set('risk_event', RiskEvent::INSUFFICIENT_PD_CAPACITY->value)
        ->set('reason_for_change', 'Dokumen pendukung akan dilengkapi kemudian.')
        ->set('legal_basis', 'Ketentuan pengajuan perubahan layanan.')
        ->set('change_risk', 'Kelengkapan dokumen dapat tertunda.')
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::DRAFT->value)
        ->and($rfc->head_of_department_approved)->toBeFalse()
        ->and($rfc->attachments()->count())->toBe(0);

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('saveChanges')
        ->assertHasNoErrors();

    Livewire::test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('submit')
        ->assertHasErrors(['attachments', 'head_of_department_approved']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::DRAFT->value);

    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Tanpa Lampiran Sekaligus Tanpa Pernyataan')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Pengajuan ini tidak menyertakan lampiran.')
        ->set('risk_event', RiskEvent::SATISFACTION_SURVEY->value)
        ->set('reason_for_change', 'Menguji kewajiban lampiran saat pengajuan.')
        ->set('legal_basis', 'Ketentuan pengajuan perubahan layanan.')
        ->set('change_risk', 'Pengajuan tanpa lampiran tidak dapat diproses.')
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasErrors(['attachments', 'head_of_department_approved']);

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
        ->assertDontSee('Detail Perubahan')
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
        ->assertSet('legal_basis', $rfc->legal_basis)
        ->assertSet('change_risk', $rfc->change_risk)
        ->assertSee('lampiran-lama.pdf')
        ->assertSee(route('change-requests.attachments.show', ['rfc' => $rfc, 'attachment' => $attachment]));
});

it('menampilkan label dan field detail perubahan, alasan, dasar hukum, serta risiko perubahan', function () {
    Livewire::test(CreateChangeRequest::class)
        ->assertSee('Sistem / Layanan')
        ->assertSee('Detail Perubahan')
        ->assertSee('Alasan Perubahan')
        ->assertSee('Dasar Hukum')
        ->assertSee('Risiko Perubahan')
        ->assertDontSee('Sistem / Aplikasi')
        ->assertDontSee('Apa yang diubah dan tujuannya')
        ->assertDontSee('Kenapa ini harus diubah?');
});

it('mewajibkan alasan, dasar hukum, dan risiko perubahan saat menyimpan RFC', function () {
    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Pengajuan Tanpa Kelengkapan Analisis')
        ->set('application_id', (string) $this->application->id)
        ->set('change_type', 'normal')
        ->set('description', 'Detail perubahan yang akan diajukan.')
        ->set('risk_event', RiskEvent::CYBER_INCIDENT->value)
        ->call('save', RfcStatus::DRAFT->value)
        ->assertHasErrors([
            'reason_for_change' => 'required',
            'legal_basis' => 'required',
            'change_risk' => 'required',
        ]);

    expect(ChangeRequest::count())->toBe(0);
});
