<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_tasks', function (Blueprint $table): void {
            // Change Manager hanya mendisposisikan tugas; estimasi jadwal diisi
            // Tim Rilis pada langkah berikutnya sehingga kedua kolom opsional.
            $table->dateTime('scheduled_start')->nullable()->change();
            $table->dateTime('scheduled_end')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('release_tasks', function (Blueprint $table): void {
            $table->dateTime('scheduled_start')->nullable(false)->change();
            $table->dateTime('scheduled_end')->nullable(false)->change();
        });
    }
};
