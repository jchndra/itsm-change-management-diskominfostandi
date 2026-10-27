<?php

use App\Enums\RfcStatus;
use App\Models\ChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('menolak rollback normalisasi nomor draft karena bersifat forward-only', function () {
    $migration = require database_path(
        'migrations/2026_10_26_223254_normalize_draft_rfc_numbers.php'
    );

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});

it('menolak rollback kolom nomor draft jika masih ada draft tanpa nomor', function () {
    ChangeRequest::factory()->create([
        'status' => RfcStatus::DRAFT->value,
        'rfc_number' => null,
    ]);

    $migration = require database_path(
        'migrations/2026_10_26_223252_allow_null_rfc_number_and_add_submission_token_to_change_requests_table.php'
    );

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Schema::hasColumn('change_requests', 'submission_token'))->toBeTrue();
});
