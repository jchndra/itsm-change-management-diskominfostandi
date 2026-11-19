<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai tugas aktif Tim Rilis diperluas dengan "cancelled" agar penolakan
     * RFC oleh Change Manager saat pekerjaan berjalan dapat membatalkan
     * tugas tanpa menyamarkannya sebagai hasil pelaksanaan.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE release_tasks MODIFY execution_status ENUM('pending', 'in_progress', 'successful', 'rolled_back', 'cancelled') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        Schema::table('release_tasks', function (Blueprint $table): void {
            $table->enum('execution_status', ['pending', 'in_progress', 'successful', 'rolled_back', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('release_tasks')
            ->where('execution_status', 'cancelled')
            ->update(['execution_status' => 'rolled_back']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE release_tasks MODIFY execution_status ENUM('pending', 'in_progress', 'successful', 'rolled_back') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        Schema::table('release_tasks', function (Blueprint $table): void {
            $table->enum('execution_status', ['pending', 'in_progress', 'successful', 'rolled_back'])
                ->default('pending')
                ->change();
        });
    }
};
