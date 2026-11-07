<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('estimated_days')->nullable()->after('instruction');
        });
    }

    public function down(): void
    {
        Schema::table('release_tasks', function (Blueprint $table): void {
            $table->dropColumn('estimated_days');
        });
    }
};
