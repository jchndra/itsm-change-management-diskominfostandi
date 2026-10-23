<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keputusan baru dicatat atas nama Change Manager dan tidak lagi
        // merujuk ke register Authority. Tipe kolom dan foreign key tetap.
        Schema::table('decisions', function (Blueprint $table) {
            $table->unsignedBigInteger('authority_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan menjadi NOT NULL secara otomatis.
        // Keputusan baru menyimpan authority_id null sehingga rollback akan
        // gagal atau memaksa perubahan data. Kembalikan manual setelah data
        // lama/baru dipastikan aman.
    }
};
