<?php

use App\Enums\DecisionStatus;
use App\Enums\RfcStatus;
use App\Exceptions\RfcDispositionException;
use App\Livewire\ChangeRequests\ShowChangeRequest;
use App\Livewire\Disposition\Assign;
use App\Livewire\Disposition\Index;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Decision;
use App\Models\Opd;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Notifications\RfcDispositionNotification;
use App\Services\RfcDispositionService;
use App\Services\RfcHistoryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @param  array<int, string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function dispositionUser(array $roles = [], array $attributes = []): User
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
function dispositionMember(User $user, array $attributes = []): ReleaseTeamMember
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
function dispositionRfc(User $requester, array $attributes = []): ChangeRequest
{
    return ChangeRequest::factory()->create(array_merge([
        'submitted_by_id' => $requester->id,
        'created_by_id' => $requester->id,
        'status' => RfcStatus::APPROVED->value,
    ], $attributes));
}

function dispositionApprovedDecision(ChangeRequest $rfc, User $recorder, ?Carbon $date = null): Decision
{
    $authority = Authority::factory()->create();

    return Decision::create([
        'change_request_id' => $rfc->id,
        'authority_id' => $authority->id,
        'recorded_by_id' => $recorder->id,
        'snapshot_authority_name' => $authority->name,
        'snapshot_authority_position' => $authority->position,
        'decision_status' => DecisionStatus::APPROVED->value,
        'decision_date' => $date ?? now(),
        'reason' => 'Keputusan persetujuan untuk pengujian disposisi.',
    ]);
}

function dispositionSidebarHtml(string $html): string
{
    $start = strpos($html, '<aside');
    $end = strpos($html, '</aside>');

    return $start === false || $end === false
        ? ''
        : substr($html, $start, $end - $start);
}

function dispositionSidebarActive(string $html, string $url): bool
{
    return (bool) preg_match(
        '/<a href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/',
        $html
    );
}

it('menolak Pemohon, Admin, dan Tim Rilis di halaman disposisi serta mengizinkan Change Manager', function () {
    $requester = dispositionUser(['Pemohon']);
    $admin = dispositionUser(['Admin']);
    $releaseTeam = dispositionUser(['Tim Rilis']);
    $manager = dispositionUser(['Change Manager']);

    $rfc = dispositionRfc($requester);

    foreach ([$requester, $admin, $releaseTeam] as $user) {
        $this->actingAs($user);

        $this->get(route('disposition.index'))->assertForbidden();
        $this->get(route('disposition.assign', $rfc))->assertForbidden();

        Livewire::actingAs($user)
            ->test(Assign::class, ['rfc' => $rfc])
            ->assertForbidden();
    }

    $this->actingAs($manager);

    $this->get(route('disposition.index'))->assertOk();
    $this->get(route('disposition.assign', $rfc))->assertOk();

    Livewire::actingAs($manager)
        ->test(Assign::class, ['rfc' => $rfc])
        ->assertOk();
});

it('mengalihkan tamu ke login dari halaman disposisi', function () {
    $requester = dispositionUser(['Pemohon']);
    $rfc = dispositionRfc($requester);

    $this->get(route('disposition.index'))->assertRedirect(route('login'));
    $this->get(route('disposition.assign', $rfc))->assertRedirect(route('login'));
});

it('menolak request Livewire lanjutan ketika pengguna kehilangan hak', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $admin = dispositionUser(['Admin']);

    $rfc = dispositionRfc($requester);

    $this->actingAs($manager);
    $index = Livewire::test(Index::class)->assertOk();
    $assign = Livewire::test(Assign::class, ['rfc' => $rfc])->assertOk();

    $this->actingAs($requester);
    $index->call('setTab', 'assigned')->assertForbidden();

    $this->actingAs($admin);
    $assign->refresh()->assertForbidden();
});

it('hanya menampilkan RFC approved tanpa tugas pada tab Menunggu Disposisi', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $opd = Opd::factory()->create(['name' => 'Dinas Pengujian Disposisi']);
    $requester->update(['name' => 'Pemohon Disposisi', 'opd_id' => $opd->id]);

    $releaseUser = dispositionUser(['Tim Rilis'], ['name' => 'Anggota Penerima']);
    $member = dispositionMember($releaseUser);

    $waiting = dispositionRfc($requester, [
        'title' => 'RFC Menunggu Disposisi Unik',
        'change_type' => 'emergency',
    ]);
    dispositionApprovedDecision($waiting, $manager, Carbon::parse('2026-10-20 09:00:00'));

    $alreadyAssigned = dispositionRfc($requester, ['title' => 'RFC Sudah Disposisi Unik']);
    ReleaseTask::factory()->forRfc($alreadyAssigned)->assignedTo($member)->create([
        'assigned_by_id' => $manager->id,
    ]);

    $draft = dispositionRfc($requester, [
        'title' => 'RFC Draft Unik',
        'status' => RfcStatus::DRAFT->value,
    ]);
    $submitted = dispositionRfc($requester, [
        'title' => 'RFC Diajukan Unik',
        'status' => RfcStatus::SUBMITTED->value,
    ]);
    $deleted = dispositionRfc($requester, ['title' => 'RFC Terhapus Unik']);
    $deleted->delete();

    $this->actingAs($manager);

    $component = Livewire::test(Index::class)
        ->assertOk()
        ->assertSee($waiting->title)
        ->assertSee('Dinas Pengujian Disposisi')
        ->assertSee('Darurat (Emergency)')
        ->assertSee('20 Okt 2026')
        ->assertDontSee($alreadyAssigned->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($submitted->title)
        ->assertDontSee($deleted->title)
        ->assertViewHas('waitingCount', 1)
        ->assertViewHas('assignedCount', 1);

    $component->assertViewHas('waitingRfcs', function ($paginator) use ($waiting): bool {
        return collect($paginator->items())->pluck('id')->all() === [$waiting->id];
    });

    $component->call('setTab', 'assigned')
        ->assertSee($alreadyAssigned->title)
        ->assertSee('Anggota Penerima')
        ->assertDontSee($waiting->title);
});

it('menyimpan disposisi satu baris per penerima tanpa mengubah status RFC', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $otherRequester = dispositionUser(['Pemohon']);

    $firstUser = dispositionUser(['Tim Rilis'], ['name' => 'Penerima Pertama']);
    $secondUser = dispositionUser(['Tim Rilis'], ['name' => 'Penerima Kedua']);
    $firstMember = dispositionMember($firstUser);
    $secondMember = dispositionMember($secondUser);

    $rfc = dispositionRfc($requester, ['title' => 'RFC Disposisi Sukses']);
    $instruction = 'Laksanakan perubahan pada jendela pemeliharaan malam ini.';

    $this->actingAs($manager);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('selectedMemberIds', [$firstMember->id, $secondMember->id])
        ->set('instruction', $instruction)
        ->set('confirmed', true)
        ->call('assign')
        ->assertHasNoErrors()
        ->assertRedirect(route('disposition.index', ['tab' => 'assigned']))
        ->assertSessionHas('success');

    expect($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value)
        ->and(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(2);

    foreach ([$firstMember, $secondMember] as $member) {
        $this->assertDatabaseHas('release_tasks', [
            'change_request_id' => $rfc->id,
            'release_team_member_id' => $member->id,
            'assigned_by_id' => $manager->id,
            'instruction' => $instruction,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'execution_status' => 'pending',
            'executed_at' => null,
            'execution_notes' => null,
        ]);
    }

    $activity = Activity::query()
        ->where('subject_type', ChangeRequest::class)
        ->where('subject_id', $rfc->id)
        ->where('description', 'Disposisi ke Tim Rilis')
        ->firstOrFail();

    expect(data_get($activity->properties, 'recipient_names'))->toBe(['Penerima Pertama', 'Penerima Kedua'])
        ->and(data_get($activity->properties, 'recipient_count'))->toBe(2)
        ->and(data_get($activity->properties, 'instruction'))->toBe($instruction);

    $notification = $requester->notifications()->firstOrFail();

    expect($notification->type)->toBe(RfcDispositionNotification::class)
        ->and(data_get($notification->data, 'rfc_id'))->toBe($rfc->id)
        ->and(data_get($notification->data, 'message'))->toBe($rfc->displayIdentifier().' diteruskan ke Tim Rilis')
        ->and($otherRequester->notifications()->count())->toBe(0);

    Livewire::test(Index::class)
        ->assertViewHas('waitingCount', 0)
        ->assertViewHas('assignedCount', 1);

    $this->get(route('disposition.index', ['tab' => 'assigned']))
        ->assertOk()
        ->assertSee($rfc->title);
});

it('memvalidasi penerima, instruksi, dan kotak konfirmasi', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $member = dispositionMember(dispositionUser(['Tim Rilis']));
    $rfc = dispositionRfc($requester);
    $validInstruction = 'Instruksi yang cukup panjang untuk disposisi.';

    $this->actingAs($manager);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('instruction', $validInstruction)
        ->set('confirmed', true)
        ->call('assign')
        ->assertHasErrors(['selectedMemberIds' => 'required']);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('selectedMemberIds', [$member->id])
        ->set('instruction', '')
        ->set('confirmed', true)
        ->call('assign')
        ->assertHasErrors(['instruction' => 'required']);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('selectedMemberIds', [$member->id])
        ->set('instruction', 'pendek')
        ->set('confirmed', true)
        ->call('assign')
        ->assertHasErrors(['instruction' => 'min']);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('selectedMemberIds', [$member->id])
        ->set('instruction', $validInstruction)
        ->set('confirmed', false)
        ->call('assign')
        ->assertHasErrors(['confirmed' => 'accepted']);

    $this->assertDatabaseCount('release_tasks', 0);
});

it('hanya menampilkan anggota Tim Rilis yang memenuhi syarat dan menolak penerima tidak layak', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);

    $eligibleUser = dispositionUser(['Tim Rilis'], ['name' => 'Penerima Layak Unik']);
    $eligibleMember = dispositionMember($eligibleUser);

    $inactiveMembershipUser = dispositionUser(['Tim Rilis'], ['name' => 'Anggota Nonaktif Unik']);
    $inactiveMembership = dispositionMember($inactiveMembershipUser, ['is_active' => false]);

    $withoutAccount = ReleaseTeamMember::factory()->create([
        'user_id' => null,
        'name' => 'Tanpa Akun Unik',
        'is_active' => true,
    ]);

    $inactiveAccountUser = dispositionUser(['Tim Rilis'], [
        'name' => 'Akun Nonaktif Unik',
        'is_active' => false,
    ]);
    $inactiveAccount = dispositionMember($inactiveAccountUser);

    $wrongRoleUser = dispositionUser(['Pemohon'], ['name' => 'Bukan Tim Rilis Unik']);
    $wrongRoleMember = dispositionMember($wrongRoleUser);

    $rfc = dispositionRfc($requester);
    $instruction = 'Instruksi disposisi untuk menguji penerima tidak layak.';

    $this->actingAs($manager);

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->assertViewHas('eligibleMembers', function ($members) use ($eligibleMember): bool {
            return $members->pluck('id')->all() === [$eligibleMember->id];
        })
        ->assertDontSee('Anggota Nonaktif Unik')
        ->assertDontSee('Tanpa Akun Unik')
        ->assertDontSee('Akun Nonaktif Unik')
        ->assertDontSee('Bukan Tim Rilis Unik');

    Livewire::test(Assign::class, ['rfc' => $rfc])
        ->set('selectedMemberIds', [
            $inactiveMembership->id,
            $withoutAccount->id,
            $inactiveAccount->id,
            $wrongRoleMember->id,
        ])
        ->set('instruction', $instruction)
        ->set('confirmed', true)
        ->call('assign')
        ->assertHasErrors('disposition');

    expect(fn () => app(RfcDispositionService::class)->assign(
        $rfc,
        $manager,
        [$inactiveMembership->id],
        $instruction,
    ))->toThrow(RfcDispositionException::class);

    $this->assertDatabaseCount('release_tasks', 0);
});

it('menolak disposisi kedua dan RFC yang bukan approved termasuk lewat service', function () {
    $manager = dispositionUser(['Change Manager']);
    $superAdmin = dispositionUser(['Super Admin']);
    $requester = dispositionUser(['Pemohon']);
    $member = dispositionMember(dispositionUser(['Tim Rilis']));
    $instruction = 'Instruksi disposisi pertama untuk pengujian duplikasi.';

    $rfc = dispositionRfc($requester);

    expect($manager->can('assignRelease', $rfc))->toBeTrue()
        ->and($manager->can('viewDisposition'))->toBeTrue();

    app(RfcDispositionService::class)->assign($rfc, $manager, [$member->id], $instruction);

    expect($rfc->fresh()->status)->toBe(RfcStatus::APPROVED->value)
        ->and($manager->can('assignRelease', $rfc->fresh()))->toBeFalse();

    expect(fn () => app(RfcDispositionService::class)->assign(
        $rfc->fresh(),
        $superAdmin,
        [$member->id],
        'Instruksi disposisi kedua yang harus ditolak.',
    ))->toThrow(RfcDispositionException::class);

    $this->actingAs($manager)
        ->get(route('disposition.assign', $rfc))
        ->assertForbidden();

    expect(ReleaseTask::query()->where('change_request_id', $rfc->id)->count())->toBe(1);

    foreach ([RfcStatus::SUBMITTED, RfcStatus::IN_PROGRESS, RfcStatus::IMPLEMENTED] as $status) {
        $otherRfc = dispositionRfc($requester, ['status' => $status->value]);

        expect(fn () => app(RfcDispositionService::class)->assign(
            $otherRfc,
            $manager,
            [$member->id],
            'Instruksi disposisi untuk status yang tidak diizinkan.',
        ))->toThrow(RfcDispositionException::class);
    }

    $this->assertDatabaseCount('release_tasks', 1);
});

it('menggulung seluruh tugas jika transaksi gagal di tengah jalan', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $firstMember = dispositionMember(dispositionUser(['Tim Rilis']));
    $secondMember = dispositionMember(dispositionUser(['Tim Rilis']));
    $rfc = dispositionRfc($requester);

    $createdCount = 0;

    ReleaseTask::created(function (ReleaseTask $task) use (&$createdCount): void {
        $createdCount++;

        if ($createdCount === 2) {
            throw new RuntimeException('Simulasi kegagalan penyimpanan tugas kedua.');
        }
    });

    $thrown = false;

    try {
        app(RfcDispositionService::class)->assign(
            $rfc,
            $manager,
            [$firstMember->id, $secondMember->id],
            'Instruksi disposisi untuk pengujian rollback transaksi.',
        );
    } catch (RuntimeException) {
        $thrown = true;
    } finally {
        ReleaseTask::flushEventListeners();
    }

    expect($thrown)->toBeTrue()
        ->and(ReleaseTask::query()->count())->toBe(0)
        ->and(Activity::query()
            ->where('subject_type', ChangeRequest::class)
            ->where('subject_id', $rfc->id)
            ->count())->toBe(0)
        ->and($requester->notifications()->count())->toBe(0);
});

it('menyembunyikan penerima dan instruksi dari Pemohon tetapi menampilkannya untuk staf', function () {
    $manager = dispositionUser(['Change Manager'], ['name' => 'Change Manager Disposisi']);
    $requester = dispositionUser(['Pemohon']);
    $releaseUser = dispositionUser(['Tim Rilis'], ['name' => 'Nama Penerima Rahasia']);
    $member = dispositionMember($releaseUser);
    $rfc = dispositionRfc($requester, ['title' => 'RFC Visibilitas Disposisi']);
    $instruction = 'Instruksi rahasia internal yang tidak boleh dilihat Pemohon.';

    app(RfcDispositionService::class)->assign($rfc, $manager, [$member->id], $instruction);

    $this->actingAs($requester);

    $response = $this->get(route('change-requests.show', $rfc))
        ->assertOk()
        ->assertSee('Diteruskan ke Tim Rilis')
        ->assertDontSee('Disposisi ke Tim Rilis')
        ->assertDontSee('Nama Penerima Rahasia')
        ->assertDontSee($instruction)
        ->assertDontSee('Diberikan oleh');

    expect($response->getContent())
        ->not->toContain('Nama Penerima Rahasia')
        ->not->toContain($instruction);

    Livewire::actingAs($requester)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Diteruskan ke Tim Rilis')
        ->assertDontSee('Disposisi ke Tim Rilis')
        ->assertDontSee('Nama Penerima Rahasia')
        ->assertDontSee($instruction);

    $requesterHistory = app(RfcHistoryService::class)->build($rfc->fresh(), false);
    $requesterEvent = collect($requesterHistory['events'])->firstWhere('key', 'release_disposition');

    expect($requesterEvent)->not->toBeNull()
        ->and($requesterEvent['label'])->toBe('Diteruskan ke Tim Rilis')
        ->and($requesterEvent['note'])->toBeNull()
        ->and($requesterEvent['actor_name'])->toBeNull()
        ->and(json_encode($requesterHistory['events']))->not->toContain('Nama Penerima Rahasia')
        ->and(json_encode($requesterHistory['events']))->not->toContain($instruction);

    $this->actingAs($manager);

    Livewire::actingAs($manager)
        ->test(ShowChangeRequest::class, ['rfc' => $rfc->fresh()])
        ->assertOk()
        ->assertSee('Disposisi')
        ->assertSee('Nama Penerima Rahasia')
        ->assertSee($instruction)
        ->assertSee('Diberikan oleh '.$manager->name);

    $staffHistory = app(RfcHistoryService::class)->build($rfc->fresh(), true);
    $staffEvent = collect($staffHistory['events'])->firstWhere('key', 'release_disposition');

    expect($staffEvent['label'])->toBe('Disposisi ke Tim Rilis')
        ->and($staffEvent['note'])->toContain('Nama Penerima Rahasia')
        ->and($staffEvent['note'])->toContain($instruction)
        ->and($staffEvent['actor_name'])->toBe($manager->name);
});

it('tetap merender notifikasi disposisi baru dan notifikasi lama tanpa error', function () {
    $manager = dispositionUser(['Change Manager']);
    $requester = dispositionUser(['Pemohon']);
    $member = dispositionMember(dispositionUser(['Tim Rilis']));
    $rfc = dispositionRfc($requester);
    $legacyMessage = 'Pesan notifikasi lama yang harus tetap tampil.';

    app(RfcDispositionService::class)->assign(
        $rfc,
        $manager,
        [$member->id],
        'Instruksi notifikasi disposisi yang valid.',
    );

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => RfcDispositionNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $requester->id,
        'data' => json_encode([
            'rfc_id' => $rfc->id,
            'rfc_number' => $rfc->rfc_number,
            'title' => $rfc->title,
            'status' => 'status_lama_tidak_dikenal',
            'message' => $legacyMessage,
        ]),
        'read_at' => null,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($requester)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($rfc->displayIdentifier().' diteruskan ke Tim Rilis')
        ->assertSee($legacyMessage);
});

it('menata menu Disposisi RFC setelah Daftar RFC dan hanya menandai satu menu aktif', function () {
    $manager = dispositionUser(['Change Manager']);
    $admin = dispositionUser(['Admin']);
    $requester = dispositionUser(['Pemohon']);
    $releaseTeam = dispositionUser(['Tim Rilis']);
    $rfc = dispositionRfc($requester);

    $this->actingAs($manager)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Disposisi RFC')
        ->assertSee(route('disposition.index'));

    $sidebar = dispositionSidebarHtml(
        $this->actingAs($manager)->get(route('notifications.index'))->assertOk()->getContent()
    );

    $positions = [
        strpos($sidebar, 'Dashboard Change Manager'),
        strpos($sidebar, 'Daftar RFC'),
        strpos($sidebar, 'Disposisi RFC'),
        strpos($sidebar, 'Riwayat Penghapusan'),
        strpos($sidebar, 'Notifikasi'),
        strpos($sidebar, 'Profil'),
    ];

    expect($positions)->not->toContain(false);

    for ($index = 1; $index < count($positions); $index++) {
        expect($positions[$index])->toBeGreaterThan($positions[$index - 1]);
    }

    foreach ([$admin, $requester, $releaseTeam] as $user) {
        $hiddenSidebar = dispositionSidebarHtml(
            $this->actingAs($user)->get(route('notifications.index'))->assertOk()->getContent()
        );

        expect($hiddenSidebar)->not->toContain('Disposisi RFC');
    }

    $this->actingAs($manager);

    $dispositionHtml = $this->get(route('disposition.index'))->assertOk()->getContent();

    expect(substr_count($dispositionHtml, 'aria-current="page"'))->toBe(1)
        ->and(dispositionSidebarActive($dispositionHtml, route('disposition.index')))->toBeTrue()
        ->and(dispositionSidebarActive($dispositionHtml, route('change-requests.index')))->toBeFalse();

    $assignHtml = $this->get(route('disposition.assign', $rfc))->assertOk()->getContent();

    expect(substr_count($assignHtml, 'aria-current="page"'))->toBe(1)
        ->and(dispositionSidebarActive($assignHtml, route('disposition.index')))->toBeTrue()
        ->and(dispositionSidebarActive($assignHtml, route('change-requests.index')))->toBeFalse();

    $listHtml = $this->get(route('change-requests.index'))->assertOk()->getContent();

    expect(substr_count($listHtml, 'aria-current="page"'))->toBe(1)
        ->and(dispositionSidebarActive($listHtml, route('change-requests.index')))->toBeTrue()
        ->and(dispositionSidebarActive($listHtml, route('disposition.index')))->toBeFalse();
});

it('tidak mengubah dashboard Change Manager dan daftar RFC yang sudah ada', function () {
    $manager = dispositionUser(['Change Manager']);

    $this->actingAs($manager)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Change Manager');

    $this->get(route('change-requests.index'))
        ->assertOk()
        ->assertSee('Daftar RFC');
});

it('membuat role Tim Rilis dengan izin yang benar tanpa rfc.view_all dan tidak mengubah role lain', function () {
    expect(Permission::query()->where('name', 'rfc.view_assigned')->exists())->toBeTrue();

    $releaseTeamRole = Role::findByName('Tim Rilis');

    expect($releaseTeamRole->permissions->pluck('name')->sort()->values()->all())->toBe([
        'rfc.record_execution',
        'rfc.view_assigned',
    ])->and($releaseTeamRole->hasPermissionTo('rfc.view_all'))->toBeFalse();

    $roles = ['Pemohon', 'Admin', 'Change Manager', 'Super Admin'];

    $before = collect($roles)->mapWithKeys(fn (string $role): array => [
        $role => Role::findByName($role)->permissions->pluck('name')->sort()->values()->all(),
    ])->all();

    $this->seed(PermissionSeeder::class);

    $after = collect($roles)->mapWithKeys(fn (string $role): array => [
        $role => Role::findByName($role)->permissions->pluck('name')->sort()->values()->all(),
    ])->all();

    expect($after)->toBe($before)
        ->and(Role::findByName('Admin')->hasPermissionTo('rfc.assign_release'))->toBeFalse()
        ->and(Role::findByName('Change Manager')->hasPermissionTo('rfc.view_assigned'))->toBeFalse();
});
