<?php

namespace Database\Seeders;

use App\Enums\RfcStatus;
use App\Models\Application;
use App\Models\Authority;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Proteksi environment agar seeder dummy tidak berjalan di production
        if (app()->environment('production')) {
            return;
        }

        // 1. Eksekusi PermissionSeeder untuk mendaftarkan semua izin & role
        $this->call(PermissionSeeder::class);

        // Ambil role yang telah dibuat dan dikonfigurasi izinnya oleh PermissionSeeder
        $rolePemohon = Role::findByName('Pemohon');
        $roleAdmin = Role::findByName('Admin');
        $roleChangeManager = Role::findByName('Change Manager');

        // 2. Seed Master OPD Dummy
        $opdA = Opd::create([
            'code' => 'PD02',
            'name' => 'Dinas Perencanaan Sample',
        ]);

        $opdB = Opd::create([
            'code' => 'PD01',
            'name' => 'Dinas Pelayanan Sample',
        ]);

        // 3. Seed Users Dummy untuk 3 Role
        $admin = User::create([
            'opd_id' => $opdA->id,
            'name' => 'User Admin',
            'nip' => '199001012020011001',
            'email' => 'admin@example.com',
            'password' => Hash::make(env('SEEDER_ADMIN_PASSWORD', 'password')),
            'position' => 'Administrator Sistem',
            'phone_number' => '081200000001',
            'is_active' => true,
        ]);
        $admin->assignRole($roleAdmin);

        $cm = User::create([
            'opd_id' => $opdA->id,
            'name' => 'User Change Manager',
            'nip' => '198501012015011002',
            'email' => 'changemanager@example.com',
            'password' => Hash::make(env('SEEDER_CHANGE_MANAGER_PASSWORD', 'password')),
            'position' => 'Pengendali Perubahan IT',
            'phone_number' => '081200000002',
            'is_active' => true,
        ]);
        $cm->assignRole($roleChangeManager);

        $pemohon = User::create([
            'opd_id' => $opdB->id,
            'name' => 'User Pemohon',
            'nip' => '199501012022012003',
            'email' => 'pemohon@example.com',
            'password' => Hash::make(env('SEEDER_PEMOHON_PASSWORD', 'password')),
            'position' => 'Staf Pengelola Aplikasi',
            'phone_number' => '081200000003',
            'is_active' => true,
        ]);
        $pemohon->assignRole($rolePemohon);

        // 4. Seed Aplikasi Master Dummy
        $app1 = Application::create([
            'opd_id' => $opdB->id,
            'code' => 'APP-DUMMY-1',
            'name' => 'Sistem Informasi Sample A',
            'description' => 'Aplikasi contoh untuk pengujian alur perubahan sistem.',
            'criticality_level' => 'high',
        ]);

        $app2 = Application::create([
            'opd_id' => $opdA->id,
            'code' => 'APP-DUMMY-2',
            'name' => 'Sistem Informasi Sample B',
            'description' => 'Aplikasi contoh untuk simulasi surat fisik dan revisi data.',
            'criticality_level' => 'medium',
        ]);

        // 5. Seed Register Pejabat Berwenang Dummy (legacy; tidak dipakai form keputusan baru)
        Authority::create([
            'name' => 'Pejabat Penyetuju A',
            'nip' => '197501011998011004',
            'position' => 'Kepala Bidang IT Sample',
            'authority_type' => 'cab_member',
        ]);

        Authority::create([
            'name' => 'Pejabat Penyetuju B',
            'nip' => '197001011995011005',
            'position' => 'Kepala Dinas Sample',
            'authority_type' => 'head_of_department',
        ]);

        // 6. Seed akun Tim Rilis Dummy
        $this->call(ReleaseTeamSeeder::class);

        // 7. Seed Sample RFC Dummy
        ChangeRequest::create([
            'rfc_number' => 'RFC-2026-1',
            'title' => 'Pembaruan Versi Modul Utama Aplikasi Sample A',
            'application_id' => $app1->id,
            'submitted_by_id' => $pemohon->id,
            'created_by_id' => $pemohon->id,
            'is_physical_letter' => false,
            'reason_for_change' => 'Peningkatan performa dan optimasi basis data pada sistem contoh.',
            'impact_description' => 'Layanan mengalami jeda pemrosesan selama 15 menit saat pembaruan.',
            'status' => RfcStatus::SUBMITTED->value,
            'target_implementation_date' => '2026-10-24 22:00:00',
            'head_of_department_approved' => true,
            'head_of_department_approved_at' => '2026-10-20 09:30:00',
        ]);

        // RFC lama: tetap memakai nota dinas fisik dan tanggal usulan dari alur sebelumnya.
        ChangeRequest::create([
            'rfc_number' => 'RFC-2026-2',
            'title' => 'Penambahan Fitur Laporan Eksternal pada Aplikasi Sample B',
            'application_id' => $app2->id,
            'submitted_by_id' => $pemohon->id,
            'created_by_id' => $admin->id,
            'is_physical_letter' => true,
            'physical_letter_number' => '500/001/SAMPLE/2026',
            'reason_for_change' => 'Penyesuaian kebutuhan format dokumen laporan internal.',
            'impact_description' => 'Tidak ada dampak pemadaman sistem.',
            'status' => RfcStatus::DRAFT->value,
            'target_implementation_date' => '2026-10-28 20:00:00',
        ]);
    }
}
