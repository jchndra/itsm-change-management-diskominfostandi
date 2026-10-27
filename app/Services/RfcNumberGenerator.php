<?php

namespace App\Services;

use App\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RfcNumberGenerator
{
    /**
     * Nomor diambil dari nomor resmi tertinggi pada tahun berjalan. Draft
     * tanpa nomor dan RFC terhapus tetap diperhitungkan agar nomor yang sudah
     * pernah terbit tidak dipakai ulang.
     */
    public static function generate(): string
    {
        $year = date('Y');
        $prefix = "RFC-{$year}-";

        return DB::transaction(function () use ($prefix): string {
            $lastNumber = ChangeRequest::withTrashed()
                ->whereNotNull('rfc_number')
                ->where('rfc_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('rfc_number')
                ->map(fn (string $number): int => (int) Str::afterLast($number, '-'))
                ->max() ?? 0;

            return $prefix.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
        });
    }
}
