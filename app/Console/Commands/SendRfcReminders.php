<?php

namespace App\Console\Commands;

use App\Services\RfcReminderService;
use Illuminate\Console\Command;

class SendRfcReminders extends Command
{
    protected $signature = 'rfc:send-reminders {--dry-run}';

    protected $description = 'Kirim pengingat dan eskalasi otomatis untuk RFC yang menunggu tindakan.';

    public function handle(RfcReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $service->run($dryRun);

        $this->info($dryRun
            ? 'Simulasi pengingat RFC: tidak ada notifikasi yang dikirim dan tidak ada log yang ditulis.'
            : 'Pengingat RFC selesai diproses.');

        $labels = [
            RfcReminderService::KIND_PENDING_REVIEW => 'Menunggu review',
            RfcReminderService::KIND_APPROVED_UNASSIGNED => 'Disetujui tanpa disposisi',
            RfcReminderService::KIND_TASK_NOT_STARTED => 'Tugas belum dimulai',
            RfcReminderService::KIND_TASK_OVERDUE => 'Tugas melewati estimasi',
            RfcReminderService::KIND_WAITING_EVALUATION => 'Menunggu evaluasi',
        ];

        $rows = [];
        $total = 0;

        foreach ($labels as $kind => $label) {
            $count = (int) ($summary[$kind] ?? 0);
            $total += $count;
            $rows[] = [$label, $kind, $count];
        }

        $rows[] = ['Total', '', $total];

        $this->table(['Kategori', 'Jenis', 'Jumlah'], $rows);

        return self::SUCCESS;
    }
}
