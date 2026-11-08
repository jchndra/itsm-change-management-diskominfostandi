<?php

use App\Exceptions\UserAccountException;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Profile;
use App\Models\User;
use App\Services\UserAccountService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function userManagementUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => 'kata-sandi-lama',
        'is_active' => true,
    ], $attributes));

    $user->assignRole($role);

    return $user;
}

function userManagementSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

test('Admin dapat mereset kata sandi dan kata sandi sementara hanya ditampilkan sekali', function () {
    $admin = userManagementUser('Admin');
    $target = userManagementUser('Pemohon', [
        'name' => 'Pengguna Reset',
        'email' => 'pengguna.reset@example.test',
    ]);

    userManagementSession($target);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Kelola Pengguna');

    $component = Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openResetModal', $target->id)
        ->call('resetPassword');

    $temporaryPassword = $component->get('temporaryPassword');

    expect($temporaryPassword)->toHaveLength(12)
        ->and(Hash::check($temporaryPassword, $target->fresh()->password))->toBeTrue()
        ->and($target->fresh()->must_change_password)->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and(data_get($activity->properties, 'event'))->toBe('password_reset_by_admin')
        ->and(json_encode($activity->properties))->not->toContain($temporaryPassword)
        ->and(json_encode(session()->all()))->not->toContain($temporaryPassword);

    $component->call('closeResetModal')->assertSet('temporaryPassword', '');

    Auth::logout();

    Livewire::test(Login::class)
        ->set('email', $target->email)
        ->set('password', $temporaryPassword)
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($target->fresh());
});

test('Pemohon Change Manager dan Tim Rilis ditolak dari halaman kelola pengguna', function (string $role) {
    $user = userManagementUser($role);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
})->with(['Pemohon', 'Change Manager', 'Tim Rilis']);

test('Admin tidak dapat mereset atau menonaktifkan diri sendiri dan Super Admin', function () {
    $admin = userManagementUser('Admin');
    $superAdmin = userManagementUser('Super Admin');

    expect($admin->can('resetPassword', $admin))->toBeFalse()
        ->and($admin->can('toggleActive', $admin))->toBeFalse()
        ->and($admin->can('resetPassword', $superAdmin))->toBeFalse()
        ->and($admin->can('toggleActive', $superAdmin))->toBeFalse();

    $service = app(UserAccountService::class);

    expect(fn () => $service->resetPassword($admin, $admin))
        ->toThrow(UserAccountException::class);
    expect(fn () => $service->setActive($admin, $admin, false))
        ->toThrow(UserAccountException::class);
    expect(fn () => $service->resetPassword($superAdmin, $admin))
        ->toThrow(UserAccountException::class);
    expect(fn () => $service->setActive($superAdmin, $admin, false))
        ->toThrow(UserAccountException::class);
});

test('Super Admin tetap dapat mengelola Super Admin lain', function () {
    $actor = userManagementUser('Super Admin');
    $target = userManagementUser('Super Admin');

    expect($actor->can('resetPassword', $target))->toBeTrue()
        ->and($actor->can('toggleActive', $target))->toBeTrue();
});

test('pengguna wajib ganti kata sandi diarahkan ke profil tetapi profil dan logout tetap dapat diakses', function () {
    $user = userManagementUser('Pemohon', ['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('change-requests.index'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('warning', 'Anda harus mengganti kata sandi sementara terlebih dahulu.');

    $this->get(route('profile.show'))->assertOk()->assertSee('Anda harus mengganti kata sandi sementara terlebih dahulu.');

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('mengganti kata sandi menghapus tanda wajib ganti password', function () {
    $user = userManagementUser('Pemohon', ['must_change_password' => true]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('current_password', 'kata-sandi-lama')
        ->set('password', 'kata-sandi-baru-aman')
        ->set('password_confirmation', 'kata-sandi-baru-aman')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('kata-sandi-baru-aman', $user->fresh()->password))->toBeTrue();
});

test('akun nonaktif tetap tidak dapat login setelah pengelolaan akun', function () {
    $user = userManagementUser('Pemohon', [
        'email' => 'nonaktif@example.test',
        'is_active' => false,
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'kata-sandi-lama')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});
