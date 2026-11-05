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
     *     format: string,
     *     status: string|null,
     *     available: bool,
     *     size: int|null,
     *     file: string|null,
     *     download_name: string|null,
     *     download_url: string|null,
     *     downloads: array<int, array{
     *         key: string,
     *         label: string,
     *         file: string,
     *         download_name: string,
     *         format: string,
     *         available: bool,
     *         size: int|null,
     *         download_url: string|null
     *     }>
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
     *     format: string,
     *     status: string|null,
     *     available: bool,
     *     size: int|null,
     *     file: string|null,
     *     download_name: string|null,
     *     download_url: string|null,
     *     downloads: array<int, array{
     *         key: string,
     *         label: string,
     *         file: string,
     *         download_name: string,
     *         format: string,
     *         available: bool,
     *         size: int|null,
     *         download_url: string|null
     *     }>
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
     * Cari satu berkas unduhan pada dokumen.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     file: string,
     *     download_name: string,
     *     format: string,
     *     available: bool,
     *     size: int|null,
     *     download_url: string|null
     * }|null
     */
    public function findDownload(string $key, ?string $download = null): ?array
    {
        $document = $this->find($key);

        if ($document === null || $document['downloads'] === []) {
            return null;
        }

        if ($download === null) {
            return $document['downloads'][0];
        }

        foreach ($document['downloads'] as $item) {
            if ($item['key'] === $download) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Path absolut dokumen untuk keperluan pengiriman file.
     */
    public function pathFor(array $download): string
    {
        return Storage::disk('local')->path($download['file']);
    }

    /**
     * @param  array{
     *     title: string,
     *     description: string,
     *     status?: string,
     *     downloads: array<int, array{key?: string, label: string, file: string, download_name: string}>
     * }  $document
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     format: string,
     *     status: string|null,
     *     available: bool,
     *     size: int|null,
     *     file: string|null,
     *     download_name: string|null,
     *     download_url: string|null,
     *     downloads: array<int, array{
     *         key: string,
     *         label: string,
     *         file: string,
     *         download_name: string,
     *         format: string,
     *         available: bool,
     *         size: int|null,
     *         download_url: string|null
     *     }>
     * }
     */
    private function hydrate(string $key, array $document): array
    {
        $downloads = collect($document['downloads'] ?? [])
            ->values()
            ->map(function (array $download) use ($key): array {
                return $this->hydrateDownload($key, $download, $download['key'] ?? null);
            })
            ->all();

        $primary = $downloads[0] ?? null;

        return [
            'key' => $key,
            'title' => $document['title'],
            'description' => $document['description'],
            'format' => 'PDF',
            'status' => $document['status'] ?? null,
            'available' => $primary['available'] ?? false,
            'size' => $primary['size'] ?? null,
            'file' => $primary['file'] ?? null,
            'download_name' => $primary['download_name'] ?? null,
            'download_url' => $primary['download_url'] ?? null,
            'downloads' => $downloads,
        ];
    }

    /**
     * @param  array{label: string, file: string, download_name: string}  $download
     * @return array{
     *     key: string,
     *     label: string,
     *     file: string,
     *     download_name: string,
     *     format: string,
     *     available: bool,
     *     size: int|null,
     *     download_url: string|null
     * }
     */
    private function hydrateDownload(string $documentKey, array $download, ?string $downloadKey): array
    {
        $disk = Storage::disk('local');
        $available = $disk->exists($download['file']);

        $routeKey = $downloadKey ?? $documentKey;

        return [
            'key' => $routeKey,
            'label' => $download['label'],
            'file' => $download['file'],
            'download_name' => $download['download_name'],
            'format' => 'PDF',
            'available' => $available,
            'size' => $available ? $disk->size($download['file']) : null,
            'download_url' => $available
                ? route('rfc-documents.download', $downloadKey === null
                    ? $documentKey
                    : ['document' => $documentKey, 'download' => $routeKey])
                : null,
        ];
    }
}
