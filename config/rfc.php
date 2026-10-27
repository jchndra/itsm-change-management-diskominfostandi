<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dokumen RFC untuk Pemohon
    |--------------------------------------------------------------------------
    |
    | Dokumen pada bagian ini bersifat statis dan diganti melalui deployment.
    | Aplikasi tidak menyediakan fitur unggah atau pengelolaan dokumen dari
    | dashboard. Path di bawah relatif terhadap root disk privat "local"
    | (storage/app/private), bukan direktori public aplikasi.
    |
    */

    'documents' => [
        'template-surat-persetujuan' => [
            'title' => 'Template Surat Persetujuan RFC',
            'description' => 'Template surat persetujuan perubahan sistem, dilengkapi kolom identitas, ringkasan perubahan, risiko, dan ruang tanda tangan pejabat.',
            'file' => 'rfc-documents/template-surat-persetujuan-rfc-dummy.pdf',
            'download_name' => 'Template Surat Persetujuan RFC - Dummy.pdf',
        ],
        'pedoman-pengisian-rfc' => [
            'title' => 'Pedoman Pengisian RFC & Surat Persetujuan',
            'description' => 'Panduan pengisian RFC dan surat persetujuan, termasuk contoh perubahan, panduan risiko, serta matriks risiko dummy.',
            'file' => 'rfc-documents/pedoman-pengisian-rfc-dan-surat-persetujuan-dummy.pdf',
            'download_name' => 'Pedoman Pengisian RFC dan Surat Persetujuan - Dummy.pdf',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Surat Persetujuan Bertanda Tangan
    |--------------------------------------------------------------------------
    |
    | SEMENTARA: seluruh kategori perubahan wajib melampirkan surat persetujuan
    | bertanda tangan saat diajukan. Aturan ini menunggu konfirmasi SOP; jangan
    | menambahkan pengecualian otomatis untuk Emergency sebelum SOP ditetapkan.
    |
    */

    'approval_letter' => [
        'required_for_submission' => true,
        'temporary' => true,
        'note' => 'Kewajiban surat persetujuan untuk semua kategori masih bersifat sementara menunggu konfirmasi SOP.',
        'change_types' => ['standard', 'normal', 'emergency'],
        'mimes' => ['pdf'],
        'max_kb' => 5120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defer RFC Notifications Until Commit
    |--------------------------------------------------------------------------
    |
    | Ketika aktif, notifikasi RFC dikirim setelah transaksi database terluar
    | berhasil di-commit. Kegagalan pengiriman dilaporkan tanpa membatalkan
    | perubahan status maupun activity log. Pada pengujian, nilai ini dibuat
    | nonaktif agar notifikasi dapat diperiksa langsung di dalam transaksi uji.
    |
    */

    'notify_after_commit' => env('RFC_NOTIFY_AFTER_COMMIT', true),
];
