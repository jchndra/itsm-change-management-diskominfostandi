<?php

namespace App\Services;

use App\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RfcNumberGenerator
{
    /**
     * Nomor diambil dari nomor resmi tertinggi dengan kode OPD yang sama.
     * Setiap OPD memiliki urutan sendiri, sehingga RFC-PD01-1 dan RFC-PD02-1
     * dapat terbit berdampingan. Draft tanpa nomor dan RFC terhapus tetap
     * diperhitungkan agar nomor yang pernah terbit tidak dipakai ulang.
     */
    public static function generate(string $opdCode): string
    {
        $prefix = 'RFC-'.$opdCode.'-';

        return DB::transaction(function () use ($prefix): string {
            $lastNumber = ChangeRequest::withTrashed()
                ->whereNotNull('rfc_number')
                ->where('rfc_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('rfc_number')
                ->map(fn (string $number): int => (int) Str::afterLast($number, '-'))
                ->max() ?? 0;

            return $prefix.($lastNumber + 1);
        });
    }
}
