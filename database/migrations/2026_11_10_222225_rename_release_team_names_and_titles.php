<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penamaan Tim Rilis disesuaikan tanpa mengubah id, user_id, role, maupun
     * tautan akun yang sudah ada. Migrasi ini hanya memperbarui data teks.
     *
     * @var array<string, string>
     */
    private const TEAM_NAME_RENAMES = [
        'Tim Rilis Database' => 'Tim Rilis Pusat Data',
    ];

    /**
     * @var array<string, string>
     */
    private const ROLE_TITLE_RENAMES = [
        'DevOps Engineer Sample' => 'DevOps Engineer',
        'Database Administrator Sample' => 'Data Center Engineer',
        'System Administrator Sample' => 'System Administrator',
    ];

    public function up(): void
    {
        $this->renameReleaseTeamData(self::TEAM_NAME_RENAMES, self::ROLE_TITLE_RENAMES);
    }

    public function down(): void
    {
        $this->renameReleaseTeamData(
            array_flip(self::TEAM_NAME_RENAMES),
            array_flip(self::ROLE_TITLE_RENAMES),
        );
    }

    /**
     * @param  array<string, string>  $teamNames
     * @param  array<string, string>  $roleTitles
     */
    private function renameReleaseTeamData(array $teamNames, array $roleTitles): void
    {
        if (! Schema::hasTable('release_team_members')) {
            return;
        }

        foreach ($teamNames as $oldName => $newName) {
            $linkedUserIds = DB::table('release_team_members')
                ->where('name', $oldName)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            DB::table('release_team_members')
                ->where('name', $oldName)
                ->update([
                    'name' => $newName,
                    'updated_at' => now(),
                ]);

            if ($linkedUserIds->isNotEmpty() && Schema::hasTable('users')) {
                DB::table('users')
                    ->whereIn('id', $linkedUserIds)
                    ->where('name', $oldName)
                    ->update([
                        'name' => $newName,
                        'updated_at' => now(),
                    ]);
            }
        }

        foreach ($roleTitles as $oldTitle => $newTitle) {
            DB::table('release_team_members')
                ->where('role_title', $oldTitle)
                ->update([
                    'role_title' => $newTitle,
                    'updated_at' => now(),
                ]);
        }
    }
};
