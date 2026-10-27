<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_request_attachments', function (Blueprint $table) {
            // Lampiran lama otomatis menjadi dokumen pendukung.
            $table->string('document_type', 30)->default('supporting')->index();
        });
    }

    public function down(): void
    {
        Schema::table('change_request_attachments', function (Blueprint $table) {
            $table->dropIndex(['document_type']);
            $table->dropColumn('document_type');
        });
    }
};
