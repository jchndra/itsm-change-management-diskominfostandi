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
            $table->text('legal_basis')
                ->nullable()
                ->after('description')
                ->comment('Dasar hukum atau regulasi yang melandasi perubahan');

            $table->text('change_risk')
                ->nullable()
                ->after('reason_for_change')
                ->comment('Risiko yang mungkin timbul dari perubahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropColumn(['legal_basis', 'change_risk']);
        });
    }
};
