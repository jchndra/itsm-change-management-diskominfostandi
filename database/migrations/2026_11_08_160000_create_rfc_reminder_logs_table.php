<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfc_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->constrained('change_requests')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->dateTime('since_at');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->unique(['change_request_id', 'kind', 'since_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfc_reminder_logs');
    }
};
