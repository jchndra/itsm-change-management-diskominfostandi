<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class RfcDocumentService
{
    /**
     * Ambil seluruh dokumen RFC beserta status ketersediaan filenya.
     *
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     file: string,
     *     download_name: string,
     *     format: string,
     *     status: string,
     *     available: bool,
     *     size: int|null,
     *     download_url: string|null
     * }>
     */
    public function all(): array
    {
        return collect(config('rfc.documents', []))
            ->map(fn (array $document, string $key): array => $this->hydrate($key, $document))
            ->values()
            ->all();
    }

    /**
     * Cari satu dokumen berdasarkan kunci konfigurasi.
     *
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     file: string,
     *     download_name: string,
     *     format: string,
     *     status: string,
     *     available: bool,
     *     size: int|null,
     *     download_url: string|null
     * }|null
     */
    public function find(string $key): ?array
    {
        $document = config("rfc.documents.{$key}");

        if (! is_array($document)) {
            return null;
        }

        return $this->hydrate($key, $document);
    }

    /**
     * Path absolut dokumen untuk keperluan pengiriman file.
     */
    public function pathFor(array $document): string
    {
        return Storage::disk('local')->path($document['file']);
    }

    /**
     * @param  array{title: string, description: string, file: string, download_name: string}  $document
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     file: string,
     *     download_name: string,
     *     format: string,
     *     status: string,
     *     available: bool,
     *     size: int|null,
     *     download_url: string|null
     * }
     */
    private function hydrate(string $key, array $document): array
    {
        $disk = Storage::disk('local');
        $available = $disk->exists($document['file']);

        return [
            'key' => $key,
            'title' => $document['title'],
            'description' => $document['description'],
            'file' => $document['file'],
            'download_name' => $document['download_name'],
            'format' => 'PDF',
            'status' => 'Draf / Dummy',
            'available' => $available,
            'size' => $available ? $disk->size($document['file']) : null,
            'download_url' => $available ? route('rfc-documents.download', $key) : null,
        ];
    }
}
