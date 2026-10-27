<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft lama tidak boleh menyandang nomor resmi karena nomor hanya
     * diterbitkan saat pengajuan berhasil. Nomor lama dicatat di activity log
     * agar perubahan data tetap dapat diaudit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('change_requests') || ! Schema::hasTable('activity_log')) {
            return;
        }

        $now = now();
        $subjectType = 'App\\Models\\ChangeRequest';

        DB::table('change_requests')
            ->where('status', 'draft')
            ->whereNotNull('rfc_number')
            ->orderBy('id')
            ->get(['id', 'rfc_number'])
            ->each(function (object $rfc) use ($now, $subjectType): void {
                DB::table('activity_log')->insert([
                    'log_name' => 'rfc',
                    'description' => 'Nomor RFC draft dikosongkan karena draft belum pernah diajukan.',
                    'subject_type' => $subjectType,
                    'subject_id' => $rfc->id,
                    'event' => 'draft_number_cleared',
                    'causer_type' => null,
                    'causer_id' => null,
                    'attribute_changes' => null,
                    'properties' => json_encode([
                        'event' => 'draft_number_cleared',
                        'old_number' => $rfc->rfc_number,
                        'new_number' => null,
                        'reason' => 'Nomor RFC hanya diterbitkan saat pengajuan berhasil.',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('change_requests')
                    ->where('id', $rfc->id)
                    ->update(['rfc_number' => null]);
            });
    }

    /**
     * Migrasi ini sengaja forward-only. Nomor yang dikosongkan berasal dari
     * draft yang tidak pernah diajukan sehingga bukan nomor resmi. Memulihkan
     * nomor tersebut akan melanggar invariant "draft tanpa nomor" dan dapat
     * menimbulkan konflik dengan nomor resmi yang sudah terbit.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi normalisasi nomor draft bersifat forward-only dan tidak dapat di-rollback. '
            .'Lihat database/migrations/README.md untuk prosedur pemulihan.'
        );
    }
};
