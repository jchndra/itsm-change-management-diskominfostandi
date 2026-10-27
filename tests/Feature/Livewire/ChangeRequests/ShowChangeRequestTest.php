<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Decision;
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
});

it('allows requester to view their own RFC', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'title' => 'Pembaruan Server B',
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Pembaruan Server B')
        ->assertSee($rfc->rfc_number);
});

it('blocks requester from viewing another users RFC with 403', function () {
    $requester1 = User::factory()->create();
    $requester1->assignRole('Pemohon');

    $requester2 = User::factory()->create(); // Attacker
    $requester2->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester1->id,
    ]);

    $this->actingAs($requester2);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertForbidden(); // Memastikan respons HTTP 403
});

it('shows the edit button only for the owner with an editable status', function () {
    // Perilaku lama: tombol edit disembunyikan karena fitur edit belum ada.
    // Sekarang tombol harus muncul hanya untuk pemilik draft/needs_revision dan tidak untuk RFC terhapus.
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $otherRequester = User::factory()->create();
    $otherRequester->assignRole('Pemohon');

    $draftRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::DRAFT->value,
    ]);

    $revisionRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::NEEDS_REVISION->value,
        'rejection_note' => 'Lengkapi dokumen pendukung.',
    ]);

    $submittedRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::SUBMITTED->value,
    ]);

    $approvedRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    $deletedDraftRfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::DRAFT->value,
    ]);
    $deletedDraftRfc->delete();

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $draftRfc])
        ->assertSee('Edit RFC')
        ->assertDontSee('Revisi RFC');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $revisionRfc])
        ->assertSee('Revisi RFC')
        ->assertDontSee('Edit RFC');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $submittedRfc])
        ->assertDontSee('Edit RFC')
        ->assertDontSee('Revisi RFC');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approvedRfc])
        ->assertDontSee('Edit RFC')
        ->assertDontSee('Revisi RFC');

    Livewire::test(ShowChangeRequest::class, ['rfc' => $deletedDraftRfc])
        ->assertDontSee('Edit RFC')
        ->assertDontSee('Revisi RFC');

    $this->actingAs($otherRequester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $draftRfc])
        ->assertForbidden();
});

it('allows change manager to bypass ownership check', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $manager = User::factory()->create();
    // Policy view() memberi akses lintas pemilik melalui rfc.view_all, bukan rfc.assess_risk.
    $manager->assignRole('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
    ]);

    $this->actingAs($manager);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk(); // Manager tidak terblokir 403
});

it('shows submitted request details and hides the old purpose section', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $opd = Opd::factory()->create(['name' => 'Dinas Komunikasi dan Informatika']);
    $application = Application::factory()->create([
        'name' => 'Sistem Persuratan Digital',
        'opd_id' => $opd->id,
    ]);

    $this->actingAs($requester);

    Livewire::test(CreateChangeRequest::class)
        ->set('title', 'Migrasi Sistem Persuratan')
        ->set('application_id', (string) $application->id)
        ->set('change_type', 'normal')
        ->set('description', "Modul persuratan akan dimigrasikan ke server baru.\nTujuannya menjaga ketersediaan layanan.")
        ->set('reason_for_change', "Server lama sudah tidak layak.\nPerlu migrasi ke server baru.")
        ->set('impact_description', "Layanan surat berhenti sementara.\nPengguna diberi notifikasi.")
        ->set('target_implementation_date', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('is_physical_letter', true)
        ->set('physical_letter_number', 'ND-123/2026')
        ->set('attachments', [UploadedFile::fake()->create('nota-dinas.pdf', 100, 'application/pdf')])
        ->set('approval_letter', UploadedFile::fake()->create('surat-persetujuan.pdf', 100, 'application/pdf'))
        ->call('save', RfcStatus::SUBMITTED->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Sistem Persuratan Digital')
        ->assertSee('Server lama sudah tidak layak.')
        ->assertSee('Perlu migrasi ke server baru.')
        ->assertSee('Layanan surat berhenti sementara.')
        ->assertSee('Pengguna diberi notifikasi.')
        ->assertSee('ND-123/2026')
        ->assertDontSee('Tujuan Perubahan')
        ->assertDontSee('Deskripsi Lengkap');
});

it('shows the rejection reason to the owner of a rejected RFC', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::REJECTED->value,
        'rejection_note' => 'Dokumen pendukung belum lengkap dan tidak dapat diverifikasi.',
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Alasan Penolakan')
        ->assertSee('Dokumen pendukung belum lengkap dan tidak dapat diverifikasi.');
});

it('hides the change manager internal note from the requester on returned_to_admin', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
        'status' => RfcStatus::RETURNED_TO_ADMIN->value,
    ]);

    $authority = Authority::factory()->create();
    Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $authority->id,
        'recorded_by_id' => User::factory()->create()->id,
        'snapshot_authority_name' => $authority->name,
        'snapshot_authority_position' => $authority->position,
        'decision_status' => DecisionStatus::RETURNED_TO_ADMIN->value,
        'decision_date' => now(),
        'reason' => 'Catatan internal CM yang tidak boleh dilihat Pemohon.',
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Catatan internal CM yang tidak boleh dilihat Pemohon.');
});

it('shows the requester name and unit to staff', function () {
    $opd = Opd::factory()->create(['name' => 'Dinas Pendidikan']);
    $requester = User::factory()->create([
        'name' => 'Pemohon Rahasia',
        'opd_id' => $opd->id,
    ]);
    $requester->assignRole('Pemohon');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertSee('Nama Pemohon')
        ->assertSee('Pemohon Rahasia')
        ->assertSee('Unit Kerja')
        ->assertSee('Dinas Pendidikan');
});

it('hides the requester name and unit from the requester', function () {
    $opd = Opd::factory()->create(['name' => 'Dinas Pendidikan']);
    $requester = User::factory()->create([
        'name' => 'Pemohon Rahasia',
        'opd_id' => $opd->id,
    ]);
    $requester->assignRole('Pemohon');

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $requester->id,
    ]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Nama Pemohon')
        ->assertDontSee('Dinas Pendidikan');
});
