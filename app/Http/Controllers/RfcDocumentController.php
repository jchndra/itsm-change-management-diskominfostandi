<?php

namespace App\Http\Controllers;

use App\Services\RfcDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RfcDocumentController extends Controller
{
    public function __construct(private RfcDocumentService $documents) {}

    public function panduanAnalisisRisiko(): BinaryFileResponse
    {
        $path = base_path('Dokumen/Panduan_Pengisian_Analisis_Resiko.pdf');

        abort_unless(is_file($path), 404);

        $response = response()->download($path, 'Panduan_Pengisian_Analisis_Resiko.pdf', [
            'Content-Type' => 'application/pdf',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function __invoke(string $document, ?string $download = null): BinaryFileResponse
    {
        $downloadData = $this->documents->findDownload($document, $download);

        abort_unless($downloadData && $downloadData['available'], 404);

        $path = $this->documents->pathFor($downloadData);

        abort_unless(is_file($path), 404);

        $response = response()->download($path, $downloadData['download_name'], [
            'Content-Type' => 'application/pdf',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
