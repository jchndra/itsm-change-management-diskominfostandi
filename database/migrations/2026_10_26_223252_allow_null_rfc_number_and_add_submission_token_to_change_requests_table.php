<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft tidak lagi menyandang nomor resmi. submission_token mencegah
     * double-submit dari form yang sama.
     */
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            // Nomor resmi hanya diberikan saat RFC berhasil diajukan.
            $table->string('rfc_number', 30)->nullable()->change();
            $table->string('submission_token', 64)->nullable()->unique();
        });
    }

    /**
     * Rollback hanya aman jika tidak ada draft tanpa nomor. Draft yang sudah
     * ada tidak boleh diberi nomor fiktif hanya agar kolom bisa NOT NULL.
     * Pemeriksaan dilakukan sebelum DDL agar tidak terjadi rollback parsial.
     */
    public function down(): void
    {
        if (Schema::hasTable('change_requests')
            && DB::table('change_requests')->whereNull('rfc_number')->exists()) {
            throw new RuntimeException(
                'Rollback dibatalkan: masih ada draft tanpa nomor RFC. '
                .'Gunakan pendekatan forward-only; lihat database/migrations/README.md.'
            );
        }

        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });

        Schema::table('change_requests', function (Blueprint $table) {
            $table->string('rfc_number', 30)->nullable(false)->change();
        });
    }
};
