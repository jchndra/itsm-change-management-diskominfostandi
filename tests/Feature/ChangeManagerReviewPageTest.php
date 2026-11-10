<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Livewire\ChangeRequests\ListChangeRequests;
use App\Livewire\ChangeRequests\ReviewByChangeManager;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\RiskAssessment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function cmReviewPageUser(array $roles = [], array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $attributes));

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmReviewPageMember(User $user, array $attributes = []): ReleaseTeamMember
{
    return ReleaseTeamMember::factory()->create(array_merge([
        'user_id' => $user->id,
        'name' => $user->name,
        'is_active' => true,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmReviewPageRfc(User $requester, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::TICKET_CREATED->value,
    ], $attributes));
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->fillEvaluation = function (Testable $component): Testable {
        return $component
            ->set('risk_level', 'Sedang')
            ->set('impact_level', 'Sedang')
            ->set('urgency_level', 'Normal')
            ->set('classification', 'Normal Change')
            ->set('analysis_notes', 'Catatan analisis risiko lengkap untuk pengujian halaman tinjauan.');
    };
});

it('membuka halaman tinjauan untuk change manager', function () {
    $manager = cmReviewPageUser(['Change Manager']);
    $rfc = cmReviewPageRfc(cmReviewPageUser(['Pemohon']));

    $this->actingAs($manager)
        ->get(route('change-requests.cm-review', $rfc))
        ->assertOk()
        ->assertSee('Tinjauan Change Manager')
        ->assertSee('Evaluasi Risiko')
        ->assertSee('Keputusan')
        ->assertSee('Disposisi Tim Rilis')
        ->assertSee('Simpan &amp; Selesaikan', false);
});

it('menolak pengguna selain change manager', function () {
    $rfc = cmReviewPageRfc(cmReviewPageUser(['Pemohon']));

    foreach ([
        cmReviewPageUser(['Admin']),
        cmReviewPageUser(['Pemohon']),
        cmReviewPageUser(['Tim Rilis']),
    ] as $user) {
        $this->actingAs($user)
            ->get(route('change-requests.cm-review', $rfc))
            ->assertForbidden();
    }
});

it('menolak change manager yang mengajukan RFC sendiri', function () {
    $manager = cmReviewPageUser(['Change Manager']);
    $rfc = cmReviewPageRfc($manager);

    $this->actingAs($manager)
        ->get(route('change-requests.cm-review', $rfc))
        ->assertForbidden();
});

it('menyimpan persetujuan sekaligus disposisi melalui halaman tinjauan', function () {
    $manager = cmReviewPageUser(['Change Manager']);
    $requester = cmReviewPageUser(['Pemohon']);
    $member = cmReviewPageMember(cmReviewPageUser(['Tim Rilis'], ['name' => 'Anggota Review']));
    $rfc = cmReviewPageRfc($requester);

    $this->actingAs($manager);

    $component = ($this->fillEvaluation)(Livewire::test(ReviewByChangeManager::class, ['rfc' => $rfc]));

    $component
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui berdasarkan evaluasi teknis lengkap.')
        ->set('selectedMemberId', $member->id)
        ->set('instruction', 'Laksanakan perubahan pada jendela pemeliharaan malam ini.')
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.index'));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::APPROVED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and($fresh->decisions()->count())->toBe(1)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(1);
});

it('menyimpan keputusan tanpa disposisi melalui halaman tinjauan', function (DecisionStatus $decisionStatus, RfcStatus $targetStatus) {
    $manager = cmReviewPageUser(['Change Manager']);
    $rfc = cmReviewPageRfc(cmReviewPageUser(['Pemohon']));

    $this->actingAs($manager);

    $component = ($this->fillEvaluation)(Livewire::test(ReviewByChangeManager::class, ['rfc' => $rfc]));

    $component
        ->set('decisionStatus', $decisionStatus->value)
        ->set('notes', 'Catatan keputusan '.$decisionStatus->value.'.')
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('change-requests.index'));

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe($targetStatus->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(1)
        ->and($fresh->decisions()->firstOrFail()->decision_status)->toBe($decisionStatus->value)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(0);
})->with([
    'rejected' => [DecisionStatus::REJECTED, RfcStatus::REJECTED],
    'revision required' => [DecisionStatus::REVISION_REQUIRED, RfcStatus::NEEDS_REVISION],
]);

it('menampilkan error validasi ketika setujui tanpa penerima disposisi', function () {
    $manager = cmReviewPageUser(['Change Manager']);
    $rfc = cmReviewPageRfc(cmReviewPageUser(['Pemohon']));

    $this->actingAs($manager);

    $component = ($this->fillEvaluation)(Livewire::test(ReviewByChangeManager::class, ['rfc' => $rfc]));

    $component
        ->set('decisionStatus', DecisionStatus::APPROVED->value)
        ->set('notes', 'Disetujui tanpa memilih penerima disposisi.')
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors(['selectedMemberId', 'instruction']);

    $fresh = $rfc->fresh();

    expect($fresh->status)->toBe(RfcStatus::TICKET_CREATED->value)
        ->and(RiskAssessment::query()->where('change_request_id', $rfc->id)->count())->toBe(0)
        ->and($fresh->decisions()->count())->toBe(0);
});

it('menautkan daftar RFC ke halaman tinjauan untuk status ticket created', function () {
    $manager = cmReviewPageUser(['Change Manager']);
    $rfc = cmReviewPageRfc(cmReviewPageUser(['Pemohon']), ['title' => 'RFC Tinjauan Tautan']);

    $this->actingAs($manager);

    Livewire::test(ListChangeRequests::class)
        ->assertOk()
        ->assertSee($rfc->title)
        ->assertSee(route('change-requests.cm-review', $rfc))
        ->assertSee('Tinjau')
        ->assertDontSee(route('change-requests.assess-risk', $rfc))
        ->assertDontSee(route('change-requests.record-decision', $rfc));
});
