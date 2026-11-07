<?php

namespace Database\Seeders;

use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use App\Services\RfcNumberGenerator;
use App\Services\TicketNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

class SampleRfcSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const RELEASE_TEAM_NAMES = [
        'Tim Rilis Aplikasi',
        'Tim Rilis Database',
        'Tim Rilis Infrastruktur Jaringan',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $pemohon = User::query()->where('email', 'pemohon@example.com')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $changeManager = User::query()->where('email', 'cm@example.com')->first()
            ?? User::query()->where('email', 'changemanager@example.com')->first();

        if ($pemohon === null || $admin === null || $changeManager === null) {
            throw new RuntimeException(
                'Prasyarat akun belum lengkap: pemohon@example.com, admin@example.com, dan cm@example.com wajib tersedia.'
            );
        }

        $opdCode = $pemohon->opd?->code;

        if (blank($opdCode)) {
            throw new RuntimeException('Akun pemohon@example.com belum memiliki OPD sehingga nomor RFC tidak dapat dibuat.');
        }

        /** @var Collection<string, ReleaseTeamMember> $releaseTeamMembers */
        $releaseTeamMembers = ReleaseTeamMember::query()
            ->whereIn('name', self::RELEASE_TEAM_NAMES)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->whereHas('user')
            ->get()
            ->keyBy('name');

        foreach (self::RELEASE_TEAM_NAMES as $name) {
            if (! $releaseTeamMembers->has($name)) {
                throw new RuntimeException(
                    "Prasyarat Tim Rilis belum lengkap: anggota aktif tertaut user dengan nama \"{$name}\" wajib tersedia."
                );
            }
        }

        $applications = Application::query()->orderBy('id')->get();

        if ($applications->isEmpty()) {
            $applications = Application::factory()->count(2)->create();
        }

        foreach ($this->rfcDefinitions() as $index => $definition) {
            $alreadyExists = ChangeRequest::withTrashed()
                ->where('title', $definition['title'])
                ->where('submitted_by_id', $pemohon->id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $ticketCreatedAt = $definition['ticket_created_at'];

            $rfc = ChangeRequest::create([
                'rfc_number' => RfcNumberGenerator::generate($opdCode),
                'ticket_number' => TicketNumberGenerator::generate(),
                'title' => $definition['title'],
                'application_id' => $applications[$index % $applications->count()]->id,
                'submitted_by_id' => $pemohon->id,
                'created_by_id' => $pemohon->id,
                'description' => $definition['description'],
                'legal_basis' => $definition['legal_basis'],
                'reason_for_change' => $definition['reason_for_change'],
                'change_risk' => $definition['change_risk'],
                'impact_description' => $definition['impact_description'],
                'change_type' => 'normal',
                'status' => $definition['status']->value,
                'target_implementation_date' => $definition['target_implementation_date']
                    ?? $ticketCreatedAt->copy()->addDays(7),
                'head_of_department_approved' => true,
                'head_of_department_approved_at' => $ticketCreatedAt->copy()->subDays(2),
                'ticket_created_at' => $ticketCreatedAt,
                'ticket_created_by_id' => $admin->id,
                'priority' => $definition['priority']->value,
                'category' => $definition['category']->value,
                'rejection_note' => $definition['rejection_note'] ?? null,
                'closed_at' => $definition['closed_at'] ?? null,
                'closed_by_id' => ($definition['closed_at'] ?? null) !== null ? $changeManager->id : null,
                'closure_notes' => $definition['closure_notes'] ?? null,
            ]);

            $this->recordTransition($rfc, RfcStatus::SUBMITTED, RfcStatus::TICKET_CREATED, $admin, $ticketCreatedAt);

            $approvedAt = $definition['approved_at'] ?? null;

            if ($approvedAt !== null) {
                $this->recordTransition($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, $changeManager, $approvedAt);
            }

            $taskDefinition = $definition['task'] ?? null;
            $taskMember = $taskDefinition !== null ? $releaseTeamMembers->get($taskDefinition['member']) : null;
            $taskActor = $taskMember?->user;

            $inProgressAt = $definition['in_progress_at'] ?? null;

            if ($inProgressAt !== null && $taskActor !== null) {
                $this->recordTransition($rfc, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $taskActor, $inProgressAt);
            }

            $implementedAt = $definition['implemented_at'] ?? null;

            if ($implementedAt !== null && $taskActor !== null) {
                $this->recordTransition($rfc, RfcStatus::IN_PROGRESS, RfcStatus::IMPLEMENTED, $taskActor, $implementedAt);
            }

            $failedAt = $definition['failed_at'] ?? null;

            if ($failedAt !== null && $taskActor !== null) {
                $this->recordTransition($rfc, RfcStatus::IN_PROGRESS, RfcStatus::FAILED, $taskActor, $failedAt);
            }

            $closedAt = $definition['closed_at'] ?? null;

            if ($closedAt !== null) {
                $this->recordTransition($rfc, RfcStatus::IMPLEMENTED, RfcStatus::CLOSED, $changeManager, $closedAt);
            }

            $rejectedAt = $definition['rejected_at'] ?? null;

            if ($rejectedAt !== null) {
                $this->recordTransition(
                    $rfc,
                    RfcStatus::TICKET_CREATED,
                    RfcStatus::REJECTED,
                    $changeManager,
                    $rejectedAt,
                    $definition['rejection_note'] ?? null,
                );
            }

            if ($taskDefinition !== null && $taskMember !== null) {
                ReleaseTask::create([
                    'change_request_id' => $rfc->id,
                    'release_team_member_id' => $taskMember->id,
                    'assigned_by_id' => $changeManager->id,
                    'instruction' => $taskDefinition['instruction'],
                    'scheduled_start' => $taskDefinition['scheduled_start'],
                    'scheduled_end' => $taskDefinition['scheduled_end'],
                    'execution_status' => $taskDefinition['execution_status'],
                    'executed_at' => $taskDefinition['executed_at'] ?? null,
                    'execution_notes' => $taskDefinition['execution_notes'] ?? null,
                ]);
            }
        }
    }

    private function recordTransition(
        ChangeRequest $rfc,
        RfcStatus $oldStatus,
        RfcStatus $newStatus,
        User $actor,
        Carbon $occurredAt,
        ?string $note = null,
    ): void {
        Activity::create([
            'log_name' => 'rfc',
            'description' => "Status RFC diubah dari {$oldStatus->label()} menjadi {$newStatus->label()}.",
            'subject_type' => $rfc->getMorphClass(),
            'subject_id' => $rfc->getKey(),
            'causer_type' => $actor->getMorphClass(),
            'causer_id' => $actor->getKey(),
            'properties' => [
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'note' => $note,
            ],
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rfcDefinitions(): array
    {
        return [
            [
                'title' => 'Pembaruan Versi PHP Portal Layanan',
                'status' => RfcStatus::TICKET_CREATED,
                'ticket_created_at' => now()->subDay(),
                'priority' => TicketPriority::MEDIUM,
                'category' => ServiceCategory::APPLICATION,
                'description' => 'Pembaruan versi PHP pada portal layanan agar tetap menerima dukungan keamanan.',
                'legal_basis' => 'Kebijakan pemeliharaan aplikasi layanan publik tahunan.',
                'reason_for_change' => 'Versi PHP lama tidak lagi menerima pembaruan keamanan.',
                'change_risk' => 'Potensi gangguan singkat saat proses build dan restart layanan.',
                'impact_description' => 'Portal layanan tidak dapat diakses selama kurang lebih 10 menit.',
            ],
            [
                'title' => 'Penambahan Bandwidth Link Backbone',
                'status' => RfcStatus::TICKET_CREATED,
                'ticket_created_at' => now()->subDays(6),
                'priority' => TicketPriority::HIGH,
                'category' => ServiceCategory::NETWORK,
                'description' => 'Penambahan kapasitas bandwidth pada link backbone utama antar gedung.',
                'legal_basis' => 'Rencana peningkatan kapasitas jaringan triwulan berjalan.',
                'reason_for_change' => 'Utilisasi link backbone mendekati batas maksimum pada jam sibuk.',
                'change_risk' => 'Perubahan konfigurasi dapat memutus koneksi sementara.',
                'impact_description' => 'Akses aplikasi internal melambat selama proses pemindahan link.',
            ],
            [
                'title' => 'Migrasi Basis Data Kepegawaian',
                'status' => RfcStatus::APPROVED,
                'ticket_created_at' => now()->subDays(3),
                'approved_at' => now()->subDays(2),
                'priority' => TicketPriority::MEDIUM,
                'category' => ServiceCategory::DATABASE,
                'description' => 'Migrasi basis data kepegawaian ke server baru dengan versi mesin yang lebih modern.',
                'legal_basis' => 'Kebijakan konsolidasi dan pembaruan infrastruktur basis data.',
                'reason_for_change' => 'Server lama sudah melewati masa dukungan dan kapasitas penyimpanan menipis.',
                'change_risk' => 'Kehilangan data apabila prosedur backup dan verifikasi tidak dijalankan.',
                'impact_description' => 'Layanan kepegawaian tidak tersedia selama proses migrasi.',
            ],
            [
                'title' => 'Pembaruan Modul Internal Aplikasi Keuangan',
                'status' => RfcStatus::APPROVED,
                'ticket_created_at' => now()->subDays(5),
                'approved_at' => now()->subDays(4),
                'priority' => TicketPriority::HIGH,
                'category' => ServiceCategory::APPLICATION,
                'description' => 'Pembaruan modul internal aplikasi keuangan untuk memperbaiki alur validasi anggaran.',
                'legal_basis' => 'Permintaan perbaikan dari unit keuangan dan akuntansi.',
                'reason_for_change' => 'Ditemukan kesalahan validasi pada proses pengajuan anggaran.',
                'change_risk' => 'Alur persetujuan dapat tertahan bila konfigurasi tidak sesuai.',
                'impact_description' => 'Pengguna tidak dapat mengajukan anggaran selama jendela pemeliharaan.',
                'task' => [
                    'member' => 'Tim Rilis Aplikasi',
                    'execution_status' => 'pending',
                    'instruction' => 'Terapkan pembaruan modul keuangan dan verifikasi alur validasi anggaran.',
                    'scheduled_start' => now()->addDay(),
                    'scheduled_end' => now()->addDay()->addHours(3),
                ],
            ],
            [
                'title' => 'Optimasi Indeks Basis Data Persediaan',
                'status' => RfcStatus::IN_PROGRESS,
                'ticket_created_at' => now()->subDays(8),
                'approved_at' => now()->subDays(7),
                'in_progress_at' => now()->subDays(6),
                'priority' => TicketPriority::HIGH,
                'category' => ServiceCategory::DATABASE,
                'description' => 'Optimasi indeks dan statistik basis data persediaan untuk mempercepat laporan.',
                'legal_basis' => 'Evaluasi kinerja aplikasi persediaan periode berjalan.',
                'reason_for_change' => 'Laporan persediaan membutuhkan waktu lebih dari dua menit untuk dimuat.',
                'change_risk' => 'Pembuatan indeks dapat meningkatkan beban tulis sementara.',
                'impact_description' => 'Laporan persediaan melambat selama proses optimasi berlangsung.',
                'task' => [
                    'member' => 'Tim Rilis Database',
                    'execution_status' => 'in_progress',
                    'instruction' => 'Buat indeks baru dan perbarui statistik tabel persediaan.',
                    'scheduled_start' => now()->subDay(),
                    'scheduled_end' => now()->addDay(),
                ],
            ],
            [
                'title' => 'Peremajaan Perangkat Jaringan Gedung Utama',
                'status' => RfcStatus::IN_PROGRESS,
                'ticket_created_at' => now()->subDays(10),
                'approved_at' => now()->subDays(9),
                'in_progress_at' => now()->subDays(8),
                'priority' => TicketPriority::MEDIUM,
                'category' => ServiceCategory::INFRASTRUCTURE,
                'description' => 'Peremajaan perangkat jaringan pada ruang server gedung utama.',
                'legal_basis' => 'Jadwal pemeliharaan infrastruktur jaringan tahunan.',
                'reason_for_change' => 'Perangkat lama mengalami kegagalan port secara berkala.',
                'change_risk' => 'Koneksi jaringan dapat terputus saat penggantian perangkat.',
                'impact_description' => 'Jaringan gedung utama tidak tersedia di luar jam layanan.',
                'task' => [
                    'member' => 'Tim Rilis Infrastruktur Jaringan',
                    'execution_status' => 'in_progress',
                    'instruction' => 'Ganti perangkat jaringan dan uji konektivitas seluruh segmen.',
                    'scheduled_start' => now()->subHours(12),
                    'scheduled_end' => now()->addHours(12),
                ],
            ],
            [
                'title' => 'Peningkatan Kapasitas Server Aplikasi Perizinan',
                'status' => RfcStatus::IMPLEMENTED,
                'ticket_created_at' => now()->subDays(12),
                'approved_at' => now()->subDays(11),
                'in_progress_at' => now()->subDays(10),
                'implemented_at' => now()->subDays(8),
                'priority' => TicketPriority::MEDIUM,
                'category' => ServiceCategory::APPLICATION,
                'description' => 'Peningkatan kapasitas CPU dan memori server aplikasi perizinan.',
                'legal_basis' => 'Rencana peningkatan kapasitas layanan perizinan terpadu.',
                'reason_for_change' => 'Penggunaan CPU konsisten tinggi saat jam pengajuan izin.',
                'change_risk' => 'Server perlu dimulai ulang setelah perubahan kapasitas.',
                'impact_description' => 'Aplikasi perizinan tidak dapat diakses selama restart.',
                'task' => [
                    'member' => 'Tim Rilis Aplikasi',
                    'execution_status' => 'successful',
                    'instruction' => 'Terapkan peningkatan kapasitas dan uji beban aplikasi perizinan.',
                    'scheduled_start' => now()->subDays(9),
                    'scheduled_end' => now()->subDays(8),
                    'executed_at' => now()->subDays(8),
                ],
            ],
            [
                'title' => 'Perbaikan Skema Basis Data Layanan Publik',
                'status' => RfcStatus::FAILED,
                'ticket_created_at' => now()->subDays(14),
                'approved_at' => now()->subDays(13),
                'in_progress_at' => now()->subDays(12),
                'failed_at' => now()->subDays(10),
                'priority' => TicketPriority::HIGH,
                'category' => ServiceCategory::DATABASE,
                'description' => 'Perbaikan skema basis data layanan publik untuk menambah kolom pelacakan berkas.',
                'legal_basis' => 'Kebutuhan integrasi pelacakan berkas layanan publik.',
                'reason_for_change' => 'Skema lama tidak mendukung pencatatan status berkas digital.',
                'change_risk' => 'Migrasi skema dapat gagal apabila data lama tidak konsisten.',
                'impact_description' => 'Layanan publik berisiko mengalami galat saat migrasi skema.',
                'task' => [
                    'member' => 'Tim Rilis Database',
                    'execution_status' => 'rolled_back',
                    'instruction' => 'Jalankan migrasi skema dan lakukan verifikasi integritas data.',
                    'scheduled_start' => now()->subDays(12),
                    'scheduled_end' => now()->subDays(10),
                    'executed_at' => now()->subDays(10),
                    'execution_notes' => 'Rollback dilakukan karena ditemukan data lama yang tidak konsisten.',
                ],
            ],
            [
                'title' => 'Pembaruan Portal Informasi dan Pengumuman',
                'status' => RfcStatus::CLOSED,
                'ticket_created_at' => now()->subDays(20),
                'approved_at' => now()->subDays(19),
                'in_progress_at' => now()->subDays(18),
                'implemented_at' => now()->subDays(16),
                'closed_at' => now()->subDays(15),
                'closure_notes' => 'Pembaruan portal berhasil dan telah diverifikasi oleh unit pengelola layanan.',
                'priority' => TicketPriority::MEDIUM,
                'category' => ServiceCategory::APPLICATION,
                'description' => 'Pembaruan tampilan dan modul pengumuman pada portal informasi publik.',
                'legal_basis' => 'Kebijakan keterbukaan informasi dan peningkatan layanan publik.',
                'reason_for_change' => 'Tampilan lama kurang ramah perangkat bergerak.',
                'change_risk' => 'Kesalahan templat dapat membuat halaman pengumuman tidak tampil.',
                'impact_description' => 'Portal informasi tidak dapat diakses selama proses pembaruan.',
                'task' => [
                    'member' => 'Tim Rilis Aplikasi',
                    'execution_status' => 'successful',
                    'instruction' => 'Terapkan pembaruan portal dan periksa tampilan pada perangkat bergerak.',
                    'scheduled_start' => now()->subDays(17),
                    'scheduled_end' => now()->subDays(16),
                    'executed_at' => now()->subDays(16),
                ],
            ],
            [
                'title' => 'Penambahan Fitur Laporan Aplikasi Arsip',
                'status' => RfcStatus::REJECTED,
                'ticket_created_at' => now()->subDays(4),
                'rejected_at' => now()->subDays(3),
                'rejection_note' => 'Ditolak karena kebutuhan laporan belum didukung data sumber yang lengkap.',
                'priority' => TicketPriority::LOW,
                'category' => ServiceCategory::APPLICATION,
                'description' => 'Penambahan fitur laporan rekap pada aplikasi arsip digital.',
                'legal_basis' => 'Usulan peningkatan layanan kearsipan internal.',
                'reason_for_change' => 'Unit kearsipan membutuhkan rekap berkas bulanan.',
                'change_risk' => 'Laporan berpotensi menampilkan data yang tidak lengkap.',
                'impact_description' => 'Tidak ada dampak langsung karena fitur masih berupa usulan.',
            ],
        ];
    }
}
