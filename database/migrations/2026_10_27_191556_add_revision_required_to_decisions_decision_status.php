<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nilai baru ditambahkan di akhir agar aman untuk data lama.
        $values = ['approved', 'rejected', 'returned_to_admin', 'revision_required'];

        if (DB::getDriverName() === 'mysql') {
            $enum = implode(',', array_map(fn (string $value) => "'{$value}'", $values));

            DB::statement("ALTER TABLE decisions MODIFY decision_status ENUM({$enum}) NOT NULL");

            return;
        }

        Schema::table('decisions', function (Blueprint $table) use ($values) {
            $table->enum('decision_status', $values)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE decisions MODIFY decision_status ENUM('approved','rejected','returned_to_admin','revision_required') NOT NULL");

            DB::table('decisions')
                ->where('decision_status', 'revision_required')
                ->update(['decision_status' => 'returned_to_admin']);

            DB::statement("ALTER TABLE decisions MODIFY decision_status ENUM('approved','rejected','returned_to_admin') NOT NULL");

            return;
        }

        Schema::table('decisions', function (Blueprint $table) {
            $table->string('decision_status', 30)->change();
        });

        DB::table('decisions')
            ->where('decision_status', 'revision_required')
            ->update(['decision_status' => 'returned_to_admin']);

        Schema::table('decisions', function (Blueprint $table) {
            $table->enum('decision_status', ['approved', 'rejected', 'returned_to_admin'])->change();
        });
    }
};
