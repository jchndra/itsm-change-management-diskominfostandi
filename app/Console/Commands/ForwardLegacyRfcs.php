<?php

namespace App\Console\Commands;

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use App\StateMachines\RfcStatusTransition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('rfc:forward-legacy {--dry-run : Tampilkan daftar tanpa mengubah data}')]
#[Description('Teruskan RFC lama berstatus submitted/returned_to_admin ke Change Manager')]
class ForwardLegacyRfcs extends Command
{
    public function handle(): int
    {
        $legacyStatuses = [
            RfcStatus::SUBMITTED->value,
            RfcStatus::RETURNED_TO_ADMIN->value,
        ];

        $rfcs = ChangeRequest::query()
            ->whereIn('status', $legacyStatuses)
            ->orderBy('id')
            ->get(['id', 'rfc_number', 'title', 'status']);

        if ($rfcs->isEmpty()) {
            $this->info('Tidak ada RFC lama yang perlu diteruskan.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'No. RFC', 'Status', 'Judul'],
                $rfcs->map(fn (ChangeRequest $rfc): array => [
                    $rfc->id,
                    $rfc->displayIdentifier(),
                    RfcStatus::labelFor($rfc->status),
                    $rfc->title,
                ])->all()
            );

            $this->info("Dry run: {$rfcs->count()} RFC akan diteruskan tanpa perubahan data.");

            return self::SUCCESS;
        }

        $forwarded = 0;

        foreach ($rfcs as $candidate) {
            $changed = DB::transaction(function () use ($candidate): bool {
                $rfc = ChangeRequest::query()
                    ->whereKey($candidate->id)
                    ->lockForUpdate()
                    ->first();

                if (! $rfc || ! in_array(RfcStatus::fromValue($rfc->status), [
                    RfcStatus::SUBMITTED,
                    RfcStatus::RETURNED_TO_ADMIN,
                ], true)) {
                    return false;
                }

                RfcStatusTransition::transitionBySystem(
                    $rfc,
                    RfcStatus::TICKET_CREATED,
                    properties: ['event' => 'legacy_forwarded'],
                    logMessage: 'Diteruskan ke Change Manager (penyesuaian alur)',
                );

                return true;
            });

            if ($changed) {
                $forwarded++;
                $this->line("Diteruskan: {$candidate->displayIdentifier()}");
            }
        }

        $this->info("Selesai: {$forwarded} RFC diteruskan ke Change Manager.");

        return self::SUCCESS;
    }
}
