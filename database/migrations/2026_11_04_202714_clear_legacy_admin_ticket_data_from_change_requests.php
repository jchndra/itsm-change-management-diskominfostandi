<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor tiket berasal dari alur lama pembuatan tiket oleh Admin dan sudah
     * tidak dipakai pada alur Pemohon -> Change Manager -> Tim Rilis. Kolomnya
     * tetap dipertahankan untuk berjaga-jaga, tetapi datanya dikosongkan.
     */
    public function up(): void
    {
        if (! Schema::hasTable('change_requests')) {
            return;
        }

        DB::table('change_requests')
            ->where(function ($query): void {
                $query->whereNotNull('ticket_number')
                    ->orWhereNotNull('ticket_created_at')
                    ->orWhereNotNull('ticket_created_by_id');
            })
            ->update([
                'ticket_number' => null,
                'ticket_created_at' => null,
                'ticket_created_by_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Data tiket lama tidak dapat dipulihkan karena memang sengaja dihapus.
     */
    public function down(): void
    {
        // Sengaja tidak melakukan apa pun.
    }
};
