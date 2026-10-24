<?php

use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Exceptions\InvalidRfcStatusTransitionException;
use App\Exceptions\RfcImplementationException;
use App\Livewire\ChangeRequests\CloseRfc;
use App\Livewire\ChangeRequests\ImplementChange;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Evidence;
use App\Models\User;
use App\Services\RfcHistoryService;
use App\Services\RfcImplementationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

function implementationUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function implementationRfc(RfcStatus $status, ?User $requester = null): ChangeRequest
{
    $attributes = ['status' => $status->value];

    if ($requester) {
        $attributes['submitted_by_id'] = $requester->id;
        $attributes['created_by_id'] = $requester->id;
    }

    return ChangeRequest::factory()->create($attributes);
}

/**
 * @return array<string, mixed>
 */
function validImplementationFields(array $overrides = []): array
{
    return array_merge([
        'implementation_result' => ImplementationResult::SUCCESS->value,
        'started_at' => now()->subHour()->format('Y-m-d\TH:i'),
        'finished_at' => now()->format('Y-m-d\TH:i'),
        'executor_name' => 'Tim Rilis Diskominfostandi',
        'report_source' => 'Laporan tertulis Tim Rilis',
        'summary' => 'Perubahan berhasil diterapkan pada server produksi.',
        'test_results' => 'Pengujian fungsional dan regresi berhasil.',
        'failure_notes' => '',
        'follow_up_action' => '',
        'follow_up_notes' => '',
        'confirmed' => true,
    ], $overrides);
}

function implementationPdf(string $name = 'bukti-implementasi.pdf', int $kilobytes = 100): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

function recordImplementation(
    ChangeRequest $rfc,
    User $actor,
    array $overrides = [],
    ?array $files = null,
): ChangeRequestImplementation {
    return app(RfcImplementationService::class)->record(
        $rfc,
        $actor,
        validImplementationFields($overrides),
        $files ?? [implementationPdf()],
    );
}

test('CM mencatat implementasi sukses dengan bukti privat dan activity audit', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $file = implementationPdf();

    $this->actingAs($cm);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', [$file])
        ->call('saveImplementation')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc->id));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::IMPLEMENTED->value);

    $implementation = ChangeRequestImplementation::where('change_request_id', $rfc->id)->sole();

    expect($implementation->recorded_by_id)->toBe($cm->id)
        ->and($implementation->implementation_result)->toBe(ImplementationResult::SUCCESS->value)
        ->and($implementation->executor_name)->toBe('Tim Rilis Diskominfostandi')
        ->and($implementation->summary)->toBe('Perubahan berhasil diterapkan pada server produksi.')
        ->and($implementation->follow_up_action)->toBeNull();

    $evidence = Evidence::where('change_request_id', $rfc->id)->sole();

    expect($evidence->evidence_type)->toBe('implementation')
        ->and($evidence->release_task_id)->toBeNull()
        ->and($evidence->uploaded_by_id)->toBe($cm->id)
        ->and($evidence->original_filename)->toBe('bukti-implementasi.pdf')
        ->and($evidence->mime_type)->toBe('application/pdf')
        ->and($evidence->file_size)->toBe(102400)
        ->and(strlen($evidence->checksum_sha256))->toBe(64)
        ->and($evidence->file_path)->toStartWith('implementation-evidences/'.$rfc->id.'/')
        ->and($evidence->file_path)->toEndWith('.pdf');

    Storage::disk('local')->assertExists($evidence->file_path);
    Storage::disk('public')->assertMissing($evidence->file_path);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $activity): bool => data_get($activity->properties, 'new_status') === RfcStatus::IMPLEMENTED->value);

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($cm->id)
        ->and(data_get($activity->properties, 'implementation_result'))->toBe(ImplementationResult::SUCCESS->value)
        ->and(data_get($activity->properties, 'executor_name'))->toBe('Tim Rilis Diskominfostandi')
        ->and(data_get($activity->properties, 'started_at'))->not->toBeNull()
        ->and(data_get($activity->properties, 'finished_at'))->not->toBeNull()
        ->and(data_get($activity->properties, 'report_source'))->toBe('Laporan tertulis Tim Rilis')
        ->and(data_get($activity->properties, 'summary'))->toBe('Perubahan berhasil diterapkan pada server produksi.')
        ->and(data_get($activity->properties, 'test_results'))->toBe('Pengujian fungsional dan regresi berhasil.')
        ->and(data_get($activity->properties, 'evidence_count'))->toBe(1)
        ->and(data_get($activity->properties, 'evidence_files.0.name'))->toBe('bukti-implementasi.pdf')
        ->and(data_get($activity->properties, 'evidence_files.0.size'))->toBe(102400)
        ->and(data_get($activity->properties, 'evidence_files.0.mime'))->toBe('application/pdf')
        ->and(data_get($activity->properties, 'evidence_files.0.checksum'))->toBe($evidence->checksum_sha256);
});

test('hasil gagal mewajibkan catatan dan tindak lanjut beserta keterangannya', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    $component = Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields([
            'implementation_result' => ImplementationResult::FAILED->value,
        ]))
        ->set('files', [implementationPdf()]);

    $component->call('saveImplementation')
        ->assertHasErrors(['failure_notes', 'follow_up_action']);

    $component
        ->set('failure_notes', 'Perubahan gagal diterapkan dan layanan dipulihkan.')
        ->set('follow_up_action', FollowUpAction::NEW_RFC->value)
        ->call('saveImplementation')
        ->assertHasErrors(['follow_up_notes']);

    $component
        ->set('follow_up_notes', 'Akan diajukan RFC baru setelah analisis akar masalah.')
        ->call('saveImplementation')
        ->assertHasNoErrors();

    $implementation = ChangeRequestImplementation::where('change_request_id', $rfc->id)->sole();

    expect($rfc->fresh()->status)->toBe(RfcStatus::FAILED->value)
        ->and($implementation->implementation_result)->toBe(ImplementationResult::FAILED->value)
        ->and($implementation->failure_notes)->toBe('Perubahan gagal diterapkan dan layanan dipulihkan.')
        ->and($implementation->follow_up_action)->toBe(FollowUpAction::NEW_RFC->value)
        ->and($implementation->follow_up_notes)->toBe('Akan diajukan RFC baru setelah analisis akar masalah.');
});

test('hasil rollback membuat status failed dan tindak lanjut none tidak mewajibkan keterangan', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields([
            'implementation_result' => ImplementationResult::ROLLED_BACK->value,
            'failure_notes' => 'Perubahan di-rollback ke versi sebelumnya.',
            'follow_up_action' => FollowUpAction::NONE->value,
        ]))
        ->set('files', [implementationPdf()])
        ->call('saveImplementation')
        ->assertHasNoErrors();

    $implementation = ChangeRequestImplementation::where('change_request_id', $rfc->id)->sole();

    expect($rfc->fresh()->status)->toBe(RfcStatus::FAILED->value)
        ->and($implementation->implementation_result)->toBe(ImplementationResult::ROLLED_BACK->value)
        ->and($implementation->failure_notes)->toBe('Perubahan di-rollback ke versi sebelumnya.')
        ->and($implementation->follow_up_action)->toBe(FollowUpAction::NONE->value)
        ->and($implementation->follow_up_notes)->toBeNull();
});

test('validasi menolak isian wajib kosong tanpa bukti dan tanpa konfirmasi', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields([
            'executor_name' => '',
            'report_source' => '',
            'summary' => '',
            'test_results' => '',
            'confirmed' => false,
        ]))
        ->call('saveImplementation')
        ->assertHasErrors([
            'executor_name',
            'report_source',
            'summary',
            'test_results',
            'files',
            'confirmed',
        ]);

    expect(ChangeRequestImplementation::count())->toBe(0)
        ->and(Evidence::count())->toBe(0);
});

test('validasi menolak waktu masa depan dan waktu selesai sebelum waktu mulai', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    $component = Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields([
            'started_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'finished_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
        ]))
        ->set('files', [implementationPdf()]);

    $component->call('saveImplementation')
        ->assertHasErrors(['started_at', 'finished_at']);

    $component
        ->set('started_at', now()->subHour()->format('Y-m-d\TH:i'))
        ->set('finished_at', now()->subHours(2)->format('Y-m-d\TH:i'))
        ->call('saveImplementation')
        ->assertHasErrors(['finished_at']);
});

test('validasi menolak ZIP mime palsu dan berkas lebih dari 5 MB', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', [UploadedFile::fake()->create('bukti.zip', 100, 'application/zip')])
        ->call('saveImplementation')
        ->assertHasErrors(['files.0']);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', [UploadedFile::fake()->create('bukti.pdf', 100, 'application/zip')])
        ->call('saveImplementation')
        ->assertHasErrors(['files.0']);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', [UploadedFile::fake()->create('bukti-besar.pdf', 5121, 'application/pdf')])
        ->call('saveImplementation')
        ->assertHasErrors(['files.0']);

    expect(Evidence::count())->toBe(0);
});

test('validasi menolak lebih dari lima berkas bukti', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    $this->actingAs($cm);

    $files = collect(range(1, 6))
        ->map(fn (int $index): UploadedFile => implementationPdf("bukti-{$index}.pdf"))
        ->all();

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', $files)
        ->call('saveImplementation')
        ->assertHasErrors(['files']);

    expect(Evidence::count())->toBe(0);
});

test('pemohon dan admin mendapat 403 di halaman implementasi', function () {
    $requester = implementationUser('Pemohon');
    $admin = implementationUser('Admin');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);

    foreach ([$requester, $admin] as $user) {
        $this->actingAs($user)
            ->get(route('change-requests.implement', $rfc))
            ->assertForbidden();
    }

    Livewire::actingAs($requester)
        ->test(ImplementChange::class, ['rfc' => $rfc])
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test(ImplementChange::class, ['rfc' => $rfc])
        ->assertForbidden();
});

test('RFC dengan status selain approved dan in_progress ditolak di halaman implementasi', function () {
    $cm = implementationUser('Change Manager');

    foreach ([
        RfcStatus::DRAFT,
        RfcStatus::SUBMITTED,
        RfcStatus::IMPLEMENTED,
        RfcStatus::FAILED,
        RfcStatus::CLOSED,
    ] as $status) {
        $rfc = implementationRfc($status);

        $this->actingAs($cm)
            ->get(route('change-requests.implement', $rfc))
            ->assertForbidden();
    }
});

test('service menolak implementasi kedua pada RFC yang sama', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED);

    recordImplementation($rfc, $cm);

    expect(fn () => recordImplementation($rfc->fresh(), $cm))
        ->toThrow(RfcImplementationException::class);

    expect(ChangeRequestImplementation::where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and(Evidence::where('change_request_id', $rfc->id)->count())->toBe(1);
});

test('service menolak Super Admin pada RFC berstatus salah', function () {
    $superAdmin = implementationUser('Super Admin');
    $rfc = implementationRfc(RfcStatus::DRAFT);

    expect(fn () => recordImplementation($rfc, $superAdmin))
        ->toThrow(RfcImplementationException::class);

    expect(ChangeRequestImplementation::count())->toBe(0)
        ->and(Evidence::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('implementation-evidences'))->toBe([]);
});

test('kegagalan di tengah transaksi menghapus file dan tidak menyisakan baris', function () {
    $actor = User::factory()->create();
    $rfc = implementationRfc(RfcStatus::APPROVED);

    expect(fn () => recordImplementation($rfc, $actor))
        ->toThrow(InvalidRfcStatusTransitionException::class);

    expect(ChangeRequestImplementation::count())->toBe(0)
        ->and(Evidence::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('implementation-evidences'))->toBe([])
        ->and($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value);
});

test('staf dapat melihat dan mengunduh bukti implementasi', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);

    recordImplementation($rfc, $cm, [], [implementationPdf('bukti-staf.pdf')]);

    $evidence = Evidence::where('change_request_id', $rfc->id)->sole();

    $this->actingAs($cm);

    $this->get(route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]))
        ->assertOk();

    $this->get(route('change-requests.evidences.show', [
        'rfc' => $rfc,
        'evidence' => $evidence,
        'download' => 1,
    ]))->assertDownload('bukti-staf.pdf');

    $html = Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])->html();

    expect($html)->not->toContain('implementation-evidences')
        ->and($html)->not->toContain('storage/app/private');
});

test('pemohon tidak dapat membuka bukti dan evidence RFC lain menghasilkan 404', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);

    recordImplementation($rfc, $cm);

    $evidence = Evidence::where('change_request_id', $rfc->id)->sole();

    $this->actingAs($requester)
        ->get(route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]))
        ->assertForbidden();

    $otherRfc = implementationRfc(RfcStatus::APPROVED, $requester);
    recordImplementation($otherRfc, $cm, [], [implementationPdf('bukti-rfc-lain.pdf')]);
    $otherEvidence = Evidence::where('change_request_id', $otherRfc->id)->sole();

    $this->actingAs($cm)
        ->get(route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $otherEvidence]))
        ->assertNotFound();
});

test('bukti lama di disk public tetap dapat dibaca sebagai cadangan', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    Storage::disk('public')->put('evidences/bukti-lama.pdf', '%PDF-1.4 lama');

    $evidence = Evidence::create([
        'change_request_id' => $rfc->id,
        'uploaded_by_id' => $cm->id,
        'evidence_type' => 'uat_signoff',
        'file_path' => 'evidences/bukti-lama.pdf',
        'original_filename' => 'bukti-lama.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'checksum_sha256' => str_repeat('a', 64),
    ]);

    $this->actingAs($cm)
        ->get(route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]))
        ->assertOk();
});

test('pemohon hanya melihat hasil ringkasan catatan tindak lanjut dan jumlah bukti', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);

    recordImplementation($rfc, $cm, [
        'implementation_result' => ImplementationResult::FAILED->value,
        'executor_name' => 'Nama Pelaksana Rahasia',
        'report_source' => 'Sumber Laporan Rahasia',
        'summary' => 'Ringkasan hasil yang boleh dilihat Pemohon.',
        'test_results' => 'Hasil pengujian rahasia internal.',
        'failure_notes' => 'Catatan kegagalan yang boleh dilihat Pemohon.',
        'follow_up_action' => FollowUpAction::ESCALATION->value,
        'follow_up_notes' => 'Keterangan tindak lanjut untuk Pemohon.',
    ], [implementationPdf('bukti-rahasia.pdf')]);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Hasil Implementasi')
        ->assertSee('Gagal')
        ->assertSee('Ringkasan hasil yang boleh dilihat Pemohon.')
        ->assertSee('Catatan kegagalan yang boleh dilihat Pemohon.')
        ->assertSee('Eskalasi atau investigasi')
        ->assertSee('Keterangan tindak lanjut untuk Pemohon.')
        ->assertSee('Bukti pendukung: 1 file')
        ->assertDontSee('Nama Pelaksana Rahasia')
        ->assertDontSee('Sumber Laporan Rahasia')
        ->assertDontSee('Hasil pengujian rahasia internal.')
        ->assertDontSee('bukti-rahasia.pdf')
        ->assertDontSee('Unduh');

    $history = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $serialized = json_encode($history);
    $implementationEvent = collect($history['events'])->firstWhere('implementation', true);

    expect($implementationEvent)->not->toBeNull()
        ->and($implementationEvent['note'])->toContain('Hasil: Gagal')
        ->and($implementationEvent['note'])->toContain('Ringkasan: Ringkasan hasil yang boleh dilihat Pemohon.')
        ->and($implementationEvent['actor_name'])->toBeNull()
        ->and($serialized)->not->toContain('Nama Pelaksana Rahasia')
        ->and($serialized)->not->toContain('Sumber Laporan Rahasia')
        ->and($serialized)->not->toContain('Hasil pengujian rahasia internal.')
        ->and($serialized)->not->toContain('bukti-rahasia.pdf');

    $staffHistory = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $staffEvent = collect($staffHistory['events'])->firstWhere('implementation', true);

    expect($staffEvent)->not->toBeNull()
        ->and($staffEvent['note'])->toContain('Pelaksana: Nama Pelaksana Rahasia')
        ->and($staffEvent['note'])->toContain('Jumlah bukti: 1 file')
        ->and($staffEvent['note'])->toContain('bukti-rahasia.pdf');
});

test('route upload-evidence tidak ada dan tombol catat implementasi hanya untuk CM pada status tepat', function () {
    expect(Route::has('change-requests.upload-evidence'))->toBeFalse();

    $cm = implementationUser('Change Manager');
    $admin = implementationUser('Admin');
    $requester = implementationUser('Pemohon');
    $approved = implementationRfc(RfcStatus::APPROVED, $requester);
    $implemented = implementationRfc(RfcStatus::IMPLEMENTED, $requester);

    $this->actingAs($cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertSee('Catat Implementasi')
        ->assertSee(route('change-requests.implement', $approved));

    Livewire::test(ShowChangeRequest::class, ['rfc' => $implemented])
        ->assertOk()
        ->assertDontSee('Catat Implementasi');

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertDontSee('Catat Implementasi');

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertDontSee('Catat Implementasi');
});

test('RFC lama tanpa data implementasi tetap tampil tanpa error', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::SUBMITTED, $requester);

    $this->actingAs($cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Hasil Implementasi');

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Hasil Implementasi');
});

test('change manager dapat menutup rfc berstatus failed', function () {
    $cm = implementationUser('Change Manager');

    $rfc = ChangeRequest::factory()->create([
        'status' => RfcStatus::FAILED->value,
    ]);

    $this->actingAs($cm);

    Livewire::test(CloseRfc::class, ['rfc' => $rfc])
        ->set('closure_notes', 'Tiket ditutup setelah rollback diverifikasi.')
        ->call('closeTicket')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::CLOSED->value);
});
