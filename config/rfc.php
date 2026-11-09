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
    | Setiap dokumen dapat memiliki beberapa berkas unduhan. Entri pertama
    | menjadi berkas utama (dipakai untuk label format dan ukuran pada kartu).
    | Kunci unduhan (key) bersifat opsional; kunci dipakai untuk membedakan
    | tautan unduhan pada dokumen yang memiliki lebih dari satu berkas.
    |
    */

    'documents' => [
        'analisis-dampak-teknis' => [
            'title' => 'Panduan Mengisi Formulir RFC',
            'description' => 'Formulir resmi analisis dampak teknis perubahan, dilengkapi panduan pengisian kriteria dampak, kemungkinan, besaran, dan level risiko.',
            'status' => 'Resmi',
            'downloads' => [
                [
                    'key' => 'template-diskominfostandi',
                    'label' => 'Download Template Diskominfostandi',
                    'file' => 'rfc-documents/Template_Form_RFC-Diskominfostandi.pdf',
                    'download_name' => 'Template_Form_RFC-Diskominfostandi.pdf',
                ],
                [
                    'key' => 'form-kosong',
                    'label' => 'Download Template Form Kosong',
                    'file' => 'rfc-documents/Form_RFC.pdf',
                    'download_name' => 'Form_RFC.pdf',
                ],
            ],
        ],
        'panduan-manajemen-risiko' => [
            'title' => 'Panduan Manajemen Risiko',
            'description' => 'Pedoman manajemen risiko pemerintah digital Kota Bekasi: landasan regulasi, kajian kondisi awal, profil risiko, metodologi, dan rencana kerja implementasi.',
            'status' => 'Resmi',
            'downloads' => [
                [
                    'label' => 'Unduh PDF',
                    'file' => 'rfc-documents/Buku_Panduan_Manajemen_Risiko.pdf',
                    'download_name' => 'Buku_Panduan_Manajemen_Risiko.pdf',
                ],
            ],
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Pengingat dan Eskalasi Otomatis
    |--------------------------------------------------------------------------
    |
    | Ambang batas dalam hari kalender dan jeda anti-spam antar pengingat untuk
    | kondisi RFC yang sama. Semua penerima dibatasi pada akun aktif saja.
    |
    */

    'reminders' => [
        'enabled' => env('RFC_REMINDERS_ENABLED', true),
        'pending_review_days' => 3,
        'approved_unassigned_days' => 2,
        'task_not_started_days' => 2,
        'waiting_evaluation_days' => 3,
        'cooldown_days' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ekspor Daftar RFC
    |--------------------------------------------------------------------------
    |
    | Batas maksimum baris untuk satu berkas ekspor. Dapat diturunkan pada
    | pengujian untuk memverifikasi penolakan tanpa membuat ribuan data.
    |
    */

    'export' => [
        'max_rows' => (int) env('RFC_EXPORT_MAX_ROWS', 10000),
    ],
];
