<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Daftar kanonik seluruh permission sistem PARAF.
     *
     * Daftar ini harus memuat permission yang sudah ada di database agar
     * syncPermissions() tidak mencabut izin yang diberikan secara manual.
     *
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'rfc.create',
        'rfc.view_own',
        'rfc.view_all',
        'rfc.view_assigned',
        'rfc.review_completeness',
        'rfc.assess_risk',
        'rfc.record_decision',
        'rfc.assign_release',
        'rfc.record_execution',
        'rfc.schedule',
        'rfc.close',
        'users.manage',
        'roles.manage',
        'master_data.manage',
        'audit_log.view',
        'release_team.manage',
    ];

    public function run(): void
    {
        // Reset cache permission
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 1. Role Pemohon (User OPD)
        $pemohonRole = Role::findOrCreate('Pemohon', 'web');
        $pemohonRole->syncPermissions([
            'rfc.create',
            'rfc.view_own',
        ]);

        // 2. Role Admin / Frontdesk (Diskominfostandi)
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->syncPermissions([
            'rfc.view_all',
            'rfc.review_completeness',
        ]);

        // 3. Role Change Manager
        $cmRole = Role::findOrCreate('Change Manager', 'web');
        $cmRole->syncPermissions([
            'rfc.view_all',
            'rfc.assess_risk',
            'rfc.record_decision',
            'rfc.assign_release',
            'rfc.schedule',
            'rfc.close',
        ]);

        // 4. Role Tim Rilis
        // Eksekusi belum diaktifkan pada Tahap A. Permission disiapkan agar
        // policy dan tahap berikutnya dapat memakai kontrak akses yang sama.
        $releaseTeamRole = Role::findOrCreate('Tim Rilis', 'web');
        $releaseTeamRole->syncPermissions([
            'rfc.view_assigned',
            'rfc.record_execution',
        ]);

        // 5. Role Super Admin
        // Akses teknis diberikan secara eksplisit. Tidak ada lagi Gate::before
        // yang memberikan seluruh ability tanpa permission.
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $superAdminRole->syncPermissions(self::PERMISSIONS);
    }
}
