<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_task_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_task_id')->constrained('release_tasks')->cascadeOnDelete();
            $table->foreignId('change_request_id')->constrained('change_requests')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('old_estimated_days')->nullable();
            $table->unsignedSmallInteger('new_estimated_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_task_updates');
    }
};
