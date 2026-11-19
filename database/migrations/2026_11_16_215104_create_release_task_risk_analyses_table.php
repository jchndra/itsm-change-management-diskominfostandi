<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_task_risk_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_task_id')->unique()->constrained('release_tasks')->cascadeOnDelete();
            $table->foreignId('submitted_by_id')->constrained('users')->restrictOnDelete();
            $table->text('activity_plan');
            $table->text('change_plan');
            $table->text('rollback_mechanism');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_task_risk_analyses');
    }
};
