<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->foreignId('closed_by_id')
                ->nullable()
                ->after('closed_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('closure_notes')->nullable()->after('closed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_id');
            $table->dropColumn('closure_notes');
        });
    }
};
