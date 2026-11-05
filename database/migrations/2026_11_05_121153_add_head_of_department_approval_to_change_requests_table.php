<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->boolean('head_of_department_approved')
                ->default(false)
                ->after('change_risk')
                ->comment('Pernyataan pemohon bahwa perubahan telah disetujui Kepala Dinas');

            $table->timestamp('head_of_department_approved_at')
                ->nullable()
                ->after('head_of_department_approved')
                ->comment('Waktu pemohon menyatakan persetujuan Kepala Dinas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropColumn(['head_of_department_approved', 'head_of_department_approved_at']);
        });
    }
};
