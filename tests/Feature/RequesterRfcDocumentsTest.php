<?php

use App\Models\User;
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

it('menampilkan dua dokumen rfc dummy pada dashboard pemohon', function () {
    $response = $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Form RFC & Pedoman Manajemen Risiko')
        ->assertSee('Template Surat Persetujuan RFC')
        ->assertSee('Pedoman Pengisian RFC & Surat Persetujuan')
        ->assertSee('Draf / Dummy')
        ->assertSee(route('rfc-documents.download', 'template-surat-persetujuan'), false)
        ->assertSee(route('rfc-documents.download', 'pedoman-pengisian-rfc'), false)
        ->assertDontSee('Dokumen belum tersedia');

    expect(substr_count($response->getContent(), 'Unduh PDF'))->toBe(2);
});

it('mengizinkan pengguna terautentikasi mengunduh kedua dokumen rfc', function () {
    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', 'template-surat-persetujuan'))
        ->assertDownload('Template Surat Persetujuan RFC - Dummy.pdf')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');

    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', 'pedoman-pengisian-rfc'))
        ->assertDownload('Pedoman Pengisian RFC dan Surat Persetujuan - Dummy.pdf')
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');
});

it('menyimpan kedua dokumen sebagai file pdf yang valid di disk privat', function () {
    foreach (config('rfc.documents') as $document) {
        $disk = Storage::disk('local');
        $path = $disk->path($document['file']);

        expect($disk->exists($document['file']))->toBeTrue()
            ->and($disk->size($document['file']))->toBeGreaterThan(0)
            ->and(file_get_contents($path, false, null, 0, 5))->toBe('%PDF-');
    }
});

it('tidak meninggalkan salinan dokumen rfc di direktori publik', function () {
    foreach (config('rfc.documents') as $document) {
        expect(is_file(public_path($document['file'])))->toBeFalse();
    }

    expect(is_dir(public_path('documents')))->toBeFalse();
});

it('mengizinkan staf yang berhak mengunduh dokumen rfc', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('rfc-documents.download', 'template-surat-persetujuan'))
        ->assertDownload('Template Surat Persetujuan RFC - Dummy.pdf');
});

it('menolak pengguna terautentikasi tanpa izin mengunduh dokumen rfc', function () {
    $tanpaIzin = User::factory()->create();

    $this->actingAs($tanpaIzin)
        ->get(route('rfc-documents.download', 'template-surat-persetujuan'))
        ->assertForbidden();
});

it('menolak tamu yang mencoba mengunduh dokumen rfc', function () {
    $this->get(route('rfc-documents.download', 'template-surat-persetujuan'))
        ->assertRedirect(route('login'));
});

it('mengembalikan 404 untuk kunci dokumen yang tidak dikenal', function () {
    $this->actingAs($this->pemohon)
        ->get('/rfc-documents/dokumen-tidak-dikenal')
        ->assertNotFound();
});

it('menolak percobaan path traversal pada route dokumen', function () {
    $this->actingAs($this->pemohon)
        ->get('/rfc-documents/..%2F..%2F.env')
        ->assertNotFound();
});

it('tidak melayani dokumen privat melalui route storage tanpa signed url', function () {
    $this->get('/storage/rfc-documents/template-surat-persetujuan-rfc-dummy.pdf')
        ->assertForbidden();
});

it('menampilkan status belum tersedia jika file dokumen tidak ada', function () {
    config([
        'rfc.documents.template-surat-persetujuan.file' => 'rfc-documents/file-yang-tidak-ada.pdf',
    ]);

    $this->actingAs($this->pemohon)
        ->get(route('requester.dashboard'))
        ->assertOk()
        ->assertSee('Dokumen belum tersedia')
        ->assertDontSee(route('rfc-documents.download', 'template-surat-persetujuan'), false)
        ->assertSee(route('rfc-documents.download', 'pedoman-pengisian-rfc'), false);

    $this->actingAs($this->pemohon)
        ->get(route('rfc-documents.download', 'template-surat-persetujuan'))
        ->assertNotFound();
});
