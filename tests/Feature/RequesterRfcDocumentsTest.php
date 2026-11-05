<?php

use App\Models\User;
use App\Services\RfcDocumentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->pemohon = User::factory()->create([
        'name' => 'Pemohon Dokumen',
    ]);
    $this->pemohon->assignRole('Pemohon');
});

it('menampilkan dokumen rfc resmi pada dashboard pemohon', function () {
    $response = $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Form RFC & Pedoman Manajemen Risiko')
        ->assertSee('Panduan Mengisi Formulir RFC')
        ->assertSee('Panduan Manajemen Risiko')
        ->assertSee('Resmi')
        ->assertDontSee('Draf / Dummy')
        ->assertDontSee('draf/dummy')
        ->assertSee(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'template-diskominfostandi']), false)
        ->assertSee(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'form-kosong']), false)
        ->assertSee(route('rfc-documents.download', 'panduan-manajemen-risiko'), false)
        ->assertSee('Download Template Diskominfostandi')
        ->assertSee('Download Template Form Kosong')
        ->assertDontSee('Dokumen belum tersedia')
        ->assertDontSee('deployment/server');

    expect(preg_match_all('/>\s*Unduh PDF\s*<\/a>/', $response->getContent()))->toBe(1);
});

it('menampilkan tautan unduhan yang berbeda untuk setiap template rfc', function () {
    $downloads = collect(app(RfcDocumentService::class)->all())
        ->firstWhere('key', 'analisis-dampak-teknis')['downloads'];

    expect($downloads)->toHaveCount(2)
        ->and($downloads[0]['download_url'])->toBe(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'template-diskominfostandi']))
        ->and($downloads[1]['download_url'])->toBe(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'form-kosong']))
        ->and($downloads[0]['file'])->not->toBe($downloads[1]['file']);
});

it('mengizinkan pengguna terautentikasi mengunduh semua dokumen rfc', function () {
    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'template-diskominfostandi']))
        ->assertDownload('Template_Form_RFC-Diskominfostandi.pdf')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');

    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'form-kosong']))
        ->assertDownload('Form_RFC.pdf')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');

    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', 'panduan-manajemen-risiko'))
        ->assertDownload('Buku_Panduan_Manajemen_Risiko.pdf')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');
});

it('menyimpan seluruh dokumen sebagai file pdf yang valid di disk privat', function () {
    foreach (config('rfc.documents') as $document) {
        foreach ($document['downloads'] as $download) {
            $disk = Storage::disk('local');
            $path = $disk->path($download['file']);

            expect($disk->exists($download['file']))->toBeTrue()
                ->and($disk->size($download['file']))->toBeGreaterThan(0)
                ->and(file_get_contents($path, false, null, 0, 5))->toBe('%PDF-');
        }
    }
});

it('tidak meninggalkan salinan dokumen rfc di direktori publik', function () {
    foreach (config('rfc.documents') as $document) {
        foreach ($document['downloads'] as $download) {
            expect(is_file(public_path($download['file'])))->toBeFalse();
        }
    }

    expect(is_dir(public_path('documents')))->toBeFalse();
});

it('mengizinkan staf yang berhak mengunduh dokumen rfc', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'template-diskominfostandi']))
        ->assertDownload('Template_Form_RFC-Diskominfostandi.pdf');
});

it('menolak pengguna terautentikasi tanpa izin mengunduh dokumen rfc', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->get(route('rfc-documents.download', 'analisis-dampak-teknis'))
        ->assertForbidden();
});

it('menolak tamu yang mencoba mengunduh dokumen rfc', function () {
    $this->get(route('rfc-documents.download', 'analisis-dampak-teknis'))
        ->assertRedirect(route('login'));
});

it('mengembalikan 404 untuk kunci dokumen yang tidak dikenal', function () {
    $this->actingAs($this->pemohon)
        ->get('/rfc-documents/dokumen-tidak-dikenal')
        ->assertNotFound();
});

it('mengembalikan 404 untuk kunci unduhan yang tidak dikenal', function () {
    $this->actingAs($this->pemohon)
        ->get('/rfc-documents/analisis-dampak-teknis/template-tidak-dikenal')
        ->assertNotFound();
});

it('menolak percobaan path traversal pada route dokumen', function () {
    $this->actingAs($this->pemohon)
        ->get('/rfc-documents/..%2F..%2F.env')
        ->assertNotFound();
});

it('tidak melayani dokumen privat melalui route storage tanpa signed url', function () {
    $this->get('/storage/rfc-documents/Template_Form_RFC-Diskominfostandi.pdf')
        ->assertForbidden();
});

it('menampilkan status belum tersedia jika file dokumen tidak ada', function () {
    config([
        'rfc.documents.analisis-dampak-teknis.downloads.0.file' => 'rfc-documents/file-yang-tidak-ada.pdf',
    ]);

    $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Dokumen belum tersedia')
        ->assertDontSee(route('rfc-documents.download', ['document' => 'analisis-dampak-teknis', 'download' => 'template-diskominfostandi']), false);

    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', 'analisis-dampak-teknis'))
        ->assertNotFound();
});
