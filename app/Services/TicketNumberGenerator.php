<?php

namespace App\Services;

use App\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public static function generate(): string
    {
        $year = date('Y');
        $prefix = "CHG-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $latestRfc = ChangeRequest::withTrashed()
                ->whereNotNull('ticket_number')
                ->where('ticket_number', 'LIKE', "{$prefix}%")
                ->orderByDesc('ticket_number')
                ->lockForUpdate()
                ->first();

            if (! $latestRfc) {
                return "{$prefix}0001";
            }

            $lastNumber = (int) substr($latestRfc->ticket_number, -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "{$prefix}{$nextNumber}";
        });
    }
}
