<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Enums\RiskEvent;
use App\Livewire\ChangeRequests\AssessRisk;
use App\Livewire\ChangeRequests\CreateChangeRequest;
use App\Livewire\ChangeRequests\EditChangeRequest;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\RecordDecision;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestAttachment;
use App\Models\Opd;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Notifications\ChangeManagerReviewNotification;
use App\Notifications\RfcStatusChangedNotification;
use App\Services\RfcHistoryService;
use App\StateMachines\RfcStatusTransition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->owner = User::factory()->create([
        'name' => 'Pemohon Alur Baru',
        'opd_id' => Opd::factory()->create(['code' => 'PD01'])->id,
    ]);
    $this->owner->assignRole('Pemohon');

    $this->cm = User::factory()->create(['name' => 'Change Manager Alur Baru']);
    $this->cm->assignRole('Change Manager');

    $this->secondCm = User::factory()->create(['name' => 'Change Manager Alur Baru 2']);
    $this->secondCm->assignRole('Change Manager');

    $this->admin = User::factory()->create(['name' => 'Admin Alur Baru']);
    $this->admin->assignRole('Admin');

    $this->application = Application::factory()->create();

    Storage::fake('local');
});

function newFlowFormFields(Application $application, string $title = 'Pengajuan Alur Baru'): array
{
    return [
        'title' => $title,
        'application_id' => (string) $application->id,
        'change_type' => 'normal',
        'description' => 'Deskripsi perubahan untuk pengujian alur baru.',
        'legal_basis' => 'Dasar hukum perubahan untuk pengujian alur baru.',
        'risk_event' => RiskEvent::CYBER_INCIDENT->value,
        'reason_for_change' => 'Alasan perubahan untuk pengujian alur baru.',
        'change_risk' => 'Risiko perubahan untuk pengujian alur baru.',
        'impact_description' => 'Dampak perubahan untuk pengujian alur baru.',
        'target_implementation_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
    ];
}

function newFlowRfc(User $owner, RfcStatus $status, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create([
        'submitted_by_id' => $owner->id,
        'created_by_id' => $owner->id,
        'status' => $status->value,
        'risk_event' => RiskEvent::CYBER_INCIDENT,
        ...$attributes,
    ]);
}

function newFlowRisk(ChangeRequest $rfc, User $assessor): void
{
    RiskAssessment::create([
        'change_request_id' => $rfc->id,
        'assessed_by_id' => $assessor->id,
        'system_criticality_score' => 3,
        'impact_scope_score' => 3,
        'rollback_readiness_score' => 2,
        'total_score' => 8,
        'risk_level' => 'Sedang',
        'impact_level' => 'Sedang',
        'urgency_level' => 'Normal',
        'classification' => 'Normal Change',
        'analysis_notes' => 'Analisis risiko untuk pengujian alur baru.',
        'mitigation_plan' => 'Rencana mitigasi untuk pengujian.',
        'rollback_plan' => 'Rencana rollback untuk pengujian.',
    ]);
}

test('pengajuan baru langsung masuk ke Change Manager, memakai satu nomor RFC, dan tanpa field tiket', function () {
    Notification::fake();

    Livewire::actingAs($this->owner)
        ->test(CreateChangeRequest::class)
        ->set(newFlowFormFields($this->application, 'Pengajuan Langsung Alur Baru'))
        ->set('attachments', [UploadedFile::fake()->create('surat-persetujuan.pdf', 100, 'application/pdf')])
        ->call('save', RfcStatus::TICKET_CREATED->value)
        ->assertHasNoErrors();

    $rfc = ChangeRequest::latest('id')->firstOrFail();

    expect($rfc->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->rfc_number)->toBe('RFC-PD01-1')
        ->and($rfc->ticket_number)->toBeNull()
        ->and($rfc->priority)->toBeNull()
        ->and($rfc->category)->toBeNull();

    $expectedMessage = "{$rfc->displayIdentifier()} menunggu pemeriksaan Anda";

    foreach ([$this->cm, $this->secondCm] as $changeManager) {
        Notification::assertSentTo(
            $changeManager,
            ChangeManagerReviewNotification::class,
            function (ChangeManagerReviewNotification $notification) use ($expectedMessage): bool {
                $payload = $notification->toArray($this->cm);

                return $payload['message'] === $expectedMessage
                    && ! array_key_exists('status', $payload)
                    && ! array_key_exists('outcome', $payload);
            }
        );
    }

    Notification::assertSentToTimes($this->owner, RfcStatusChangedNotification::class, 1);
    Notification::assertNotSentTo($this->admin, ChangeManagerReviewNotification::class);
});

test('pengiriman revisi langsung kembali ke Change Manager dan memberi tahu seluruh Change Manager', function () {
    Notification::fake();

    $rfc = newFlowRfc($this->owner, RfcStatus::NEEDS_REVISION, [
        'rfc_number' => 'RFC-'.date('Y').'-0100',
        'rejection_note' => 'Lengkapi lampiran.',
    ]);

    $rfc->attachments()->create([
        'uploaded_by_id' => $this->owner->id,
        'document_type' => ChangeRequestAttachment::TYPE_APPROVAL_LETTER,
        'original_name' => 'surat-persetujuan.pdf',
        'path' => 'rfc-attachments/'.$rfc->id.'/surat-persetujuan.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    Livewire::actingAs($this->owner)
        ->test(EditChangeRequest::class, ['rfc' => $rfc])
        ->call('submit')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();
    $expectedMessage = "{$fresh->displayIdentifier()} menunggu pemeriksaan Anda";

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($fresh->rejection_note)->toBeNull();

    foreach ([$this->cm, $this->secondCm] as $changeManager) {
        Notification::assertSentTo(
            $changeManager,
            ChangeManagerReviewNotification::class,
            function (ChangeManagerReviewNotification $notification) use ($changeManager, $expectedMessage): bool {
                return $notification->toArray($changeManager)['message'] === $expectedMessage;
            }
        );
    }

    Notification::assertSentToTimes($this->owner, RfcStatusChangedNotification::class, 1);
});

test('persetujuan Change Manager wajib mengonfirmasi kelengkapan dokumen', function () {
    $rfc = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED);
    newFlowRisk($rfc, $this->cm);

    $component = Livewire::actingAs($this->cm)
        ->test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Dokumen lengkap dan risiko dapat diterima.')
        ->call('saveDecision')
        ->assertHasErrors(['completenessConfirmed']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($rfc->fresh()->decisions()->count())->toBe(0);

    $component
        ->set('completenessConfirmed', true)
        ->call('saveDecision')
        ->assertHasNoErrors();

    $approvalNote = 'Dokumen lengkap dan risiko dapat diterima.';
    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::APPROVED->value)
        ->and($fresh->decisions()->firstOrFail()->decision_status)
        ->toBe(DecisionStatus::APPROVED->value);

    $ownerEvent = collect(app(RfcHistoryService::class)->build($fresh, false)['events'])
        ->firstWhere('key', RfcStatus::APPROVED->value);
    $staffEvent = collect(app(RfcHistoryService::class)->build($fresh, true)['events'])
        ->firstWhere('key', RfcStatus::APPROVED->value);

    expect($ownerEvent)->not->toBeNull()
        ->and($ownerEvent['note'])->toBeNull()
        ->and($ownerEvent['actor_name'])->toBeNull()
        ->and($staffEvent['note'])->toBe($approvalNote)
        ->and($staffEvent['actor_name'])->toBe($this->cm->name);
});

test('revisi Change Manager wajib beralasan, menyetel needs_revision, dan catatannya terlihat Pemohon', function () {
    $rfc = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED);
    newFlowRisk($rfc, $this->cm);

    $this->actingAs($this->cm)
        ->get(route('change-requests.record-decision', $rfc))
        ->assertOk()
        ->assertSee('Revisi')
        ->assertDontSee('Dikembalikan ke Admin');

    $component = Livewire::actingAs($this->cm)
        ->test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::REVISION_REQUIRED->value)
        ->set('notes', '')
        ->call('saveDecision')
        ->assertHasErrors(['notes']);

    expect($rfc->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value);

    $note = 'Lengkapi surat persetujuan dan perjelas rencana rollback.';
    $component
        ->set('notes', $note)
        ->call('saveDecision')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::NEEDS_REVISION->value)
        ->and($fresh->rejection_note)->toBe($note)
        ->and($fresh->decisions()->firstOrFail()->decision_status)
        ->toBe(DecisionStatus::REVISION_REQUIRED->value);

    $ownerHistory = app(RfcHistoryService::class)->build($fresh, false);
    $revisionEvent = collect($ownerHistory['events'])->firstWhere('key', RfcStatus::NEEDS_REVISION->value);

    expect($revisionEvent)->not->toBeNull()
        ->and($revisionEvent['note'])->toBe($note)
        ->and($this->owner->notifications()->count())->toBe(1)
        ->and(data_get($this->owner->notifications()->first()->data, 'message'))
        ->toBe("RFC {$fresh->rfc_number} perlu direvisi: {$note}");
});

test('penolakan Change Manager wajib alasan dan tidak membuat persetujuan', function () {
    $rfc = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED);
    newFlowRisk($rfc, $this->cm);

    $component = Livewire::actingAs($this->cm)
        ->test(RecordDecision::class, ['rfc' => $rfc])
        ->set('decisionStatus', DecisionStatus::REJECTED->value)
        ->set('notes', '')
        ->call('saveDecision')
        ->assertHasErrors(['notes']);

    $reason = 'Ruang lingkup perubahan tidak jelas dan tidak dapat diverifikasi.';
    $component
        ->set('notes', $reason)
        ->call('saveDecision')
        ->assertHasNoErrors();

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::REJECTED->value)
        ->and($fresh->decisions()->firstOrFail()->decision_status)->toBe(DecisionStatus::REJECTED->value)
        ->and($fresh->decisions()->firstOrFail()->reason)->toBe($reason);

    Livewire::actingAs($this->owner)
        ->test(ShowChangeRequest::class, ['rfc' => $fresh])
        ->assertOk()
        ->assertSee($reason);
});

test('Change Manager tidak dapat memutuskan RFC yang dia ajukan sendiri', function () {
    $rfc = newFlowRfc($this->cm, RfcStatus::TICKET_CREATED);
    newFlowRisk($rfc, $this->cm);

    $this->actingAs($this->cm)
        ->get(route('change-requests.record-decision', $rfc))
        ->assertForbidden();
});

test('riwayat dan langkah berikutnya alur baru tidak memuat langkah Admin', function () {
    $rfc = newFlowRfc($this->owner, RfcStatus::DRAFT, ['rfc_number' => null]);

    RfcStatusTransition::transitionTo($rfc, RfcStatus::TICKET_CREATED, $this->owner);

    $ownerHistory = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $ownerEvents = collect($ownerHistory['events']);

    expect($ownerEvents->pluck('label')->all())
        ->toContain('RFC diajukan dan diteruskan ke Change Manager')
        ->and($ownerEvents->pluck('label')->implode(' | '))->not->toContain('Admin')
        ->and($ownerEvents->every(fn (array $event): bool => $event['actor_name'] === null))->toBeTrue()
        ->and(collect($ownerHistory['next_steps'])->pluck('label')->all())
        ->toBe(['Ditinjau Change Manager', 'Dikerjakan Tim Rilis', 'Evaluasi', 'Ditutup']);

    $staffHistory = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $submissionEvent = collect($staffHistory['events'])->firstWhere('key', RfcStatus::TICKET_CREATED->value);

    expect($submissionEvent)->not->toBeNull()
        ->and($submissionEvent['actor_name'])->toBe($this->owner->name)
        ->and($submissionEvent['actor_role'])->toBe('Pemohon');
});

test('perintah forward legacy mendukung dry-run, aktor sistem, dan idempotensi', function () {
    $submitted = newFlowRfc($this->owner, RfcStatus::SUBMITTED, [
        'rfc_number' => 'RFC-'.date('Y').'-0801',
    ]);
    $returned = newFlowRfc($this->owner, RfcStatus::RETURNED_TO_ADMIN, [
        'rfc_number' => 'RFC-'.date('Y').'-0802',
    ]);
    $alreadyForwarded = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED, [
        'rfc_number' => 'RFC-'.date('Y').'-0803',
    ]);

    $forwardedActivities = fn () => Activity::all()->filter(
        fn (Activity $activity): bool => data_get($activity->properties, 'event') === 'legacy_forwarded'
    );

    $this->artisan('rfc:forward-legacy', ['--dry-run' => true])->assertExitCode(0);

    expect($submitted->fresh()->status)->toBe(RfcStatus::SUBMITTED->value)
        ->and($returned->fresh()->status)->toBe(RfcStatus::RETURNED_TO_ADMIN->value)
        ->and($forwardedActivities())->toHaveCount(0);

    $this->artisan('rfc:forward-legacy')->assertExitCode(0);

    expect($submitted->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($returned->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($alreadyForwarded->fresh()->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and($forwardedActivities())->toHaveCount(2);

    $activity = $forwardedActivities()->firstWhere('subject_id', $submitted->id);

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Diteruskan ke Change Manager (penyesuaian alur)')
        ->and($activity->causer_id)->toBeNull();

    $this->artisan('rfc:forward-legacy')->assertExitCode(0);

    expect($forwardedActivities())->toHaveCount(2);
});

test('tiket kosong tidak dirender sebagai strip pada daftar dan halaman review', function () {
    $newRfc = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED);

    Livewire::actingAs($this->admin)
        ->test(ListChangeRequests::class)
        ->assertOk()
        ->assertDontSee('No. Tiket');

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $newRfc])
        ->assertOk()
        ->assertDontSee('Informasi Tiket dari Admin')
        ->assertDontSee('Nomor Tiket');

    $legacy = newFlowRfc($this->owner, RfcStatus::TICKET_CREATED, [
        'ticket_number' => 'CHG-'.date('Y').'-0099',
        'priority' => 'high',
        'category' => 'application',
        'admin_note' => 'Catatan tiket lama untuk Change Manager.',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ListChangeRequests::class)
        ->assertOk()
        ->assertSee($legacy->title)
        ->assertDontSee('No. Tiket')
        ->assertDontSee('CHG-'.date('Y').'-0099');

    Livewire::actingAs($this->cm)
        ->test(AssessRisk::class, ['rfc' => $legacy])
        ->assertOk()
        ->assertSee('Informasi Tiket dari Admin')
        ->assertSee('CHG-'.date('Y').'-0099')
        ->assertSee('Tinggi')
        ->assertSee('Aplikasi')
        ->assertSee('Catatan tiket lama untuk Change Manager.');
});
