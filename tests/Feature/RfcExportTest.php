<?php

use App\Enums\RfcStatus;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Carbon::setTestNow(Carbon::create(2026, 11, 8, 10, 0, 0, 'Asia/Jakarta'));

    $this->cm = User::factory()->create(['name' => 'Change Manager Ekspor']);
    $this->cm->assignRole('Change Manager');

    $this->pemohon = User::factory()->create();
    $this->pemohon->assignRole('Pemohon');

    $this->timRilis = User::factory()->create();
    $this->timRilis->assignRole('Tim Rilis');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function rfcExportMakeRfc(array $attributes = []): ChangeRequest
{
    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $rfc = ChangeRequest::factory()->create($attributes);

    if ($createdAt !== null) {
        $rfc->forceFill(['created_at' => $createdAt])->saveQuietly();
        $rfc->refresh();
    }

    return $rfc;
}

/**
 * @return array<string, array<int, array<int, mixed>>>
 */
function rfcExportReadXlsx(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'rfc-export-test-');
    file_put_contents($path, $content);

    $reader = new Reader;
    $reader->open($path);

    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        $sheets[$sheet->getName()] = $rows;
    }

    $reader->close();
    @unlink($path);

    return $sheets;
}

/**
 * @return array<int, array<int, string|null>>
 */
function rfcExportCsvRows(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

it('mengunduh berkas xlsx dengan sheet daftar dan ringkasan', function () {
    rfcExportMakeRfc(['title' => 'Ubah Layanan Publik']);

    $response = $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('Laporan-RFC_semua_semua.xlsx');

    $sheets = rfcExportReadXlsx($response->streamedContent());

    expect($sheets)->toHaveKeys(['Daftar RFC', 'Ringkasan'])
        ->and($sheets['Daftar RFC'][0][1])->toBe('Nomor RFC')
        ->and($sheets['Daftar RFC'][1][3])->toBe('Ubah Layanan Publik');
});

it('mengunduh berkas csv dengan BOM header dan pemisah titik koma', function () {
    rfcExportMakeRfc(['title' => 'Ubah Layanan Publik']);

    $response = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'from' => '2026-11-01',
            'to' => '2026-11-08',
        ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    $rows = rfcExportCsvRows($content);

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No;"Nomor RFC"')
        ->and($rows[0][1])->toBe('Nomor RFC')
        ->and($rows[1][3])->toBe('Ubah Layanan Publik')
        ->and($response->headers->get('Content-Disposition'))
        ->toContain('Laporan-RFC_2026-11-01_2026-11-08.csv');
});

it('menerapkan filter status aplikasi dan tanggal', function () {
    $applicationA = Application::factory()->create();
    $applicationB = Application::factory()->create();

    rfcExportMakeRfc([
        'title' => 'RFC A',
        'status' => RfcStatus::SUBMITTED->value,
        'application_id' => $applicationA->id,
        'created_at' => Carbon::parse('2026-11-01 09:00:00'),
    ]);
    rfcExportMakeRfc([
        'title' => 'RFC B',
        'status' => RfcStatus::CLOSED->value,
        'application_id' => $applicationB->id,
        'created_at' => Carbon::parse('2026-10-01 09:00:00'),
    ]);
    rfcExportMakeRfc([
        'title' => 'RFC C',
        'status' => RfcStatus::SUBMITTED->value,
        'application_id' => $applicationA->id,
        'created_at' => Carbon::parse('2026-09-01 09:00:00'),
    ]);

    $byStatus = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'status' => RfcStatus::SUBMITTED->value,
        ]))
        ->streamedContent();

    expect($byStatus)->toContain('RFC A')
        ->toContain('RFC C')
        ->not->toContain('RFC B');

    $byApplication = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'application_id' => $applicationA->id,
        ]))
        ->streamedContent();

    expect($byApplication)->toContain('RFC A')
        ->toContain('RFC C')
        ->not->toContain('RFC B');

    $byDate = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'from' => '2026-10-15',
            'to' => '2026-11-08',
        ]))
        ->streamedContent();

    expect($byDate)->toContain('RFC A')
        ->not->toContain('RFC B')
        ->not->toContain('RFC C');
});

it('memakai ticket_created_at saat basis tanggal tiket', function () {
    rfcExportMakeRfc([
        'title' => 'RFC Basis Tiket',
        'created_at' => Carbon::parse('2026-10-01 09:00:00'),
        'ticket_created_at' => Carbon::parse('2026-11-05 10:00:00'),
    ]);

    $byCreated = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'basis' => 'created',
            'from' => '2026-11-01',
        ]))
        ->streamedContent();

    $byTicket = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'basis' => 'ticket',
            'from' => '2026-11-01',
        ]))
        ->streamedContent();

    expect($byCreated)->not->toContain('RFC Basis Tiket')
        ->and($byTicket)->toContain('RFC Basis Tiket');
});

it('tidak menyertakan RFC yang sudah dihapus', function () {
    $rfc = rfcExportMakeRfc(['title' => 'RFC Terhapus']);
    $rfc->delete();

    $content = $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->streamedContent();

    expect($content)->not->toContain('RFC Terhapus');
});

it('menolak Pemohon dan Tim Rilis', function () {
    $this->actingAs($this->pemohon)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->assertForbidden();

    $this->actingAs($this->timRilis)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->assertForbidden();
});

it('mengalihkan tamu ke halaman login', function () {
    $this->get(route('change-requests.export', ['format' => 'csv']))
        ->assertRedirect(route('login'));
});

it('menetralkan formula injection pada csv dan xlsx', function () {
    rfcExportMakeRfc(['title' => '=cmd|\'/C calc\'!A0']);

    $csv = $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->streamedContent();

    $rows = rfcExportCsvRows($csv);

    expect($csv)->toContain("'=cmd|")
        ->and($rows[1][3])->toBe("'=cmd|'/C calc'!A0");

    $xlsx = $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'xlsx']))
        ->streamedContent();

    $sheets = rfcExportReadXlsx($xlsx);

    expect($sheets['Daftar RFC'][1][3])->toBe("'=cmd|'/C calc'!A0");
});

it('menolak ekspor yang melebihi batas baris', function () {
    config(['rfc.export.max_rows' => 1]);

    rfcExportMakeRfc();
    rfcExportMakeRfc();

    $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->assertStatus(422)
        ->assertSee('Terlalu banyak data (>1). Persempit filter atau periode.', false);
});

it('membatasi ekspor enam kali per menit per pengguna', function () {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->actingAs($this->cm)
            ->get(route('change-requests.export', ['format' => 'csv']))
            ->assertOk();
    }

    $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->assertStatus(429)
        ->assertSee('Terlalu banyak permintaan ekspor. Coba lagi dalam satu menit.');
});

it('mencatat activity ekspor tanpa isi baris', function () {
    rfcExportMakeRfc(['title' => 'JUDUL-RAHASIA-BARIS']);

    $this->actingAs($this->cm)
        ->get(route('change-requests.export', ['format' => 'csv']))
        ->assertOk();

    $activity = Activity::query()
        ->where('description', 'Daftar RFC diekspor.')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->cm->id)
        ->and($activity->properties['event'])->toBe('rfc_list_exported')
        ->and($activity->properties['format'])->toBe('csv')
        ->and($activity->properties['row_count'])->toBe(1)
        ->and(json_encode($activity->properties))->not->toContain('JUDUL-RAHASIA-BARIS');
});

it('menyediakan tautan ekspor pada dashboard Change Manager', function () {
    $this->actingAs($this->cm)
        ->get(route('change-manager.dashboard'))
        ->assertOk()
        ->assertSee('Unduh laporan periode ini');
});

it('menyaring opd pemohon pada ekspor', function () {
    $opdA = Opd::factory()->create();
    $opdB = Opd::factory()->create();

    $pemohonA = User::factory()->create(['opd_id' => $opdA->id]);
    $pemohonB = User::factory()->create(['opd_id' => $opdB->id]);

    rfcExportMakeRfc(['title' => 'RFC OPD A', 'submitted_by_id' => $pemohonA->id]);
    rfcExportMakeRfc(['title' => 'RFC OPD B', 'submitted_by_id' => $pemohonB->id]);

    $content = $this->actingAs($this->cm)
        ->get(route('change-requests.export', [
            'format' => 'csv',
            'opd_id' => $opdA->id,
        ]))
        ->streamedContent();

    expect($content)->toContain('RFC OPD A')
        ->not->toContain('RFC OPD B');
});
