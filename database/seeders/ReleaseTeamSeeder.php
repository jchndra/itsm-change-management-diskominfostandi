<?php

namespace Database\Seeders;

use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class ReleaseTeamSeeder extends Seeder
{
    public function run(): void
    {
        // Proteksi environment agar seeder dummy tidak berjalan di production
        if (app()->environment('production')) {
            return;
        }

        $password = config('seeding.release_team_password');

        if (blank($password) || strlen((string) $password) < 12) {
            throw new RuntimeException('Isi SEEDER_RELEASE_TEAM_PASSWORD di .env (min. 12 karakter).');
        }

        if (! Role::where('name', 'Tim Rilis')->exists()) {
            $this->call(PermissionSeeder::class);
        }

        $members = [
            [
                'name' => 'Tim Rilis Aplikasi',
                'email' => 'aplikasi@gmail.com',
                'role_title' => 'DevOps Engineer Sample',
            ],
            [
                'name' => 'Tim Rilis Database',
                'email' => 'database@gmail.com',
                'role_title' => 'Database Administrator Sample',
            ],
            [
                'name' => 'Tim Rilis Infrastruktur Jaringan',
                'email' => 'jaringan@gmail.com',
                'role_title' => 'System Administrator Sample',
            ],
        ];

        foreach ($members as $member) {
            $user = User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'opd_id' => null,
                    'nip' => null,
                    'position' => null,
                    'phone_number' => null,
                ],
            );

            $user->syncRoles(['Tim Rilis']);

            ReleaseTeamMember::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $member['name'],
                    'role_title' => $member['role_title'],
                    'is_active' => true,
                ],
            );
        }

        // Bersihkan anggota dummy lama yang belum tertaut dan belum punya tugas.
        ReleaseTeamMember::query()
            ->whereNull('user_id')
            ->whereIn('name', ['Pelaksana Teknis A', 'Pelaksana Teknis B'])
            ->doesntHave('releaseTasks')
            ->delete();
    }
}
