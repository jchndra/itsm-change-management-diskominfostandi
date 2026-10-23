<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->string('ticket_number', 30)->nullable()->unique();
            $table->string('priority', 20)->nullable();
            $table->string('category', 30)->nullable();
            $table->text('admin_note')->nullable();
            $table->dateTime('ticket_created_at')->nullable();
            $table->foreignId('ticket_created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropForeign(['ticket_created_by_id']);
            $table->dropUnique(['ticket_number']);
            $table->dropColumn([
                'ticket_number',
                'priority',
                'category',
                'admin_note',
                'ticket_created_at',
                'ticket_created_by_id',
            ]);
        });
    }
};
