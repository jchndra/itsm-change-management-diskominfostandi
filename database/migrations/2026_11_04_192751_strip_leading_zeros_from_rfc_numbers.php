<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor RFC kini ditampilkan tanpa padding nol di depan (RFC-2026-20,
     * bukan RFC-2026-0020). Nomor lama dinormalisasi agar konsisten, dan
     * perubahan dicatat di activity log untuk keperluan audit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('change_requests')) {
            return;
        }

        $now = now();
        $subjectType = 'App\\Models\\ChangeRequest';
        $hasActivityLog = Schema::hasTable('activity_log');

        DB::table('change_requests')
            ->whereNotNull('rfc_number')
            ->orderBy('id')
            ->get(['id', 'rfc_number'])
            ->each(function (object $rfc) use ($now, $subjectType, $hasActivityLog): void {
                $normalized = $this->normalize($rfc->rfc_number);

                if ($normalized === $rfc->rfc_number) {
                    return;
                }

                if ($hasActivityLog) {
                    DB::table('activity_log')->insert([
                        'log_name' => 'rfc',
                        'description' => 'Nomor RFC dinormalisasi tanpa padding nol di depan.',
                        'subject_type' => $subjectType,
                        'subject_id' => $rfc->id,
                        'event' => 'rfc_number_normalized',
                        'causer_type' => null,
                        'causer_id' => null,
                        'attribute_changes' => null,
                        'properties' => json_encode([
                            'event' => 'rfc_number_normalized',
                            'old_number' => $rfc->rfc_number,
                            'new_number' => $normalized,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('change_requests')
                    ->where('id', $rfc->id)
                    ->update(['rfc_number' => $normalized]);
            });
    }

    /**
     * Migrasi ini sengaja forward-only. Mengembalikan padding nol akan
     * menciptakan format nomor yang tidak lagi dipakai aplikasi.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi normalisasi nomor RFC bersifat forward-only dan tidak dapat di-rollback.'
        );
    }

    private function normalize(string $number): string
    {
        if (! preg_match('/^(RFC-\d{4})-(\d+)$/', $number, $matches)) {
            return $number;
        }

        return $matches[1].'-'.(int) $matches[2];
    }
};
