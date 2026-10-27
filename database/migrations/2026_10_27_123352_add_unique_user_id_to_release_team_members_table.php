<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'release_team_members_user_id_unique';

    public function up(): void
    {
        $duplicateUserIds = DB::table('release_team_members')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($duplicateUserIds->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak dapat menambahkan unique index pada release_team_members.user_id. '
                .'Akun berikut tertaut ke lebih dari satu anggota: '.$duplicateUserIds->implode(', ')
            );
        }

        if (Schema::hasIndex('release_team_members', self::INDEX_NAME)) {
            return;
        }

        Schema::table('release_team_members', function (Blueprint $table): void {
            $table->unique('user_id', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('release_team_members', self::INDEX_NAME)) {
            return;
        }

        Schema::table('release_team_members', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
