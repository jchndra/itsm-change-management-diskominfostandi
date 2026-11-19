<?php

namespace Database\Seeders;

use App\Enums\DecisionStatus;
use App\Enums\FollowUpAction;
use App\Enums\ImplementationResult;
use App\Enums\RfcStatus;
use App\Enums\ServiceCategory;
use App\Enums\TicketPriority;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestImplementation;
use App\Models\Decision;
use App\Models\ReleaseTask;
use App\Models\ReleaseTaskRiskAnalysis;
use App\Models\ReleaseTaskUpdate;
use App\Models\ReleaseTeamMember;
use App\Models\RfcSatisfaction;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Services\RfcNumberGenerator;
use App\Services\TicketNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

/**
 * Seeder demo presentasi.
 *
 * Seeder ini menghapus seluruh data RFC yang ada (tugas Tim Rilis, analisis
 * risiko, update progres, keputusan, implementasi, kepuasan, lampiran, bukti,
 * dan riwayat aktivitas RFC), lalu membuat ulang 14 RFC contoh yang mencakup
 * seluruh 12 status. Semua RFC dibuat oleh akun Pemohon sehingga antrean
 * Change Manager dan ketiga akun Tim Rilis memiliki pekerjaan.
 *
 * Aman dijalankan berulang kali:
 *
 *     php artisan db:seed --class=DemoRfcSeeder
 */
class DemoRfcSeeder extends Seeder
{
    private User $pemohon;

    private User $admin;

    private User $changeManager;

    /** @var Collection<int, Application> */
    private Collection $applications;

    /** @var Collection<int, ReleaseTeamMember> */
    private Collection $releaseMembers;

    private int $applicationCursor = 0;

    private int $releaseMemberCursor = 0;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoRfcSeeder dilewati: data demo tidak boleh dibuat di production.');

            return;
        }

        $this->purgeRfcData();
        $this->resolveAccounts();

        // Dibuat berurutan dari yang paling lama agar nomor RFC mengikuti
        // kronologi kejadian.
        $this->seedFailed();
        $this->seedClosed();
        $this->seedWaitingEvaluation();
        $this->seedCancelled();
        $this->seedLateInProgress();
        $this->seedInProgress();
        $this->seedApproved();
        $this->seedReturnedToAdmin();
        $this->seedUnderReviewWithRisk();
        $this->seedNeedsRevision();
        $this->seedRejected();
        $this->seedUnderReview();
        $this->seedSubmitted();
        $this->seedDraft();

        $this->command?->info('Demo RFC dibuat: 14 RFC mencakup 12 status, seluruhnya milik Pemohon.');
    }

    private function seedFailed(): void
    {
        $createdAt = $this->at(30, 8, 30);

        $rfc = $this->createRfc(
            'Penggantian Perangkat Switch Lantai 3 Gedung Diskominfo',
            RfcStatus::FAILED,
            $createdAt,
            [
                'reason_for_change' => 'Switch lantai 3 sering mengalami hang sehingga koneksi jaringan pada ruang pelayanan terganggu.',
                'description' => 'Penggantian switch 24 port dengan perangkat baru beserta penyesuaian konfigurasi VLAN.',
                'change_risk' => 'Risiko gangguan jaringan sementara selama proses pemindahan kabel dan konfigurasi ulang.',
                'impact_description' => 'Layanan jaringan pada lantai 3 terganggu maksimal 2 jam selama pengerjaan.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(29, 9, 30), TicketPriority::HIGH, ServiceCategory::NETWORK, 'Perangkat pengganti sudah tersedia di gudang TIK.');
        $this->recordRiskAssessment($rfc, $this->at(29, 14, 0), 'Sedang', 'Sedang', 'Tinggi', 'Normal Change', 'Perubahan menyentuh perangkat jaringan inti lantai 3.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(28, 10, 0), 'Disetujui, dilaksanakan di luar jam layanan.');

        $member = $this->releaseMember('Tim Rilis Infrastruktur Jaringan');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(28, 10, 5),
            'Lakukan penggantian switch lantai 3 dan pastikan seluruh VLAN berjalan normal setelah pengerjaan.',
            1,
            $this->at(10, 0, 0)->toDateString(),
        );

        $this->createRiskAnalysis($task, $member, $this->at(27, 9, 0));
        $this->addTaskUpdate($task, $member, 'estimate_set', $this->at(27, 8, 30), ['new_estimated_days' => 1]);
        $this->addTaskUpdate($task, $member, 'risk_analysis_submitted', $this->at(27, 9, 5));

        $startedAt = $this->at(10, 9, 0);
        $finishedAt = $this->at(9, 16, 20);

        $task->update([
            'scheduled_start' => $startedAt,
            'scheduled_end' => $this->at(9, 17, 0),
            'execution_status' => 'rolled_back',
            'executed_at' => $finishedAt,
            'execution_notes' => 'Switch pengganti tidak kompatibel dengan modul uplink yang terpasang.',
        ]);

        $this->addTaskUpdate($task, $member, 'started', $startedAt);
        $this->addTaskUpdate($task, $member, 'blocked', $this->at(9, 13, 0), ['note' => 'Modul uplink pada switch baru tidak dikenali oleh perangkat lama.']);
        $this->addTaskUpdate($task, $member, 'progress', $finishedAt, ['note' => 'Pengerjaan dihentikan dan perangkat lama dipasang kembali untuk menjaga layanan.']);

        $this->logTransition($rfc, $member->user, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $startedAt);

        $failureNotes = 'Switch baru tidak dapat melakukan negosiasi uplink dengan perangkat core sehingga jaringan tidak stabil.';
        $followUpNotes = 'Perlu pengadaan modul uplink yang sesuai sebelum penjadwalan ulang.';

        ChangeRequestImplementation::create([
            'change_request_id' => $rfc->id,
            'recorded_by_id' => $member->user_id,
            'implementation_result' => ImplementationResult::FAILED->value,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'executor_name' => $member->name,
            'report_source' => 'Berita acara pelaksanaan Tim Rilis',
            'summary' => 'Penggantian switch dilaksanakan namun gagal karena perangkat baru tidak kompatibel dengan modul uplink.',
            'test_results' => 'Uji konektivitas gagal: 12 dari 24 port tidak dapat mengakses VLAN layanan.',
            'failure_notes' => $failureNotes,
            'follow_up_action' => FollowUpAction::RESCHEDULE->value,
            'follow_up_notes' => $followUpNotes,
        ]);

        $this->logTransition($rfc, $member->user, RfcStatus::IN_PROGRESS, RfcStatus::FAILED, $finishedAt, null, [
            'event' => 'implementation_recorded',
            'implementation_result' => ImplementationResult::FAILED->value,
            'executor_name' => $member->name,
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => $finishedAt->toDateTimeString(),
            'report_source' => 'Berita acara pelaksanaan Tim Rilis',
            'summary' => 'Penggantian switch dilaksanakan namun gagal karena perangkat baru tidak kompatibel dengan modul uplink.',
            'test_results' => 'Uji konektivitas gagal: 12 dari 24 port tidak dapat mengakses VLAN layanan.',
            'failure_notes' => $failureNotes,
            'follow_up_action' => FollowUpAction::RESCHEDULE->value,
            'follow_up_notes' => $followUpNotes,
            'evidence_count' => 0,
            'evidence_files' => [],
        ]);
    }

    private function seedClosed(): void
    {
        $createdAt = $this->at(27, 8, 0);

        $rfc = $this->createRfc(
            'Perbaikan Bug Validasi Formulir Pengajuan Layanan',
            RfcStatus::CLOSED,
            $createdAt,
            [
                'reason_for_change' => 'Formulir pengajuan menerima nomor telepon dengan format tidak valid sehingga data pemohon tidak dapat dihubungi.',
                'description' => 'Perbaikan aturan validasi nomor telepon dan penambahan pesan kesalahan yang lebih jelas pada formulir pengajuan.',
                'change_risk' => 'Risiko sangat rendah karena perubahan hanya pada lapisan validasi formulir.',
                'impact_description' => 'Pemohon akan menerima pesan validasi yang lebih jelas saat mengisi nomor telepon.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(26, 9, 0), TicketPriority::MEDIUM, ServiceCategory::APPLICATION, 'Bug sudah direproduksi pada lingkungan staging.');
        $this->recordRiskAssessment($rfc, $this->at(26, 10, 30), 'Rendah', 'Rendah', 'Normal', 'Standard Change', 'Perbaikan terbatas pada validasi masukan formulir.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(25, 9, 0), 'Disetujui sebagai perubahan standar.');

        $member = $this->releaseMember('Tim Rilis Aplikasi');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(25, 9, 5),
            'Perbaiki validasi nomor telepon pada formulir pengajuan dan tambahkan pengujian regresi.',
            1,
            $this->at(12, 0, 0)->toDateString(),
        );

        $this->createRiskAnalysis(
            $task,
            $member,
            $this->at(24, 9, 0),
            'Uji ulang formulir pengajuan pada lingkungan staging sebelum penerapan.',
            'Ubah aturan validasi dan pesan kesalahan pada komponen formulir pengajuan.',
            'Kembalikan berkas validasi ke versi sebelumnya melalui pipeline deployment.',
        );

        $this->addTaskUpdate($task, $member, 'estimate_set', $this->at(24, 8, 30), ['new_estimated_days' => 1]);
        $this->addTaskUpdate($task, $member, 'risk_analysis_submitted', $this->at(24, 9, 5));

        $startedAt = $this->at(12, 9, 0);
        $finishedAt = $this->at(11, 14, 10);

        $task->update([
            'scheduled_start' => $startedAt,
            'scheduled_end' => $this->at(11, 15, 0),
            'execution_status' => 'successful',
            'executed_at' => $finishedAt,
            'execution_notes' => 'Perbaikan diterapkan dan seluruh pengujian regresi lulus.',
        ]);

        $this->addTaskUpdate($task, $member, 'started', $startedAt);
        $this->addTaskUpdate($task, $member, 'progress', $finishedAt, ['note' => 'Validasi nomor telepon sudah diperbaiki dan diuji pada staging.']);

        $this->logTransition($rfc, $member->user, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $startedAt);

        $summary = 'Aturan validasi nomor telepon diperbarui dan pesan kesalahan formulir disesuaikan.';
        $testResults = 'Seluruh 18 skenario pengujian formulir lulus tanpa regresi.';

        ChangeRequestImplementation::create([
            'change_request_id' => $rfc->id,
            'recorded_by_id' => $member->user_id,
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'executor_name' => $member->name,
            'report_source' => 'Pipeline deployment aplikasi',
            'summary' => $summary,
            'test_results' => $testResults,
        ]);

        $this->logTransition($rfc, $member->user, RfcStatus::IN_PROGRESS, RfcStatus::IMPLEMENTED, $finishedAt, null, [
            'event' => 'implementation_recorded',
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'executor_name' => $member->name,
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => $finishedAt->toDateTimeString(),
            'report_source' => 'Pipeline deployment aplikasi',
            'summary' => $summary,
            'test_results' => $testResults,
            'evidence_count' => 0,
            'evidence_files' => [],
        ]);

        $evaluatedAt = $this->at(10, 10, 0);
        $evaluationNotes = 'Perbaikan berjalan sesuai rencana dan tidak menimbulkan gangguan layanan.';

        ChangeRequestImplementation::query()
            ->where('change_request_id', $rfc->id)
            ->update([
                'evaluation_notes' => $evaluationNotes,
                'evaluated_by_id' => $this->changeManager->id,
                'evaluated_at' => $evaluatedAt,
            ]);

        $this->logActivity($this->changeManager, $rfc, 'Evaluasi RFC dicatat.', [
            'event' => 'evaluation_recorded',
            'status' => RfcStatus::IMPLEMENTED->value,
            'old_status' => RfcStatus::IMPLEMENTED->value,
            'new_status' => RfcStatus::IMPLEMENTED->value,
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'summary' => $summary,
            'evaluation_notes' => $evaluationNotes,
            'evaluated_at' => $evaluatedAt->toDateTimeString(),
        ], $evaluatedAt);

        $closedAt = $this->at(9, 10, 30);
        $closureNotes = 'RFC ditutup karena perbaikan telah diterapkan dan tidak ada keluhan lanjutan dari pemohon.';

        $rfc->update([
            'closed_at' => $closedAt,
            'closed_by_id' => $this->changeManager->id,
            'closure_notes' => $closureNotes,
        ]);

        $this->logTransition($rfc, $this->changeManager, RfcStatus::IMPLEMENTED, RfcStatus::CLOSED, $closedAt, null, [
            'event' => 'closure_recorded',
            'outcome' => 'successful',
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'closure_notes' => $closureNotes,
            'evaluation_notes' => $evaluationNotes,
            'evaluated_at' => $evaluatedAt->toDateTimeString(),
        ]);

        $satisfaction = RfcSatisfaction::query()->create([
            'change_request_id' => $rfc->id,
            'user_id' => $this->pemohon->id,
            'rating' => 5,
            'comment' => 'Perbaikan cepat dan formulir sekarang memberi pesan yang jelas saat pengisian salah.',
        ]);

        $satisfaction->forceFill([
            'created_at' => $this->at(8, 11, 0),
            'updated_at' => $this->at(8, 11, 0),
        ])->saveQuietly();

        $this->logActivity($this->pemohon, $rfc, 'Penilaian kepuasan Pemohon dicatat.', [
            'event' => 'satisfaction_submitted',
            'rating' => 5,
        ], $this->at(8, 11, 0));
    }

    private function seedWaitingEvaluation(): void
    {
        $createdAt = $this->at(25, 9, 0);

        $rfc = $this->createRfc(
            'Pembaruan Sertifikat SSL Portal Layanan Publik',
            RfcStatus::IMPLEMENTED,
            $createdAt,
            [
                'reason_for_change' => 'Sertifikat SSL portal layanan publik akan kedaluwarsa dalam dua minggu sehingga akses aman terancam.',
                'description' => 'Pembaruan sertifikat SSL dan penyesuaian konfigurasi web server pada portal layanan publik.',
                'change_risk' => 'Risiko gangguan akses sementara apabila konfigurasi sertifikat tidak diterapkan dengan benar.',
                'impact_description' => 'Portal layanan publik tidak dapat diakses dalam waktu singkat saat pemasangan sertifikat.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(24, 9, 30), TicketPriority::HIGH, ServiceCategory::SECURITY, 'Sertifikat baru sudah diterbitkan oleh penyedia.');
        $this->recordRiskAssessment($rfc, $this->at(24, 13, 0), 'Sedang', 'Sedang', 'Tinggi', 'Normal Change', 'Perubahan menyentuh konfigurasi keamanan layanan publik.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(23, 10, 0), 'Disetujui, diterapkan di luar jam puncak layanan.');

        $member = $this->releaseMember('Tim Rilis Pusat Data');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(23, 10, 5),
            'Pasang sertifikat SSL baru pada portal layanan publik dan pastikan seluruh domain terlayani.',
            2,
            $this->at(8, 0, 0)->toDateString(),
        );

        $this->createRiskAnalysis($task, $member, $this->at(22, 9, 0));
        $this->addTaskUpdate($task, $member, 'estimate_set', $this->at(22, 8, 30), ['new_estimated_days' => 2]);
        $this->addTaskUpdate($task, $member, 'risk_analysis_submitted', $this->at(22, 9, 5));

        $startedAt = $this->at(8, 20, 0);
        $finishedAt = $this->at(6, 22, 30);

        $task->update([
            'scheduled_start' => $startedAt,
            'scheduled_end' => $this->at(6, 23, 0),
            'execution_status' => 'successful',
            'executed_at' => $finishedAt,
            'execution_notes' => 'Sertifikat terpasang dan portal dapat diakses kembali tanpa peringatan keamanan.',
        ]);

        $this->addTaskUpdate($task, $member, 'started', $startedAt);
        $this->addTaskUpdate($task, $member, 'progress', $this->at(7, 21, 0), ['note' => 'Sertifikat terpasang pada satu dari dua node web server.']);

        $this->logTransition($rfc, $member->user, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $startedAt);

        $summary = 'Sertifikat SSL baru terpasang pada kedua node web server portal layanan publik.';
        $testResults = 'Akses HTTPS berhasil pada seluruh domain dengan masa berlaku sertifikat 12 bulan.';

        ChangeRequestImplementation::create([
            'change_request_id' => $rfc->id,
            'recorded_by_id' => $member->user_id,
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'executor_name' => $member->name,
            'report_source' => 'Aplikasi pemantauan layanan',
            'summary' => $summary,
            'test_results' => $testResults,
        ]);

        $this->logTransition($rfc, $member->user, RfcStatus::IN_PROGRESS, RfcStatus::IMPLEMENTED, $finishedAt, null, [
            'event' => 'implementation_recorded',
            'implementation_result' => ImplementationResult::SUCCESS->value,
            'executor_name' => $member->name,
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => $finishedAt->toDateTimeString(),
            'report_source' => 'Aplikasi pemantauan layanan',
            'summary' => $summary,
            'test_results' => $testResults,
            'evidence_count' => 0,
            'evidence_files' => [],
        ]);
    }

    private function seedCancelled(): void
    {
        $createdAt = $this->at(21, 8, 20);
        $submittedAt = $this->at(21, 9, 0);
        $cancelledAt = $this->at(20, 14, 0);

        $rfc = $this->createRfc(
            'Perubahan Tampilan Portal Berita OPD',
            RfcStatus::CANCELLED,
            $createdAt,
            [
                'reason_for_change' => 'Tampilan portal berita OPD sudah tidak mengikuti identitas visual terbaru Pemerintah Kota.',
                'description' => 'Perubahan tata letak halaman beranda dan penyesuaian warna serta logo pada portal berita OPD.',
                'change_risk' => 'Risiko rendah karena perubahan hanya pada tampilan dan tidak menyentuh basis data.',
                'impact_description' => 'Hanya berdampak pada tampilan portal berita OPD.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->logTransition($rfc, $this->pemohon, RfcStatus::DRAFT, RfcStatus::SUBMITTED, $submittedAt);

        $cancellationNote = 'Perubahan dibatalkan karena kegiatan redesain portal akan digabung ke dalam pengadaan tahun anggaran berikutnya.';
        $this->logTransition($rfc, $this->admin, RfcStatus::SUBMITTED, RfcStatus::CANCELLED, $cancelledAt, $cancellationNote);
    }

    private function seedLateInProgress(): void
    {
        $createdAt = $this->at(20, 8, 0);

        $rfc = $this->createRfc(
            'Peningkatan Kapasitas Bandwidth Jaringan Kantor Pusat',
            RfcStatus::IN_PROGRESS,
            $createdAt,
            [
                'reason_for_change' => 'Kapasitas bandwidth saat ini tidak lagi memadai untuk layanan video conference dan aplikasi terpusat.',
                'description' => 'Peningkatan kapasitas bandwidth uplink kantor pusat dan penyesuaian kebijakan QoS pada router.',
                'change_risk' => 'Risiko gangguan jaringan sementara saat penerapan kebijakan QoS baru.',
                'impact_description' => 'Seluruh layanan jaringan kantor pusat berpotensi terganggu singkat pada saat penerapan.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(19, 9, 0), TicketPriority::HIGH, ServiceCategory::NETWORK, 'Penyedia jasa jaringan sudah menyiapkan jalur tambahan.');
        $this->recordRiskAssessment($rfc, $this->at(19, 11, 0), 'Sedang', 'Tinggi', 'Tinggi', 'Normal Change', 'Perubahan menyentuh router utama kantor pusat.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(18, 9, 0), 'Disetujui, penerapan dilakukan bertahap pada malam hari.');

        $member = $this->releaseMember('Tim Rilis Infrastruktur Jaringan');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(18, 9, 5),
            'Terapkan kebijakan QoS baru dan verifikasi kapasitas uplink kantor pusat.',
            2,
            $this->at(5, 0, 0)->toDateString(),
        );

        $this->createRiskAnalysis($task, $member, $this->at(17, 9, 0));
        $this->addTaskUpdate($task, $member, 'estimate_set', $this->at(18, 8, 30), ['new_estimated_days' => 2]);
        $this->addTaskUpdate($task, $member, 'risk_analysis_submitted', $this->at(17, 9, 5));

        $startedAt = $this->at(5, 8, 0);

        $task->update([
            'scheduled_start' => $startedAt,
            'scheduled_end' => $this->at(1, 17, 0),
            'execution_status' => 'in_progress',
            'execution_notes' => 'Penerapan QoS tahap pertama selesai, verifikasi masih berlangsung.',
        ]);

        $this->addTaskUpdate($task, $member, 'started', $startedAt);
        $this->addTaskUpdate($task, $member, 'blocked', $this->at(3, 15, 0), ['note' => 'Penyedia jasa jaringan terlambat mengaktifkan jalur tambahan.']);
        $this->addTaskUpdate($task, $member, 'progress', $this->at(2, 10, 0), ['note' => 'Jalur tambahan aktif dan uji beban sedang dilakukan.']);

        $this->logTransition($rfc, $member->user, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $startedAt);
    }

    private function seedInProgress(): void
    {
        $createdAt = $this->at(16, 8, 10);

        $rfc = $this->createRfc(
            'Optimasi Kinerja Basis Data Aplikasi Keuangan',
            RfcStatus::IN_PROGRESS,
            $createdAt,
            [
                'reason_for_change' => 'Waktu respons laporan keuangan meningkat tajam pada akhir bulan sehingga menghambat pelaporan OPD.',
                'description' => 'Penambahan indeks, pembaruan statistik, dan penyesuaian query laporan bulanan pada basis data aplikasi keuangan.',
                'change_risk' => 'Risiko kunci tabel sementara saat pembuatan indeks baru.',
                'impact_description' => 'Aplikasi keuangan berpotensi melambat sementara selama proses optimasi.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(15, 9, 10), TicketPriority::URGENT, ServiceCategory::DATABASE, 'Optimasi dibutuhkan sebelum penutupan laporan bulan ini.');
        $this->recordRiskAssessment($rfc, $this->at(15, 13, 0), 'Sedang', 'Sedang', 'Tinggi', 'Normal Change', 'Perubahan pada objek basis data produksi.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(14, 9, 0), 'Disetujui, optimasi dilakukan di luar jam kerja.');

        $member = $this->releaseMember('Tim Rilis Pusat Data');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(14, 9, 5),
            'Lakukan optimasi indeks dan query laporan bulanan, lalu ukur waktu respons sebelum dan sesudah perubahan.',
            3,
            $this->at(2, 0, 0)->toDateString(),
        );

        $this->createRiskAnalysis($task, $member, $this->at(13, 9, 0));
        $this->addTaskUpdate($task, $member, 'estimate_set', $this->at(14, 8, 30), ['new_estimated_days' => 3]);
        $this->addTaskUpdate($task, $member, 'risk_analysis_submitted', $this->at(13, 9, 5));

        $startedAt = $this->at(2, 8, 0);

        $task->update([
            'scheduled_start' => $startedAt,
            'scheduled_end' => now()->addDay()->setTime(17, 0),
            'execution_status' => 'in_progress',
            'execution_notes' => 'Indeks utama sudah dibuat, pengukuran query laporan berjalan.',
        ]);

        $this->addTaskUpdate($task, $member, 'started', $startedAt);
        $this->addTaskUpdate($task, $member, 'progress', $this->at(1, 16, 0), ['note' => 'Waktu respons laporan bulanan turun dari 42 detik menjadi 11 detik.']);

        $this->logTransition($rfc, $member->user, RfcStatus::APPROVED, RfcStatus::IN_PROGRESS, $startedAt);
    }

    private function seedApproved(): void
    {
        $createdAt = $this->at(14, 8, 30);

        $rfc = $this->createRfc(
            'Penyesuaian Hak Akses Operator Aplikasi Persuratan',
            RfcStatus::APPROVED,
            $createdAt,
            [
                'reason_for_change' => 'Operator baru pada bagian persuratan belum memiliki hak akses yang sesuai dengan tugasnya.',
                'description' => 'Penambahan peran operator persuratan dan penyesuaian matrix hak akses pada aplikasi persuratan.',
                'change_risk' => 'Risiko kesalahan pemberian akses apabila matriks peran tidak sesuai.',
                'impact_description' => 'Hanya berdampak pada pengguna internal bagian persuratan.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(13, 9, 0), TicketPriority::MEDIUM, ServiceCategory::APPLICATION, 'Daftar operator baru sudah diverifikasi oleh Sekretariat.');
        $this->recordRiskAssessment($rfc, $this->at(12, 10, 0), 'Rendah', 'Rendah', 'Normal', 'Normal Change', 'Perubahan pada konfigurasi peran pengguna.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::APPROVED, DecisionStatus::APPROVED, $this->at(12, 14, 0), 'Disetujui, diterapkan setelah jam layanan.');

        $member = $this->releaseMember('Tim Rilis Aplikasi');

        $task = $this->createReleaseTask(
            $rfc,
            $member,
            $this->at(12, 14, 5),
            'Terapkan matriks hak akses operator persuratan dan uji akses menggunakan akun uji.',
        );

        $this->logActivity($this->changeManager, $rfc, 'Disposisi ke Tim Rilis', [
            'event' => 'release_disposition',
            'recipient_names' => [$member->name],
            'recipient_count' => 1,
            'instruction' => $task->instruction,
            'release_task_ids' => [$task->id],
            'assigned_by_id' => $this->changeManager->id,
        ], $this->at(12, 14, 6));
    }

    private function seedReturnedToAdmin(): void
    {
        $createdAt = $this->at(12, 8, 0);

        $rfc = $this->createRfc(
            'Penambahan Kolom NIK pada Formulir Layanan',
            RfcStatus::RETURNED_TO_ADMIN,
            $createdAt,
            [
                'reason_for_change' => 'Formulir layanan belum memuat NIK sehingga verifikasi pemohon dilakukan manual.',
                'description' => 'Penambahan kolom NIK beserta validasi 16 digit pada formulir layanan.',
                'change_risk' => 'Risiko kebocoran data pribadi apabila kolom NIK ditampilkan tanpa pembatasan akses.',
                'impact_description' => 'Berdampak pada seluruh pemohon layanan yang mengisi formulir.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(11, 9, 0), TicketPriority::MEDIUM, ServiceCategory::APPLICATION, 'Perlu pengecekan ulang pada rancangan formulir.');

        $returnNote = 'Kelengkapan dokumen belum memadai: rancangan formulir belum mencantumkan persetujuan penggunaan data pribadi. Mohon dilengkapi sebelum diteruskan ke Change Manager.';

        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::RETURNED_TO_ADMIN, DecisionStatus::RETURNED_TO_ADMIN, $this->at(10, 10, 0), $returnNote);
    }

    private function seedUnderReviewWithRisk(): void
    {
        $createdAt = $this->at(10, 8, 40);

        $rfc = $this->createRfc(
            'Migrasi Basis Data Aplikasi Kearsipan ke Server Baru',
            RfcStatus::TICKET_CREATED,
            $createdAt,
            [
                'reason_for_change' => 'Server basis data aplikasi kearsipan sudah mendekati batas kapasitas penyimpanan.',
                'description' => 'Migrasi basis data aplikasi kearsipan ke server baru beserta penyesuaian konfigurasi koneksi.',
                'change_risk' => 'Risiko kehilangan data apabila proses migrasi tidak melalui prosedur pencadangan.',
                'impact_description' => 'Aplikasi kearsipan tidak dapat digunakan selama proses migrasi berlangsung.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(9, 9, 30), TicketPriority::HIGH, ServiceCategory::DATABASE, 'Rencana migrasi sudah dilampirkan oleh pemohon.');
        $this->recordRiskAssessment($rfc, $this->at(8, 10, 0), 'Sedang', 'Tinggi', 'Normal', 'Normal Change', 'Migrasi menyentuh basis data layanan kearsipan yang aktif digunakan.');
    }

    private function seedNeedsRevision(): void
    {
        $createdAt = $this->at(9, 8, 30);
        $submittedAt = $this->at(9, 10, 0);
        $revisionAt = $this->at(8, 9, 30);

        $revisionNote = 'Lampiran analisis dampak belum dilengkapi dan uraian risiko masih terlalu umum. Mohon lengkapi sebelum diajukan kembali.';

        $rfc = $this->createRfc(
            'Perubahan Alur Persetujuan Surat Perjalanan Dinas',
            RfcStatus::NEEDS_REVISION,
            $createdAt,
            [
                'reason_for_change' => 'Alur persetujuan surat perjalanan dinas masih berjenjang manual sehingga memakan waktu lebih dari tiga hari.',
                'description' => 'Penyederhanaan alur persetujuan surat perjalanan dinas pada aplikasi persuratan.',
                'change_risk' => 'Risiko persetujuan ganda apabila aturan delegasi tidak dirancang dengan tepat.',
                'impact_description' => 'Berdampak pada seluruh pegawai yang mengajukan perjalanan dinas.',
                'rejection_note' => $revisionNote,
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->logTransition($rfc, $this->pemohon, RfcStatus::DRAFT, RfcStatus::SUBMITTED, $submittedAt);
        $this->logTransition($rfc, $this->admin, RfcStatus::SUBMITTED, RfcStatus::NEEDS_REVISION, $revisionAt, $revisionNote);
    }

    private function seedRejected(): void
    {
        $createdAt = $this->at(8, 8, 0);

        $rejectionReason = 'Integrasi ditolak karena belum ada kajian keamanan dan perjanjian pengolahan data dengan pihak ketiga. Silakan ajukan kembali setelah dokumen keamanan dilengkapi.';

        $rfc = $this->createRfc(
            'Integrasi Aplikasi Pihak Ketiga Tanpa Kajian Keamanan',
            RfcStatus::REJECTED,
            $createdAt,
            [
                'reason_for_change' => 'Pemohon membutuhkan integrasi data dengan aplikasi mitra untuk mempercepat pelayanan.',
                'description' => 'Integrasi pertukaran data antara aplikasi layanan OPD dan aplikasi mitra melalui API.',
                'change_risk' => 'Risiko kebocoran data pribadi dan akses tidak sah karena belum ada kajian keamanan.',
                'impact_description' => 'Berpotensi berdampak pada data pribadi pemohon layanan.',
                'rejection_note' => $rejectionReason,
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(7, 9, 0), TicketPriority::HIGH, ServiceCategory::SECURITY, 'Dokumen keamanan belum dilampirkan.');
        $this->recordRiskAssessment($rfc, $this->at(7, 13, 0), 'Tinggi', 'Tinggi', 'Tinggi', 'Emergency Change', 'Integrasi melibatkan pertukaran data pribadi tanpa kendali keamanan yang memadai.');
        $this->recordDecision($rfc, RfcStatus::TICKET_CREATED, RfcStatus::REJECTED, DecisionStatus::REJECTED, $this->at(6, 10, 0), $rejectionReason);
    }

    private function seedUnderReview(): void
    {
        $createdAt = $this->at(6, 8, 50);

        $rfc = $this->createRfc(
            'Pembaruan Modul Laporan Aplikasi Perizinan',
            RfcStatus::TICKET_CREATED,
            $createdAt,
            [
                'reason_for_change' => 'Modul laporan belum dapat menampilkan rekap perizinan per kecamatan sesuai kebutuhan pimpinan.',
                'description' => 'Pembaruan modul laporan aplikasi perizinan dengan filter kecamatan dan ekspor rekap bulanan.',
                'change_risk' => 'Risiko kesalahan angka rekap apabila query agregasi tidak diuji dengan data nyata.',
                'impact_description' => 'Berdampak pada proses penyusunan laporan pimpinan.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->recordTicket($rfc, $this->at(5, 10, 30), TicketPriority::HIGH, ServiceCategory::APPLICATION, 'Dokumen lengkap, menunggu analisis risiko Change Manager.');
    }

    private function seedSubmitted(): void
    {
        $createdAt = $this->at(3, 8, 40);
        $submittedAt = $this->at(3, 13, 0);

        $rfc = $this->createRfc(
            'Integrasi Data Pegawai dengan Aplikasi Kepegawaian',
            RfcStatus::SUBMITTED,
            $createdAt,
            [
                'reason_for_change' => 'Data pegawai saat ini diperbarui manual pada dua aplikasi sehingga sering tidak sinkron.',
                'description' => 'Integrasi data pegawai antara aplikasi kepegawaian dan aplikasi layanan internal melalui layanan API.',
                'change_risk' => 'Risiko data tidak sinkron sementara selama proses integrasi awal.',
                'impact_description' => 'Berdampak pada proses administrasi kepegawaian di lingkungan Diskominfo.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
        $this->logTransition($rfc, $this->pemohon, RfcStatus::DRAFT, RfcStatus::SUBMITTED, $submittedAt);
    }

    private function seedDraft(): void
    {
        $createdAt = $this->at(2, 9, 15);

        $rfc = $this->createRfc(
            'Penyesuaian Template Surat Tugas pada Aplikasi SIPERKA',
            RfcStatus::DRAFT,
            $createdAt,
            [
                'reason_for_change' => 'Template surat tugas yang berjalan saat ini belum memuat kolom lokasi penugasan sehingga operator sering melengkapi secara manual.',
                'description' => 'Penambahan kolom lokasi penugasan dan penyesuaian tata letak template surat tugas pada aplikasi SIPERKA.',
                'change_risk' => 'Risiko rendah karena perubahan hanya menyentuh template cetak dan tidak mengubah data transaksi.',
                'impact_description' => 'Hanya berdampak pada proses cetak surat tugas di lingkungan Sekretariat Diskominfo.',
            ],
        );

        $this->recordDraft($rfc, $createdAt);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRfc(string $title, RfcStatus $status, Carbon $createdAt, array $overrides = []): ChangeRequest
    {
        $rfc = ChangeRequest::create(array_merge([
            'rfc_number' => $status === RfcStatus::DRAFT ? null : RfcNumberGenerator::generate($this->opdCode()),
            'title' => $title,
            'application_id' => $this->nextApplication()->id,
            'submitted_by_id' => $this->pemohon->id,
            'created_by_id' => $this->pemohon->id,
            'is_physical_letter' => false,
            'reason_for_change' => 'Kebutuhan penyesuaian layanan agar tetap sesuai dengan proses bisnis OPD dan ketentuan yang berlaku.',
            'description' => 'Usulan perubahan disiapkan sebagai bagian dari pemeliharaan layanan TIK di lingkungan Pemerintah Kota.',
            'legal_basis' => 'Peraturan Wali Kota tentang Penyelenggaraan Sistem Pemerintahan Berbasis Elektronik.',
            'change_risk' => 'Potensi gangguan layanan dalam waktu singkat saat perubahan diterapkan. Mitigasi disiapkan oleh Tim Rilis.',
            'impact_description' => 'Perubahan berdampak pada pengguna layanan internal OPD dan perlu diuji sebelum diterapkan penuh.',
            'head_of_department_approved' => true,
            'head_of_department_approved_at' => $createdAt->copy()->subDays(1),
            'change_type' => 'normal',
            'status' => $status->value,
            'target_implementation_date' => $createdAt->copy()->addDays(10),
        ], $overrides));

        $rfc->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $rfc;
    }

    private function recordDraft(ChangeRequest $rfc, Carbon $at): void
    {
        $this->logActivity($this->pemohon, $rfc, 'Draft RFC disimpan', [
            'old_status' => null,
            'new_status' => RfcStatus::DRAFT->value,
            'note' => null,
        ], $at);
    }

    private function recordTicket(
        ChangeRequest $rfc,
        Carbon $at,
        TicketPriority $priority,
        ServiceCategory $category,
        string $adminNote,
    ): void {
        $ticketNumber = TicketNumberGenerator::generate();

        $rfc->update([
            'ticket_number' => $ticketNumber,
            'priority' => $priority->value,
            'category' => $category->value,
            'admin_note' => $adminNote,
            'ticket_created_at' => $at,
            'ticket_created_by_id' => $this->admin->id,
        ]);

        $this->logTransition($rfc, $this->admin, RfcStatus::DRAFT, RfcStatus::TICKET_CREATED, $at);

        $this->logActivity($this->admin, $rfc, "Tiket Change {$ticketNumber} dibuat dan diteruskan ke Change Manager.", [
            'event' => 'ticket_created',
            'ticket_number' => $ticketNumber,
            'priority' => $priority->value,
            'category' => $category->value,
            'admin_note' => $adminNote,
            'checklist' => [
                'Kelengkapan dokumen' => true,
                'Dasar hukum' => true,
                'Persetujuan atasan' => true,
            ],
        ], $at->copy()->addMinutes(2));
    }

    private function recordRiskAssessment(
        ChangeRequest $rfc,
        Carbon $at,
        string $riskLevel,
        string $impactLevel,
        string $urgencyLevel,
        string $classification,
        string $analysisNotes,
    ): void {
        [$criticality, $impact, $rollback] = match ($riskLevel) {
            'Tinggi' => [3, 3, 2],
            'Kritis' => [4, 4, 3],
            default => [2, 2, 1],
        };

        RiskAssessment::create([
            'change_request_id' => $rfc->id,
            'assessed_by_id' => $this->changeManager->id,
            'system_criticality_score' => $criticality,
            'impact_scope_score' => $impact,
            'rollback_readiness_score' => $rollback,
            'total_score' => $criticality + $impact + $rollback,
            'risk_level' => $riskLevel,
            'impact_level' => $impactLevel,
            'urgency_level' => $urgencyLevel,
            'classification' => $classification,
            'analysis_notes' => $analysisNotes,
            'mitigation_plan' => 'Uji perubahan pada lingkungan staging, siapkan pencadangan sebelum penerapan, dan terapkan di luar jam layanan.',
            'rollback_plan' => 'Kembalikan konfigurasi dan basis data ke kondisi sebelum perubahan menggunakan cadangan terakhir.',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function recordDecision(
        ChangeRequest $rfc,
        RfcStatus $oldStatus,
        RfcStatus $targetStatus,
        DecisionStatus $decisionStatus,
        Carbon $at,
        string $reason,
    ): void {
        Decision::create([
            'change_request_id' => $rfc->id,
            'authority_id' => null,
            'recorded_by_id' => $this->changeManager->id,
            'snapshot_authority_name' => $this->changeManager->name,
            'snapshot_authority_nip' => $this->changeManager->nip,
            'snapshot_authority_position' => $this->changeManager->position ?: 'Change Manager',
            'decision_status' => $decisionStatus->value,
            'decision_date' => $at,
            'reason' => $reason,
            'is_replacement' => false,
            'created_at' => $at,
        ]);

        $this->logTransition($rfc, $this->changeManager, $oldStatus, $targetStatus, $at, $reason);
    }

    private function createReleaseTask(
        ChangeRequest $rfc,
        ReleaseTeamMember $member,
        Carbon $assignedAt,
        string $instruction,
        ?int $estimatedDays = null,
        ?string $plannedStartOn = null,
    ): ReleaseTask {
        $task = $rfc->releaseTasks()->create([
            'release_team_member_id' => $member->id,
            'assigned_by_id' => $this->changeManager->id,
            'instruction' => $instruction,
            'estimated_days' => $estimatedDays,
            'planned_start_on' => $plannedStartOn,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'execution_status' => 'pending',
            'executed_at' => null,
            'execution_notes' => null,
        ]);

        $task->forceFill([
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ])->saveQuietly();

        return $task;
    }

    private function createRiskAnalysis(
        ReleaseTask $task,
        ReleaseTeamMember $member,
        Carbon $at,
        string $activityPlan = 'Lakukan penerapan perubahan pada lingkungan produksi di luar jam layanan dan pantau hasilnya.',
        string $changePlan = 'Terapkan konfigurasi baru secara bertahap dan verifikasi setiap langkah sesuai prosedur.',
        string $rollbackMechanism = 'Kembalikan konfigurasi ke versi sebelumnya menggunakan prosedur rollback yang telah diuji.',
    ): ReleaseTaskRiskAnalysis {
        $analysis = $task->riskAnalysis()->create([
            'submitted_by_id' => $member->user_id,
            'activity_plan' => $activityPlan,
            'change_plan' => $changePlan,
            'rollback_mechanism' => $rollbackMechanism,
        ]);

        $analysis->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->saveQuietly();

        return $analysis;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addTaskUpdate(
        ReleaseTask $task,
        ReleaseTeamMember $member,
        string $type,
        Carbon $at,
        array $attributes = [],
    ): ReleaseTaskUpdate {
        $update = $task->updates()->create(array_merge([
            'change_request_id' => $task->change_request_id,
            'user_id' => $member->user_id,
            'type' => $type,
        ], $attributes));

        $update->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->saveQuietly();

        return $update;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logTransition(
        ChangeRequest $rfc,
        ?User $actor,
        ?RfcStatus $oldStatus,
        RfcStatus $newStatus,
        Carbon $at,
        ?string $note = null,
        array $properties = [],
    ): void {
        $description = $newStatus === RfcStatus::TICKET_CREATED
            && in_array($oldStatus, [RfcStatus::DRAFT, RfcStatus::NEEDS_REVISION], true)
                ? 'RFC diajukan dan diteruskan ke Change Manager'
                : 'Status RFC diubah dari '.($oldStatus?->label() ?? 'Draft').' menjadi '.$newStatus->label().'.';

        $this->logActivity($actor, $rfc, $description, array_merge([
            'old_status' => $oldStatus?->value,
            'new_status' => $newStatus->value,
            'note' => $note,
        ], $properties), $at);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(?User $actor, ChangeRequest $rfc, string $description, array $properties, Carbon $at): void
    {
        Activity::create([
            'log_name' => 'rfc',
            'description' => $description,
            'subject_type' => $rfc->getMorphClass(),
            'subject_id' => $rfc->getKey(),
            'causer_type' => $actor?->getMorphClass(),
            'causer_id' => $actor?->getKey(),
            'properties' => $properties,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function purgeRfcData(): void
    {
        $this->deleteStoredRfcFiles();

        DB::transaction(function (): void {
            DB::table('evidences')->delete();
            DB::table('release_task_risk_analyses')->delete();
            DB::table('release_task_updates')->delete();
            DB::table('release_tasks')->delete();
            DB::table('authorization_documents')->delete();
            DB::table('decisions')->delete();
            DB::table('change_request_implementations')->delete();
            DB::table('risk_assessments')->delete();
            DB::table('comments')->delete();
            DB::table('rfc_satisfactions')->delete();
            DB::table('rfc_reminder_logs')->delete();
            DB::table('change_request_attachments')->delete();

            ChangeRequest::withTrashed()->forceDelete();

            Activity::query()
                ->where('subject_type', (new ChangeRequest)->getMorphClass())
                ->delete();

            DB::table('notifications')->delete();
        });
    }

    private function deleteStoredRfcFiles(): void
    {
        $paths = DB::table('evidences')
            ->pluck('file_path')
            ->merge(DB::table('change_request_attachments')->pluck('path'))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    private function resolveAccounts(): void
    {
        $this->pemohon = User::role('Pemohon')->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Akun Pemohon aktif tidak ditemukan.');

        $this->admin = User::role('Admin')->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Akun Admin aktif tidak ditemukan.');

        $this->changeManager = User::role('Change Manager')->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Akun Change Manager aktif tidak ditemukan.');

        $this->applications = Application::query()->orderBy('id')->get()->values();

        if ($this->applications->isEmpty()) {
            throw new RuntimeException('Belum ada aplikasi yang dapat dipakai sebagai target RFC.');
        }

        $this->releaseMembers = ReleaseTeamMember::query()
            ->active()
            ->linked()
            ->with('user')
            ->get()
            ->filter(fn (ReleaseTeamMember $member): bool => $member->isEligibleForAssignment())
            ->sortBy(fn (ReleaseTeamMember $member): int => match ($member->name) {
                'Tim Rilis Aplikasi' => 0,
                'Tim Rilis Pusat Data' => 1,
                'Tim Rilis Infrastruktur Jaringan' => 2,
                default => 3,
            })
            ->values();

        if ($this->releaseMembers->isEmpty()) {
            throw new RuntimeException('Belum ada anggota Tim Rilis aktif yang tertaut ke akun dengan role Tim Rilis.');
        }
    }

    private function opdCode(): string
    {
        $code = $this->pemohon->opd?->code;

        if (blank($code)) {
            throw new RuntimeException('Akun Pemohon belum memiliki OPD sehingga nomor RFC tidak dapat diterbitkan.');
        }

        return (string) $code;
    }

    private function nextApplication(): Application
    {
        $application = $this->applications[$this->applicationCursor % $this->applications->count()];
        $this->applicationCursor++;

        return $application;
    }

    private function releaseMember(string $preferredName): ReleaseTeamMember
    {
        $member = $this->releaseMembers->firstWhere('name', $preferredName);

        if ($member instanceof ReleaseTeamMember) {
            return $member;
        }

        $fallback = $this->releaseMembers[$this->releaseMemberCursor % $this->releaseMembers->count()];
        $this->releaseMemberCursor++;

        return $fallback;
    }

    private function at(int $daysAgo, int $hour, int $minute = 0): Carbon
    {
        return now()->subDays($daysAgo)->setTime($hour, $minute);
    }
}
