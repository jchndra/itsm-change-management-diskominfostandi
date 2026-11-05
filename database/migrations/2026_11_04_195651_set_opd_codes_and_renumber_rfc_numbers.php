<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor RFC kini memakai kode OPD, bukan tahun (RFC-PD01-1, RFC-PD02-1).
     * Setiap OPD memiliki urutan sendiri mulai dari 1. Kode OPD sample juga
     * ditetapkan di sini agar data lama langsung memakai format baru.
     */
    public function up(): void
    {
        if (! Schema::hasTable('opds') || ! Schema::hasTable('change_requests')) {
            return;
        }

        $this->setOpdCodes();

        $now = now();
        $subjectType = 'App\\Models\\ChangeRequest';
        $hasActivityLog = Schema::hasTable('activity_log');
        $counters = [];

        DB::table('change_requests')
            ->leftJoin('users', 'users.id', '=', 'change_requests.submitted_by_id')
            ->leftJoin('applications', 'applications.id', '=', 'change_requests.application_id')
            ->leftJoin('opds as requester_opds', 'requester_opds.id', '=', 'users.opd_id')
            ->leftJoin('opds as application_opds', 'application_opds.id', '=', 'applications.opd_id')
            ->whereNotNull('change_requests.rfc_number')
            ->orderBy('change_requests.id')
            ->select([
                'change_requests.id',
                'change_requests.rfc_number',
                DB::raw('COALESCE(requester_opds.code, application_opds.code) as opd_code'),
            ])
            ->get()
            ->each(function (object $rfc) use (&$counters, $now, $subjectType, $hasActivityLog): void {
                if (blank($rfc->opd_code)) {
                    return;
                }

                $counters[$rfc->opd_code] = ($counters[$rfc->opd_code] ?? 0) + 1;
                $newNumber = 'RFC-'.$rfc->opd_code.'-'.$counters[$rfc->opd_code];

                if ($newNumber === $rfc->rfc_number) {
                    return;
                }

                if ($hasActivityLog) {
                    DB::table('activity_log')->insert([
                        'log_name' => 'rfc',
                        'description' => 'Nomor RFC disesuaikan dengan kode OPD.',
                        'subject_type' => $subjectType,
                        'subject_id' => $rfc->id,
                        'event' => 'rfc_number_opd_renumbered',
                        'causer_type' => null,
                        'causer_id' => null,
                        'attribute_changes' => null,
                        'properties' => json_encode([
                            'event' => 'rfc_number_opd_renumbered',
                            'old_number' => $rfc->rfc_number,
                            'new_number' => $newNumber,
                            'opd_code' => $rfc->opd_code,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('change_requests')
                    ->where('id', $rfc->id)
                    ->update(['rfc_number' => $newNumber]);
            });
    }

    /**
     * Migrasi ini sengaja forward-only. Nomor lama berbasis tahun sudah tidak
     * dipakai aplikasi, sehingga rollback tidak akan memulihkan format itu.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi kode OPD dan penomoran ulang RFC bersifat forward-only dan tidak dapat di-rollback.'
        );
    }

    private function setOpdCodes(): void
    {
        $codes = [
            'Dinas Pelayanan Sample' => 'PD01',
            'Dinas Perencanaan Sample' => 'PD02',
        ];

        foreach ($codes as $name => $code) {
            DB::table('opds')
                ->where('name', $name)
                ->where('code', '!=', $code)
                ->update(['code' => $code, 'updated_at' => now()]);
        }
    }
};
