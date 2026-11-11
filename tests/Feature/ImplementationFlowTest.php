<?php

use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Exceptions\RfcClosureException;
use App\Exceptions\RfcEvaluationException;
use App\Exceptions\RfcImplementationException;
use App\Livewire\ChangeRequests\CloseRfc;
use App\Livewire\ChangeRequests\EvaluateRfc;
use App\Livewire\ChangeRequests\ImplementChange;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Evidence;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcClosureService;
use App\Services\RfcEvaluationService;
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

function implementationReleaseTeamActor(ChangeRequest $rfc, array $taskAttributes = []): User
{
    $user = User::factory()->create();
    $user->assignRole('Tim Rilis');

    $member = ReleaseTeamMember::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    ReleaseTask::factory()->create(array_merge([
        'change_request_id' => $rfc->id,
        'release_team_member_id' => $member->id,
        'execution_status' => 'in_progress',
        'estimated_days' => 5,
    ], $taskAttributes));

    return $user;
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

function implementationRecordRow(
    ChangeRequest $rfc,
    User $actor,
    array $overrides = [],
): ChangeRequestImplementation {
    return ChangeRequestImplementation::create(array_merge([
        'change_request_id' => $rfc->id,
        'recorded_by_id' => $actor->id,
        'implementation_result' => ImplementationResult::SUCCESS->value,
        'started_at' => now()->subHour(),
        'finished_at' => now(),
        'executor_name' => 'Tim Rilis Diskominfostandi',
        'report_source' => 'Laporan tertulis Tim Rilis',
        'summary' => 'Perubahan diterapkan pada lingkungan produksi.',
        'test_results' => 'Pengujian fungsional dan regresi berhasil.',
    ], $overrides));
}

function evaluationNotes(string $suffix = 'implementasi'): string
{
    return 'Evaluasi menyeluruh terhadap hasil '.$suffix.' beserta bukti pendukungnya.';
}

test('Tim Rilis mencatat implementasi sukses dengan bukti privat dan activity audit', function () {
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);
    $file = implementationPdf();

    $this->actingAs($team);

    Livewire::test(ImplementChange::class, ['rfc' => $rfc])
        ->set(validImplementationFields())
        ->set('files', [$file])
        ->call('saveImplementation')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc->id));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::IMPLEMENTED->value);

    $implementation = ChangeRequestImplementation::where('change_request_id', $rfc->id)->sole();

    expect($implementation->recorded_by_id)->toBe($team->id)
        ->and($implementation->implementation_result)->toBe(ImplementationResult::SUCCESS->value)
        ->and($implementation->executor_name)->toBe('Tim Rilis Diskominfostandi')
        ->and($implementation->summary)->toBe('Perubahan berhasil diterapkan pada server produksi.')
        ->and($implementation->follow_up_action)->toBeNull();

    $evidence = Evidence::where('change_request_id', $rfc->id)->sole();

    expect($evidence->evidence_type)->toBe('implementation')
        ->and($evidence->release_task_id)->toBeNull()
        ->and($evidence->uploaded_by_id)->toBe($team->id)
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
        ->and($activity->causer_id)->toBe($team->id)
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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    $this->actingAs($team);

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

test('pemohon, admin, dan change manager mendapat 403 di halaman implementasi', function () {
    $requester = implementationUser('Pemohon');
    $admin = implementationUser('Admin');
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);

    foreach ([$requester, $admin, $cm] as $user) {
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

    Livewire::actingAs($cm)
        ->test(ImplementChange::class, ['rfc' => $rfc])
        ->assertForbidden();
});

test('RFC dengan status selain approved dan in_progress ditolak di halaman implementasi', function () {
    foreach ([
        RfcStatus::DRAFT,
        RfcStatus::SUBMITTED,
        RfcStatus::IMPLEMENTED,
        RfcStatus::FAILED,
        RfcStatus::CLOSED,
    ] as $status) {
        $rfc = implementationRfc($status);
        $team = implementationReleaseTeamActor($rfc);

        $this->actingAs($team)
            ->get(route('change-requests.implement', $rfc))
            ->assertForbidden();
    }
});

test('service menolak implementasi kedua pada RFC yang sama', function () {
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    recordImplementation($rfc, $team);

    expect(fn () => recordImplementation($rfc->fresh(), $team))
        ->toThrow(RfcImplementationException::class);

    expect(ChangeRequestImplementation::where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and(Evidence::where('change_request_id', $rfc->id)->count())->toBe(1);
});

test('menolak implementasi saat tugas masih pending pada policy dan service', function () {
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc, [
        'execution_status' => 'pending',
        'estimated_days' => null,
    ]);

    $this->actingAs($team)
        ->get(route('change-requests.implement', $rfc))
        ->assertForbidden();

    expect(fn () => recordImplementation($rfc, $team))
        ->toThrow(
            RfcImplementationException::class,
            'Mulai pengerjaan dan isi estimasi terlebih dahulu sebelum mencatat hasil implementasi.',
        );
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

test('service menegakkan penugasan Tim Rilis termasuk untuk Super Admin dan Change Manager', function () {
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $superAdmin = implementationUser('Super Admin');
    $cm = implementationUser('Change Manager');

    expect(fn () => recordImplementation($rfc, $superAdmin))
        ->toThrow(RfcImplementationException::class);

    expect(fn () => recordImplementation($rfc->fresh(), $cm))
        ->toThrow(RfcImplementationException::class);

    expect(ChangeRequestImplementation::count())->toBe(0)
        ->and(Evidence::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('implementation-evidences'))->toBe([]);
});

test('kegagalan di tengah transaksi menghapus file dan tidak menyisakan baris', function () {
    $rfc = implementationRfc(RfcStatus::APPROVED);
    $team = implementationReleaseTeamActor($rfc);

    ChangeRequest::updated(function (ChangeRequest $updated): void {
        if ($updated->status === RfcStatus::IN_PROGRESS->value) {
            throw new RuntimeException('Simulasi kegagalan implementasi.');
        }
    });

    expect(fn () => recordImplementation($rfc, $team))
        ->toThrow(RuntimeException::class);

    expect(ChangeRequestImplementation::count())->toBe(0)
        ->and(Evidence::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('implementation-evidences'))->toBe([])
        ->and($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value);
});

test('staf dapat melihat dan mengunduh bukti implementasi', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::APPROVED, $requester);
    $team = implementationReleaseTeamActor($rfc);

    recordImplementation($rfc, $team, [], [implementationPdf('bukti-staf.pdf')]);

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
    $team = implementationReleaseTeamActor($rfc);

    recordImplementation($rfc, $team);

    $evidence = Evidence::where('change_request_id', $rfc->id)->sole();

    $this->actingAs($requester)
        ->get(route('change-requests.evidences.show', ['rfc' => $rfc, 'evidence' => $evidence]))
        ->assertForbidden();

    $otherRfc = implementationRfc(RfcStatus::APPROVED, $requester);
    $otherTeam = implementationReleaseTeamActor($otherRfc);
    recordImplementation($otherRfc, $otherTeam, [], [implementationPdf('bukti-rfc-lain.pdf')]);
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
    $team = implementationReleaseTeamActor($rfc);

    recordImplementation($rfc, $team, [
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

test('tombol catat implementasi hanya untuk Tim Rilis yang ditugaskan dan CM memantau pelaksanaan', function () {
    expect(Route::has('change-requests.upload-evidence'))->toBeFalse();

    $cm = implementationUser('Change Manager');
    $admin = implementationUser('Admin');
    $requester = implementationUser('Pemohon');
    $approved = implementationRfc(RfcStatus::APPROVED, $requester);
    $team = implementationReleaseTeamActor($approved);
    $unassignedApproved = implementationRfc(RfcStatus::APPROVED, $requester);
    $implemented = implementationRfc(RfcStatus::IMPLEMENTED, $requester);
    $implementedTeam = implementationReleaseTeamActor($implemented);

    $this->actingAs($team);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertSee('Selesaikan Pekerjaan')
        ->assertSee(route('change-requests.implement', $approved));

    $this->actingAs($implementedTeam);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $implemented])
        ->assertOk()
        ->assertDontSee('Selesaikan Pekerjaan');

    $this->actingAs($cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $unassignedApproved])
        ->assertOk()
        ->assertSee('Disposisi')
        ->assertDontSee('Selesaikan Pekerjaan')
        ->assertDontSee(route('change-requests.implement', $unassignedApproved));

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertSee('Pantau Pelaksanaan')
        ->assertDontSee('Selesaikan Pekerjaan')
        ->assertDontSee(route('change-requests.implement', $approved));

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertDontSee('Selesaikan Pekerjaan');

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $approved])
        ->assertOk()
        ->assertDontSee('Selesaikan Pekerjaan');
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

test('CM mencatat evaluasi untuk RFC implemented dan failed tanpa mengubah status', function () {
    $cm = implementationUser('Change Manager');

    foreach ([
        [RfcStatus::IMPLEMENTED, ImplementationResult::SUCCESS],
        [RfcStatus::FAILED, ImplementationResult::FAILED],
    ] as [$status, $result]) {
        $rfc = implementationRfc($status);
        implementationRecordRow($rfc, $cm, [
            'implementation_result' => $result->value,
            'failure_notes' => $result === ImplementationResult::FAILED
                ? 'Perubahan gagal dan layanan telah dipulihkan.'
                : null,
            'follow_up_action' => $result === ImplementationResult::FAILED
                ? FollowUpAction::NONE->value
                : null,
        ]);

        $notes = evaluationNotes($result->value);
        $updated = app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, $notes);

        expect($rfc->fresh()->status)->toBe($status->value)
            ->and($updated->evaluated_by_id)->toBe($cm->id)
            ->and($updated->evaluated_at)->not->toBeNull()
            ->and($updated->evaluation_notes)->toBe($notes);

        $activity = Activity::query()
            ->where('subject_type', $rfc->getMorphClass())
            ->where('subject_id', $rfc->id)
            ->get()
            ->first(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'evaluation_recorded');

        expect($activity)->not->toBeNull()
            ->and($activity->causer_id)->toBe($cm->id)
            ->and(data_get($activity->properties, 'status'))->toBe($status->value)
            ->and(data_get($activity->properties, 'old_status'))->toBe($status->value)
            ->and(data_get($activity->properties, 'new_status'))->toBe($status->value)
            ->and(data_get($activity->properties, 'implementation_result'))->toBe($result->value)
            ->and(data_get($activity->properties, 'evaluation_notes'))->toBe($notes)
            ->and(data_get($activity->properties, 'evaluated_at'))->not->toBeNull();
    }
});

test('CM mencatat evaluasi lewat halaman evaluasi dengan ringkasan implementasi baca-saja', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    implementationRecordRow($rfc, $cm, [
        'summary' => 'Ringkasan implementasi untuk halaman evaluasi.',
        'test_results' => 'Pengujian evaluasi berhasil.',
        'follow_up_action' => FollowUpAction::ESCALATION->value,
        'follow_up_notes' => 'Tindak lanjut evaluasi untuk staf.',
    ]);

    Evidence::create([
        'change_request_id' => $rfc->id,
        'uploaded_by_id' => $cm->id,
        'evidence_type' => 'implementation',
        'file_path' => 'implementation-evidences/'.$rfc->id.'/bukti-evaluasi.pdf',
        'original_filename' => 'bukti-evaluasi.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'checksum_sha256' => str_repeat('b', 64),
    ]);

    $notes = evaluationNotes('implementasi halaman evaluasi');

    $this->actingAs($cm);

    Livewire::test(EvaluateRfc::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Ringkasan Hasil Implementasi')
        ->assertSee('Tim Rilis Diskominfostandi')
        ->assertSee('Ringkasan implementasi untuk halaman evaluasi.')
        ->assertSee('Pengujian evaluasi berhasil.')
        ->assertSee('Eskalasi atau investigasi')
        ->assertSee('Tindak lanjut evaluasi untuk staf.')
        ->assertSee('bukti-evaluasi.pdf')
        ->set('evaluation_notes', $notes)
        ->call('saveEvaluation')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc->id));

    expect($rfc->fresh()->implementation->evaluation_notes)->toBe($notes);
});

test('evaluasi ganda tanpa implementasi status salah dan catatan pendek ditolak', function () {
    $cm = implementationUser('Change Manager');

    $evaluatedRfc = implementationRfc(RfcStatus::IMPLEMENTED);
    implementationRecordRow($evaluatedRfc, $cm);
    app(RfcEvaluationService::class)->record($evaluatedRfc->fresh(), $cm, evaluationNotes('pertama'));

    expect(fn () => app(RfcEvaluationService::class)->record(
        $evaluatedRfc->fresh(),
        $cm,
        evaluationNotes('kedua'),
    ))->toThrow(RfcEvaluationException::class);

    $withoutImplementation = implementationRfc(RfcStatus::IMPLEMENTED);

    expect(fn () => app(RfcEvaluationService::class)->record(
        $withoutImplementation,
        $cm,
        evaluationNotes('tanpa implementasi'),
    ))->toThrow(RfcEvaluationException::class);

    $wrongStatus = implementationRfc(RfcStatus::APPROVED);
    implementationRecordRow($wrongStatus, $cm);

    expect(fn () => app(RfcEvaluationService::class)->record(
        $wrongStatus,
        $cm,
        evaluationNotes('status salah'),
    ))->toThrow(RfcEvaluationException::class);

    $shortNotesRfc = implementationRfc(RfcStatus::IMPLEMENTED);
    implementationRecordRow($shortNotesRfc, $cm);

    $this->actingAs($cm);

    Livewire::test(EvaluateRfc::class, ['rfc' => $shortNotesRfc])
        ->set('evaluation_notes', 'Terlalu pendek')
        ->call('saveEvaluation')
        ->assertHasErrors(['evaluation_notes']);

    expect(fn () => app(RfcEvaluationService::class)->record(
        $shortNotesRfc->fresh(),
        $cm,
        'Terlalu pendek',
    ))->toThrow(RfcEvaluationException::class);

    expect($shortNotesRfc->fresh()->implementation->evaluated_at)->toBeNull();
});

test('penutupan sebelum evaluasi ditolak lewat komponen URL dan service Super Admin', function () {
    $cm = implementationUser('Change Manager');
    $superAdmin = implementationUser('Super Admin');

    foreach ([RfcStatus::IMPLEMENTED, RfcStatus::FAILED] as $status) {
        $rfc = implementationRfc($status);
        implementationRecordRow($rfc, $cm);

        Livewire::actingAs($cm)
            ->test(CloseRfc::class, ['rfc' => $rfc])
            ->assertForbidden();

        $this->actingAs($cm)
            ->get(route('change-requests.close', $rfc))
            ->assertForbidden();

        expect(fn () => app(RfcClosureService::class)->close(
            $rfc->fresh(),
            $cm,
            'Catatan penutupan sebelum evaluasi.',
        ))->toThrow(RfcClosureException::class);

        expect(fn () => app(RfcClosureService::class)->close(
            $rfc->fresh(),
            $superAdmin,
            'Catatan penutupan sebelum evaluasi.',
        ))->toThrow(RfcClosureException::class);

        $fresh = $rfc->fresh();

        expect($fresh->status)->toBe($status->value)
            ->and($fresh->closed_at)->toBeNull()
            ->and($fresh->closed_by_id)->toBeNull()
            ->and($fresh->closure_notes)->toBeNull();
    }
});

test('penutupan setelah evaluasi menyimpan data dan activity outcome successful', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    implementationRecordRow($rfc, $cm);
    $evaluationNotes = evaluationNotes('implementasi sukses');
    $closureNotes = 'RFC ditutup setelah seluruh bukti dan evaluasi diverifikasi.';

    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, $evaluationNotes);

    $this->actingAs($cm);

    Livewire::test(CloseRfc::class, ['rfc' => $rfc->fresh()])
        ->assertSee('Ringkasan Hasil Implementasi')
        ->assertSee($evaluationNotes)
        ->set('closure_notes', $closureNotes)
        ->call('closeTicket')
        ->assertHasErrors(['confirmed'])
        ->set('confirmed', true)
        ->call('closeTicket')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.show', $rfc->id));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::CLOSED->value)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->closed_by_id)->toBe($cm->id)
        ->and($fresh->closure_notes)->toBe($closureNotes);

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'closure_recorded');

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($cm->id)
        ->and(data_get($activity->properties, 'old_status'))->toBe(RfcStatus::IMPLEMENTED->value)
        ->and(data_get($activity->properties, 'new_status'))->toBe(RfcStatus::CLOSED->value)
        ->and(data_get($activity->properties, 'outcome'))->toBe('successful')
        ->and(data_get($activity->properties, 'implementation_result'))->toBe(ImplementationResult::SUCCESS->value)
        ->and(data_get($activity->properties, 'closure_notes'))->toBe($closureNotes)
        ->and(data_get($activity->properties, 'evaluation_notes'))->toBe($evaluationNotes);

    $staffHistory = app(RfcHistoryService::class)->build($fresh, true);
    $staffEvaluationEvent = collect($staffHistory['events'])->firstWhere('key', 'evaluation_recorded');
    $staffClosureEvent = collect($staffHistory['events'])->firstWhere('key', RfcStatus::CLOSED->value);

    expect($staffEvaluationEvent)->not->toBeNull()
        ->and($staffEvaluationEvent['label'])->toBe('Evaluasi dicatat')
        ->and($staffEvaluationEvent['note'])->toContain($evaluationNotes)
        ->and($staffClosureEvent)->not->toBeNull()
        ->and($staffClosureEvent['note'])->toContain('Outcome: Berhasil')
        ->and($staffClosureEvent['note'])->toContain('Catatan penutupan: '.$closureNotes)
        ->and($staffClosureEvent['note'])->toContain('Ringkasan evaluasi: '.$evaluationNotes);

    $ownerHistory = app(RfcHistoryService::class)->build($fresh, false);
    $ownerClosureEvent = collect($ownerHistory['events'])->firstWhere('key', RfcStatus::CLOSED->value);

    expect(collect($ownerHistory['events'])->firstWhere('key', 'evaluation_recorded'))->toBeNull()
        ->and($ownerClosureEvent)->not->toBeNull()
        ->and($ownerClosureEvent['note'])->toBeNull()
        ->and(json_encode($ownerHistory))->not->toContain($closureNotes)
        ->and(json_encode($ownerHistory))->not->toContain($evaluationNotes);
});

test('RFC failed yang ditutup mencatat outcome unsuccessful', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::FAILED);

    implementationRecordRow($rfc, $cm, [
        'implementation_result' => ImplementationResult::FAILED->value,
        'failure_notes' => 'Perubahan gagal dan rollback telah diverifikasi.',
        'follow_up_action' => FollowUpAction::NONE->value,
    ]);

    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, evaluationNotes('kegagalan'));
    app(RfcClosureService::class)->close(
        $rfc->fresh(),
        $cm,
        'RFC ditutup setelah kegagalan dan rollback dievaluasi.',
    );

    $activity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'closure_recorded');

    expect($rfc->fresh()->status)->toBe(RfcStatus::CLOSED->value)
        ->and($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'outcome'))->toBe('unsuccessful')
        ->and(data_get($activity->properties, 'implementation_result'))->toBe(ImplementationResult::FAILED->value);
});

test('Pemohon dan Admin mendapat 403 di halaman evaluasi dan penutupan serta tidak melihat tombol', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $admin = implementationUser('Admin');

    $pending = implementationRfc(RfcStatus::IMPLEMENTED, $requester);
    implementationRecordRow($pending, $cm);

    $evaluated = implementationRfc(RfcStatus::IMPLEMENTED, $requester);
    implementationRecordRow($evaluated, $cm);
    $evaluationNotes = evaluationNotes('visibilitas');
    app(RfcEvaluationService::class)->record($evaluated->fresh(), $cm, $evaluationNotes);

    foreach ([$requester, $admin] as $user) {
        $this->actingAs($user)
            ->get(route('change-requests.evaluate', $pending))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('change-requests.close', $evaluated))
            ->assertForbidden();
    }

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $pending->fresh()])
        ->assertOk()
        ->assertDontSee('Catat Evaluasi')
        ->assertDontSee('Tutup RFC');

    $closureNotes = 'Catatan penutupan internal yang hanya untuk staf.';
    $closed = app(RfcClosureService::class)->close($evaluated->fresh(), $cm, $closureNotes);

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $closed])
        ->assertOk()
        ->assertDontSee('Catat Evaluasi')
        ->assertDontSee('Tutup RFC')
        ->assertDontSee('Evaluasi dan Penutupan')
        ->assertDontSee($evaluationNotes)
        ->assertDontSee($closureNotes);

    $this->actingAs($admin);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $closed])
        ->assertOk()
        ->assertSee('Evaluasi dan Penutupan')
        ->assertSee($evaluationNotes)
        ->assertSee($closureNotes)
        ->assertDontSee('Catat Evaluasi')
        ->assertDontSee('Tutup RFC');
});

test('penutupan kedua dan evaluasi ulang RFC closed ditolak', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    implementationRecordRow($rfc, $cm);
    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, evaluationNotes('sebelum penutupan'));

    $firstNotes = 'Catatan penutupan pertama yang sah.';
    $closed = app(RfcClosureService::class)->close($rfc->fresh(), $cm, $firstNotes);

    expect(fn () => app(RfcClosureService::class)->close(
        $closed,
        $cm,
        'Catatan penutupan kedua yang seharusnya ditolak.',
    ))->toThrow(RfcClosureException::class);

    expect(fn () => app(RfcEvaluationService::class)->record(
        $closed,
        $cm,
        evaluationNotes('ulang'),
    ))->toThrow(RfcEvaluationException::class);

    $closureActivityCount = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->filter(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'closure_recorded')
        ->count();

    expect($closed->fresh()->status)->toBe(RfcStatus::CLOSED->value)
        ->and($closed->fresh()->closure_notes)->toBe($firstNotes)
        ->and($closureActivityCount)->toBe(1);
});

test('kegagalan di tengah transaksi penutupan tidak meninggalkan perubahan setengah jalan', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    implementationRecordRow($rfc, $cm);
    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, evaluationNotes('sebelum kegagalan'));

    ChangeRequest::updated(function (ChangeRequest $updated): void {
        if ($updated->status === RfcStatus::CLOSED->value) {
            throw new RuntimeException('Simulasi kegagalan penutupan.');
        }
    });

    expect(fn () => app(RfcClosureService::class)->close(
        $rfc->fresh(),
        $cm,
        'Catatan penutupan yang gagal di tengah transaksi.',
    ))->toThrow(RuntimeException::class);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::IMPLEMENTED->value)
        ->and($fresh->closed_at)->toBeNull()
        ->and($fresh->closed_by_id)->toBeNull()
        ->and($fresh->closure_notes)->toBeNull()
        ->and($fresh->implementation->evaluated_at)->not->toBeNull();

    $closureActivity = Activity::query()
        ->where('subject_type', $rfc->getMorphClass())
        ->where('subject_id', $rfc->id)
        ->get()
        ->first(fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'closure_recorded');

    expect($closureActivity)->toBeNull();
});

test('RFC lama berstatus closed tanpa data implementasi tetap tampil tanpa error', function () {
    $cm = implementationUser('Change Manager');
    $requester = implementationUser('Pemohon');
    $rfc = implementationRfc(RfcStatus::CLOSED, $requester);

    $this->actingAs($cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Hasil Implementasi')
        ->assertDontSee('Evaluasi dan Penutupan');

    $this->actingAs($requester);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc])
        ->assertOk()
        ->assertDontSee('Hasil Implementasi')
        ->assertDontSee('Evaluasi dan Penutupan');
});

test('tombol evaluasi dan penutupan mengikuti status serta prasyarat evaluasi', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::IMPLEMENTED);

    implementationRecordRow($rfc, $cm);

    $this->actingAs($cm);

    Livewire::test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Catat Evaluasi')
        ->assertSee('Tutup RFC aktif setelah evaluasi dicatat')
        ->assertDontSee(route('change-requests.close', $rfc));

    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, evaluationNotes('tombol'));

    $evaluated = $rfc->fresh();

    Livewire::test(ShowChangeRequest::class, ['rfc' => $evaluated])
        ->assertOk()
        ->assertSee('Tutup RFC')
        ->assertSee(route('change-requests.close', $evaluated))
        ->assertDontSee('Catat Evaluasi')
        ->assertDontSee('Tutup RFC aktif setelah evaluasi dicatat');
});

test('change manager dapat menutup rfc berstatus failed setelah evaluasi', function () {
    $cm = implementationUser('Change Manager');
    $rfc = implementationRfc(RfcStatus::FAILED);

    implementationRecordRow($rfc, $cm, [
        'implementation_result' => ImplementationResult::FAILED->value,
        'failure_notes' => 'Perubahan gagal dan layanan dipulihkan.',
        'follow_up_action' => FollowUpAction::NONE->value,
    ]);

    app(RfcEvaluationService::class)->record($rfc->fresh(), $cm, evaluationNotes('gagal'));

    $this->actingAs($cm);

    Livewire::test(CloseRfc::class, ['rfc' => $rfc->fresh()])
        ->set('closure_notes', 'Tiket ditutup setelah rollback diverifikasi.')
        ->set('confirmed', true)
        ->call('closeTicket')
        ->assertHasNoErrors();

    expect($rfc->fresh()->status)->toBe(RfcStatus::CLOSED->value);
});
