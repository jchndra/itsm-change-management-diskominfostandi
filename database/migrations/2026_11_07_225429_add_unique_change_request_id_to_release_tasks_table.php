<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateCount = DB::table('release_tasks')
            ->select('change_request_id')
            ->groupBy('change_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateCount > 0) {
            throw new RuntimeException(
                "{$duplicateCount} RFC memiliki lebih dari satu tugas Tim Rilis. "
                .'Rapikan data release_tasks secara manual sebelum menjalankan migrasi ini.'
            );
        }

        Schema::table('release_tasks', function (Blueprint $table) {
            $table->unique('change_request_id', 'release_tasks_change_request_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_tasks', function (Blueprint $table) {
            $table->dropUnique('release_tasks_change_request_id_unique');
        });
    }
};
