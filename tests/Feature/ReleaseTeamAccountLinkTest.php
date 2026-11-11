<?php

use App\Exceptions\ReleaseTeamAccountException;
use App\Livewire\Admin\ReleaseTeamAccounts;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\ReleaseTeamAccountLinker;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReleaseTeamSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function releaseTeamLinkUser(array $roles = [], array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $attributes));

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

function releaseTeamLinkMember(array $attributes = []): ReleaseTeamMember
{
    return ReleaseTeamMember::factory()->create(array_merge([
        'is_active' => true,
        'user_id' => null,
    ], $attributes));
}

it('menautkan akun yang sudah ada tanpa membuat akun atau tugas baru', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $target = releaseTeamLinkUser(['Pemohon']);

    $userCount = User::count();
    $taskCount = ReleaseTask::count();

    $linked = app(ReleaseTeamAccountLinker::class)->link($member, $target, $actor);

    expect($linked->user_id)->toBe($target->id)
        ->and($linked->user->is($target))->toBeTrue()
        ->and($target->fresh()->hasRole('Tim Rilis'))->toBeTrue()
        ->and($target->fresh()->hasRole('Pemohon'))->toBeTrue()
        ->and(User::count())->toBe($userCount)
        ->and(ReleaseTask::count())->toBe($taskCount);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'release_team',
        'subject_type' => ReleaseTeamMember::class,
        'subject_id' => $member->id,
        'causer_id' => $actor->id,
    ]);
});

it('menautkan akun lewat halaman pengelolaan Tim Rilis', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $target = releaseTeamLinkUser();

    $this->actingAs($actor)
        ->get(route('admin.release-team-accounts.index'))
        ->assertOk()
        ->assertSee('Akun Tim Rilis')
        ->assertSee($member->name);

    Livewire::actingAs($actor)
        ->test(ReleaseTeamAccounts::class)
        ->assertOk()
        ->assertSee($member->name)
        ->call('openLinkModal', $member->id)
        ->assertSet('showLinkModal', true)
        ->set('selectedUserId', (string) $target->id)
        ->call('link')
        ->assertHasNoErrors()
        ->assertSet('showLinkModal', false)
        ->assertSee('berhasil ditautkan');

    expect($member->fresh()->user_id)->toBe($target->id)
        ->and($target->fresh()->hasRole('Tim Rilis'))->toBeTrue();
});

it('menampilkan hanya akun yang memenuhi syarat pada pilihan penautan', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $eligible = releaseTeamLinkUser();
    $admin = releaseTeamLinkUser(['Admin']);
    $manager = releaseTeamLinkUser(['Change Manager']);
    $otherSuperAdmin = releaseTeamLinkUser(['Super Admin']);
    $inactive = releaseTeamLinkUser(attributes: ['is_active' => false]);
    $alreadyLinked = releaseTeamLinkUser();
    releaseTeamLinkMember(['user_id' => $alreadyLinked->id]);

    Livewire::actingAs($actor)
        ->test(ReleaseTeamAccounts::class)
        ->call('openLinkModal', $member->id)
        ->assertViewHas('availableUsers', function ($users) use ($actor, $eligible, $admin, $manager, $otherSuperAdmin, $inactive, $alreadyLinked): bool {
            $contains = fn (User $user): bool => $users->contains(fn (User $candidate): bool => $candidate->is($user));

            return $contains($eligible)
                && ! $contains($actor)
                && ! $contains($admin)
                && ! $contains($manager)
                && ! $contains($otherSuperAdmin)
                && ! $contains($inactive)
                && ! $contains($alreadyLinked);
        });
});

it('menolak akun Admin, Change Manager, dan Super Admin lain sebagai target', function (string $role) {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $target = releaseTeamLinkUser([$role]);

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($member, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($member->fresh()->user_id)->toBeNull()
        ->and($target->fresh()->hasRole('Tim Rilis'))->toBeFalse();
})->with([
    'admin' => ['Admin'],
    'change manager' => ['Change Manager'],
    'super admin' => ['Super Admin'],
]);

it('menolak akun sendiri sebagai target penautan', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($member, $actor, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($member->fresh()->user_id)->toBeNull();
});

it('menolak target yang tidak aktif', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $target = releaseTeamLinkUser(attributes: ['is_active' => false]);

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($member, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($member->fresh()->user_id)->toBeNull();
});

it('menolak anggota Tim Rilis yang tidak aktif atau sudah tertaut', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $target = releaseTeamLinkUser();
    $inactiveMember = releaseTeamLinkMember(['is_active' => false]);
    $linkedTarget = releaseTeamLinkUser();
    $linkedMember = releaseTeamLinkMember(['user_id' => $linkedTarget->id]);

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($inactiveMember, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($linkedMember, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($inactiveMember->fresh()->user_id)->toBeNull()
        ->and($linkedMember->fresh()->user_id)->toBe($linkedTarget->id);
});

it('menolak akun yang sudah tertaut ke anggota Tim Rilis lain', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $target = releaseTeamLinkUser();
    $firstMember = releaseTeamLinkMember(['user_id' => $target->id]);
    $secondMember = releaseTeamLinkMember();

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($secondMember, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($firstMember->fresh()->user_id)->toBe($target->id)
        ->and($secondMember->fresh()->user_id)->toBeNull();
});

it('menolak request Livewire yang memanipulasi target akun', function () {
    $actor = releaseTeamLinkUser(['Super Admin']);
    $member = releaseTeamLinkMember();
    $admin = releaseTeamLinkUser(['Admin']);

    Livewire::actingAs($actor)
        ->test(ReleaseTeamAccounts::class)
        ->call('openLinkModal', $member->id)
        ->set('selectedUserId', (string) $admin->id)
        ->call('link')
        ->assertHasErrors('selectedUserId');

    expect($member->fresh()->user_id)->toBeNull()
        ->and($admin->fresh()->hasRole('Tim Rilis'))->toBeFalse();
});

it('menjaga keunikan tautan akun pada tingkat database', function () {
    $firstUser = releaseTeamLinkUser();
    $secondUser = releaseTeamLinkUser();

    releaseTeamLinkMember(['user_id' => $firstUser->id]);

    expect(fn () => releaseTeamLinkMember(['user_id' => $firstUser->id]))
        ->toThrow(QueryException::class);

    releaseTeamLinkMember(['user_id' => $secondUser->id]);

    expect(ReleaseTeamMember::query()->whereNotNull('user_id')->count())->toBe(2);
});

it('menyemai tiga akun Tim Rilis dummy secara idempoten', function () {
    config(['seeding.release_team_password' => 'TestPassword-123!']);

    $this->seed(ReleaseTeamSeeder::class);
    $this->seed(ReleaseTeamSeeder::class);

    $members = ReleaseTeamMember::query()->whereNotNull('user_id')->get();

    expect($members)->toHaveCount(3)
        ->and($members->pluck('user_id')->unique())->toHaveCount(3)
        ->and($members->pluck('name')->sort()->values()->all())->toBe([
            'Tim Rilis Aplikasi',
            'Tim Rilis Infrastruktur Jaringan',
            'Tim Rilis Pusat Data',
        ])
        ->and($members->pluck('role_title')->sort()->values()->all())->toBe([
            'Data Center Engineer',
            'DevOps Engineer',
            'System Administrator',
        ])
        ->and($members->every(fn (ReleaseTeamMember $member): bool => ! str_contains($member->role_title, 'Sample')))->toBeTrue();

    foreach ($members as $member) {
        $user = $member->user;

        expect($user)->not->toBeNull()
            ->and($user->is_active)->toBeTrue()
            ->and($user->hasRole('Tim Rilis'))->toBeTrue()
            ->and($user->password)->not->toBe('TestPassword-123!')
            ->and(Hash::check('TestPassword-123!', $user->password))->toBeTrue();
    }
});

it('menolak penautan oleh pengguna tanpa permission pengelolaan', function () {
    $actor = releaseTeamLinkUser(['Change Manager']);
    $member = releaseTeamLinkMember();
    $target = releaseTeamLinkUser();

    $this->actingAs($actor)
        ->get(route('admin.release-team-accounts.index'))
        ->assertForbidden();

    expect(fn () => app(ReleaseTeamAccountLinker::class)->link($member, $target, $actor))
        ->toThrow(ReleaseTeamAccountException::class);

    expect($member->fresh()->user_id)->toBeNull();
});
