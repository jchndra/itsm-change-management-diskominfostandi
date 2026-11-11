<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_tasks', function (Blueprint $table) {
            $table->date('planned_start_on')->nullable()->after('estimated_days');
        });
    }

    public function down(): void
    {
        Schema::table('release_tasks', function (Blueprint $table) {
            $table->dropColumn('planned_start_on');
        });
    }
};
