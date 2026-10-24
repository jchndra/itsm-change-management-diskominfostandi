<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_implementations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')
                ->unique()
                ->constrained('change_requests')
                ->cascadeOnDelete();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('implementation_result', 20)->index();
            $table->dateTime('started_at');
            $table->dateTime('finished_at');
            $table->string('executor_name', 255);
            $table->string('report_source', 255)->nullable();
            $table->text('summary');
            $table->text('test_results');
            $table->text('failure_notes')->nullable();
            $table->string('follow_up_action', 30)->nullable()->index();
            $table->text('follow_up_notes')->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->foreignId('evaluated_by_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('evaluated_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_implementations');
    }
};
