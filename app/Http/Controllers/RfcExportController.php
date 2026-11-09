<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\User;
use App\Services\RfcExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfcExportController extends Controller
{
    private const RATE_LIMIT_PER_MINUTE = 6;

    public function __invoke(Request $request, RfcExportService $service): StreamedResponse|Response
    {
        Gate::authorize('exportList', ChangeRequest::class);

        /** @var User $user */
        $user = $request->user();

        $rateKey = 'rfc-export:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateKey, self::RATE_LIMIT_PER_MINUTE)) {
            return response('Terlalu banyak permintaan ekspor. Coba lagi dalam satu menit.', 429);
        }

        RateLimiter::hit($rateKey, 60);

        $validated = $request->validate([
            'format' => ['nullable', 'in:xlsx,csv'],
            'basis' => ['nullable', 'in:created,ticket'],
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string'],
            'change_type' => ['nullable', 'string'],
            'application_id' => ['nullable', 'integer', 'min:1'],
            'opd_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $format = $validated['format'] ?? 'csv';
        $filters = array_intersect_key($validated, array_flip([
            'basis', 'q', 'status', 'change_type', 'application_id', 'opd_id', 'from', 'to',
        ]));

        try {
            $total = $service->query($filters)->count();
        } catch (InvalidArgumentException $exception) {
            return response($exception->getMessage(), 422);
        }

        $maxRows = (int) config('rfc.export.max_rows', 10000);

        if ($total > $maxRows) {
            return response(
                'Terlalu banyak data (>'.number_format($maxRows, 0, ',', '.').'). Persempit filter atau periode.',
                422
            );
        }

        activity()
            ->causedBy($user)
            ->withProperties([
                'event' => 'rfc_list_exported',
                'format' => $format,
                'filters' => $filters,
                'row_count' => $total,
            ])
            ->log('Daftar RFC diekspor.');

        $fileName = $this->fileName($filters, $format);

        if ($format === 'xlsx') {
            return $this->xlsxResponse($service, $filters, $user, $fileName);
        }

        return $this->csvResponse($service, $filters, $fileName);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function csvResponse(RfcExportService $service, array $filters, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($service, $filters): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $service->headers(), ';');

            foreach ($service->rows($filters) as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function xlsxResponse(
        RfcExportService $service,
        array $filters,
        User $user,
        string $fileName
    ): StreamedResponse {
        $path = tempnam(sys_get_temp_dir(), 'rfc-export-');

        $writer = new Writer;
        $writer->openToFile($path);

        $headerStyle = (new Style)->withFontBold(true);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Daftar RFC');
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));

        foreach ($this->columnWidths() as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $writer->addRow(Row::fromValuesWithStyle($service->headers(), $headerStyle));

        foreach ($service->rows($filters) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Ringkasan');
        $writer->addRow(Row::fromValuesWithStyle(['Ringkasan Laporan RFC'], $headerStyle));

        $summary = $service->summarize($filters, $user);

        $summaryRows = [
            ['Periode', $summary['periode']],
            ['Basis tanggal', $summary['basis']],
            ['Dicetak oleh', $summary['dicetak_oleh']],
            ['Waktu cetak', $summary['waktu_cetak']],
            ['Jumlah total', $summary['total']],
        ];

        foreach ($summary['per_status'] as $label => $count) {
            $summaryRows[] = ['Status: '.$label, $count];
        }

        foreach ($summaryRows as $summaryRow) {
            $writer->addRow(Row::fromValues(array_map(
                fn (mixed $value) => $service->sanitizeCell($value),
                $summaryRow
            )));
        }

        $writer->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function fileName(array $filters, string $format): string
    {
        $from = filled($filters['from'] ?? null) ? (string) $filters['from'] : 'semua';
        $to = filled($filters['to'] ?? null) ? (string) $filters['to'] : 'semua';

        $safeFrom = preg_replace('/[^A-Za-z0-9_-]/', '-', $from) ?: 'semua';
        $safeTo = preg_replace('/[^A-Za-z0-9_-]/', '-', $to) ?: 'semua';

        return 'Laporan-RFC_'.$safeFrom.'_'.$safeTo.'.'.$format;
    }

    /**
     * @return array<int, float>
     */
    private function columnWidths(): array
    {
        return [
            6, 18, 16, 40, 22, 18, 18, 12, 22, 22, 20,
            18, 18, 16, 18, 22, 12, 18, 18, 18, 18, 12,
        ];
    }
}
