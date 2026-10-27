<?php

namespace App\Http\Controllers;

use App\Services\RfcDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RfcDocumentController extends Controller
{
    public function __construct(private RfcDocumentService $documents) {}

    public function __invoke(string $document): BinaryFileResponse
    {
        $documentData = $this->documents->find($document);

        abort_unless($documentData && $documentData['available'], 404);

        $path = $this->documents->pathFor($documentData);

        abort_unless(is_file($path), 404);

        $response = response()->download($path, $documentData['download_name'], [
            'Content-Type' => 'application/pdf',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
