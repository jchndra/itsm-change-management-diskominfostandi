<?php

use App\Enums\RfcStatus;
use App\Livewire\Admin\ReleaseTeamAccounts;
use App\Models\ChangeRequest;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\DashboardRedirector;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @param  array<int, string>  $roles
 */
function releaseTeamPermissionUser(array $roles = []): User
{
    $user = User::factory()->create(['is_active' => true]);

    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

it('mendefinisikan permission dan role Tim Rilis secara eksplisit', function () {
    $permissions = Permission::query()->pluck('name')->all();

    expect($permissions)->toContain(
        'rfc.view_assigned',
        'rfc.record_execution',
        'rfc.schedule',
        'release_team.manage',
    );

    $releaseTeam = Role::findByName('Tim Rilis');
    $releaseTeamPermissions = $releaseTeam->permissions->pluck('name')->sort()->values()->all();

    expect($releaseTeamPermissions)->toBe([
        'rfc.record_execution',
        'rfc.view_assigned',
    ])->and($releaseTeam->hasPermissionTo('rfc.view_all'))->toBeFalse()
        ->and($releaseTeam->hasPermissionTo('rfc.schedule'))->toBeFalse();

    $changeManager = Role::findByName('Change Manager');

    expect($changeManager->hasPermissionTo('rfc.schedule'))->toBeTrue()
        ->and($changeManager->hasPermissionTo('rfc.assign_release'))->toBeTrue()
        ->and($changeManager->hasPermissionTo('release_team.manage'))->toBeFalse();
});

it('memberikan Super Admin permission eksplisit tanpa bypass global', function () {
    $superAdmin = releaseTeamPermissionUser(['Super Admin']);
    $pemohon = releaseTeamPermissionUser(['Pemohon']);

    expect($superAdmin->hasPermissionTo('release_team.manage'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('rfc.view_all'))->toBeTrue()
        ->and($pemohon->hasPermissionTo('release_team.manage'))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('ability-yang-tidak-didefinisikan'))->toBeFalse();

    $rfc = ChangeRequest::factory()->create([
        'submitted_by_id' => $pemohon->id,
        'status' => RfcStatus::APPROVED->value,
    ]);

    expect($superAdmin->can('view', $rfc))->toBeTrue();

    Role::findByName('Super Admin')->revokePermissionTo('rfc.view_all');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($superAdmin->fresh()->can('view', $rfc))->toBeFalse();
});

it('mengarahkan Tim Rilis ke daftar RFC yang ditugaskan', function () {
    $releaseTeamUser = releaseTeamPermissionUser(['Tim Rilis']);

    ReleaseTeamMember::factory()->create([
        'user_id' => $releaseTeamUser->id,
        'is_active' => true,
    ]);

    expect(app(DashboardRedirector::class)->routeNameFor($releaseTeamUser))
        ->toBe('change-requests.index');

    $this->actingAs($releaseTeamUser)
        ->get('/')
        ->assertRedirect(route('change-requests.index'));
});

it('menampilkan menu pengelolaan akun hanya bagi pengguna dengan permission', function () {
    $superAdmin = releaseTeamPermissionUser(['Super Admin']);
    $changeManager = releaseTeamPermissionUser(['Change Manager']);
    $releaseTeam = releaseTeamPermissionUser(['Tim Rilis']);

    $this->actingAs($superAdmin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Akun Tim Rilis')
        ->assertSee(route('admin.release-team-accounts.index'));

    $this->actingAs($changeManager)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee('Akun Tim Rilis');

    $this->actingAs($releaseTeam)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Daftar RFC')
        ->assertDontSee('Akun Tim Rilis')
        ->assertDontSee('Dashboard Change Manager');
});

it('menolak halaman akun Tim Rilis bagi pengguna tanpa permission pengelolaan', function (string $role) {
    $user = releaseTeamPermissionUser([$role]);

    $this->actingAs($user)
        ->get(route('admin.release-team-accounts.index'))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(ReleaseTeamAccounts::class)
        ->assertForbidden();
})->with([
    'admin' => ['Admin'],
    'change manager' => ['Change Manager'],
    'pemohon' => ['Pemohon'],
    'tim rilis' => ['Tim Rilis'],
]);
