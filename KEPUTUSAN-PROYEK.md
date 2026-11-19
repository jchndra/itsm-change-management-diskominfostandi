# Keputusan Proyek: Sistem Manajemen Perubahan Layanan TI (RFC), Diskominfostandi Kota Bekasi

(Laravel 13, Livewire 4, Spatie Permission dan Activity Log. Perbarui file ini setiap ada keputusan baru.)

## Role target

* Pemohon: mendaftar sendiri lewat halaman register (NIP dan data pegawai, tanpa dibuatkan akun); membuat dan merevisi RFC; melihat notifikasi dan perkembangan RFC dari awal sampai akhir; mengunduh dua template PDF di dashboard (surat persetujuan untuk dicetak, ditandatangani, dipindai, dan diunggah ke form; buku panduan matriks risiko); memberi penilaian kepuasan bintang 1-5 setelah RFC selesai (bintang 5 tanpa komentar, bintang 1-4 wajib komentar).
* Change Manager: menerima RFC langsung setelah diajukan; analisis risiko; keputusan; disposisi ke Tim Rilis; mereview hasil Tim Rilis; menutup RFC; mengunduh PDF hasil RFC; melihat notifikasi dari awal sampai akhir.
* Tim Rilis (role baru): hanya melihat RFC yang ditugaskan (dashboard "Tugas Saya"); mengisi analisis risiko wajib (estimasi kegiatan dan rencana mulai, rencana kegiatan, rencana perubahan, mekanisme rollback) sebelum memulai; memulai pengerjaan; memperbarui rencana perubahan selama pelaksanaan; memperbarui informasi estimasi dengan alasan; tombol Setuju atau Reject (masuk review CM); catatan keterlambatan.
* Admin dan Super Admin: tidak ada dalam desain akhir. Dipensiunkan paling akhir (tahap 7), JANGAN dihapus lebih dulu.
* CAB dan ECAB adalah proses rapat, bukan role login.

## Alur target

Pemohon register -> membuat RFC (satu nomor RFC sejak diajukan) -> langsung ke antrean Change Manager -> analisis risiko -> keputusan (CAB dibahas bersama Tim Rilis; bentuk di aplikasi menunggu konfirmasi) -> disposisi ke Tim Rilis -> Tim Rilis mengisi analisis risiko dan estimasi -> memulai pengerjaan -> pelaksanaan (pembaruan rencana perubahan dan update informasi bila perlu) -> Berhasil atau Belum Berhasil (Catat Implementasi) -> review dan evaluasi CM -> (Change Manager dapat menolak pada tahap mana pun sejak disetujui) -> penutupan -> penilaian kepuasan Pemohon.

## Keputusan yang sudah diambil

* Nomor tiket CHG tidak dipakai lagi pada alur baru (kolom lama dibiarkan untuk data lama). Satu nomor RFC.
* Tanpa status baru jika memungkinkan; nilai status lama (mis. `returned\\\\\\\\\\\\\\\_to\\\\\\\\\\\\\\\_admin`) TIDAK di-rename, cukup label tampilan.
* Penugasan (CM) dan pelaksanaan (Tim Rilis) adalah dua kewenangan berbeda; Tim Rilis tidak boleh memiliki `rfc.view\\\\\\\\\\\\\\\_all`.
* Penjagaan status dan duplikasi berada di service, bukan hanya policy.
* Histori, activity log, bukti, akun lama, dan data soft delete dipertahankan.
* Tanpa dependensi baru kecuali disetujui. Zona waktu Asia/Jakarta.
* Cara kerja: satu prompt satu tujuan; tes hijau, cek manual, lalu commit sebelum prompt berikutnya.
* Catat Implementasi, waktu: waktu mulai dan waktu selesai tidak diinput manual. Waktu mulai diambil dari saat Tim Rilis menekan "Mulai Pengerjaan"; waktu selesai dicatat saat laporan disimpan. Kolom waktu pada data implementasi tetap dipakai, nilainya ditentukan oleh service.
* Catat Implementasi, bukti: unggahan bukti baru hanya berformat PDF (maks. 5 berkas, 5 MB per berkas). Bukti lama berformat gambar tetap dapat dibuka.
* Catat Implementasi, hasil: hanya dua pilihan, yaitu Berhasil (`success`) dan Belum Berhasil (`failed`). Opsi Rollback dihapus dari alur laporan baru. Nilai lama `rolled_back` tetap disimpan dan tetap terbaca sebagai "Rollback dilakukan" untuk data historis. "Belum Berhasil" adalah laporan fakta pelaksanaan, bukan keputusan penolakan; keputusan penolakan tetap menjadi kewenangan Change Manager.
* Catat Implementasi, konfirmasi: centang konfirmasi berarti menyetujui laporan beserta dokumen perubahan dan bukti dukung; teks dibedakan untuk hasil Berhasil dan Belum Berhasil, tanpa frasa "mewakili Tim Rilis". Setelah disimpan, laporan dan bukti tidak dapat diubah dan RFC masuk review Change Manager.
* Analisis risiko Tim Rilis wajib diisi sebelum "Mulai Pengerjaan". Form berurutan: estimasi kegiatan (hari dan rencana mulai), rencana kegiatan, rencana perubahan, dan mekanisme rollback. Penyimpanan form menetapkan estimasi dalam satu transaksi dan mencatat tipe pembaruan `risk_analysis_submitted`. Form tetap dapat diubah selama tugas masih menunggu dimulai, lalu menjadi baca-saja bagi Change Manager dan Pemohon.
* Pembaruan selama pengerjaan: "Update informasi" menggantikan "Revisi Estimasi" untuk mengubah estimasi hari dengan alasan. "Rencana perubahan" dipakai sebagai pembaruan berjalan atas rencana awal pada analisis risiko.
* Notifikasi dipisah menjadi dua bagian: "Diskusi RFC" (event `comment_added`) dan "Notifikasi lainnya" (termasuk notifikasi lama tanpa `event`). Masing-masing memiliki filter, paginasi, jumlah belum dibaca, dan aksi tandai dibaca sendiri. Klik notifikasi diskusi membuka RFC pada bagian `#diskusi`.
* Kalender (/kalender): hanya untuk Tim Rilis (anggota aktif, tugasnya sendiri) dan Change Manager (semua tugas). Pemohon, Admin, dan Super Admin tidak mendapat akses. Otorisasi diperiksa di route dan di setiap request Livewire.
* Menetapkan estimasi selalu menyimpan tanggal rencana mulai. Bila Tim Rilis tidak memilih tanggal, sistem memakai tanggal penetapan estimasi (WIB) agar jadwal di Kalender tidak bergeser.

## Keputusan yang sudah diambil (lanjutan)

* Tim Rilis tidak pernah memiliki keputusan "Reject"/penolakan. Hasil laporan Tim Rilis hanya Berhasil atau Belum Berhasil. Istilah "Setuju" dan "Reject" di sisi Tim Rilis dihapus dari form, panel progres, dashboard, Kalender, disposisi, dan riwayat; label hasil `failed` tampil sebagai "Belum Berhasil".
* Status `failed` tetap ada untuk data historis dan RFC yang menunggu evaluasi. Labelnya menjadi "Belum Berhasil — Menunggu Evaluasi", badge amber, dan tidak lagi dihitung sebagai "Ditolak" (masuk grup status baru `not_successful`).
* Keputusan menolak (`rejected`) adalah kewenangan Change Manager dengan izin `rfc.record_decision` yang bukan pemilik RFC. Berlaku sejak `approved`, `in_progress`, `implemented`, hingga `failed`, termasuk saat pekerjaan berjalan atau evaluasi belum dilakukan.
* Penolakan setelah disposisi dicatat lewat halaman khusus `/change-requests/{rfc}/reject` (bukan modal) dan `RfcRejectionService`: tercatat pada `decisions`, mengisi `rejection_note`, dan memindahkan RFC ke status `rejected`. Setelah ditolak, RFC final — tidak dapat diimplementasikan, dievaluasi, atau ditutup.
* Penolakan saat pekerjaan berjalan membatalkan tugas Tim Rilis aktif dengan status baru `release_tasks.execution_status = cancelled` ("Dibatalkan"), bukan `rolled_back`. Tugas yang sudah selesai (`successful`/`rolled_back`) tidak diubah.
* Tim Rilis yang ditugaskan pada RFC yang ditolak mendapat notifikasi penolakan.
* Grup `not_successful` ("Belum Berhasil") kini tampil sebagai kartu statistik amber di dashboard Pemohon dan daftar RFC Pemohon, Change Manager, dan Admin (hint: "Pelaksanaan belum berhasil" / "Menunggu evaluasi atau penutupan" untuk CM). Definisi angka tetap memakai `scopeInStatusGroup('not_successful')`; "Ditolak" tetap khusus `rejected`. Tim Rilis tidak diberi kartu baru karena "Menunggu Evaluasi" sudah mencakup `failed`.

## Menunggu konfirmasi mentor

1. Istilah "Rollback" pada hasil implementasi sudah diputuskan dihapus dari pilihan laporan baru; "Mekanisme rollback" tetap dipertahankan pada form analisis risiko sebagai rencana pemulihan teknis.
2. CAB: perlu fitur di aplikasi (tanggal, peserta, hasil) atau cukup proses di luar aplikasi?
3. Siapa yang membuat akun Tim Rilis dan menonaktifkan akun bermasalah?
4. Dokumen asli kedua template PDF dari mentor (isi tidak boleh dikarang).
5. PDF hasil RFC: boleh menambah pustaka PDF, atau cukup halaman cetak?
Keputusan sementara: memakai dompdf; view `pdf/rfc-result.blade.php` dapat dipakai sebagai halaman cetak jika pustaka tidak disetujui mentor.
6. Register: langsung aktif, verifikasi email, atau persetujuan akun?
7. Catat Implementasi: apakah aturan "bukti hanya PDF" dan "waktu selesai = saat laporan disimpan" sesuai kebiasaan Tim Rilis di lapangan? Risikonya: Tim Rilis yang hanya punya tangkapan layar harus menggabungkannya ke PDF dulu, dan laporan yang diisi belakangan mencatat waktu selesai yang terlambat.

## Urutan kerja

0. Tutup kebocoran daftar RFC (viewAny)
1. Register Pemohon
2. Dashboard Pemohon dan dua unduhan PDF
3. Role Tim Rilis dan disposisi CM
4. Alur kerja Tim Rilis
5. PDF hasil RFC
6. Penilaian kepuasan
7. Perapian Admin, Super Admin, label

Dashboard Admin dijeda.

## Catatan pengerjaan antarmuka Tim Rilis

Berjalan di luar tahap 0-7 di atas. Centang sesuai kondisi terakhir.

* \[x] Catat Implementasi: waktu otomatis dan bukti PDF saja
* \[x] Redesain halaman Catat Implementasi (senada dengan panel Progres Pengerjaan)
* \[x] Refactor: panel Progres Pengerjaan menjadi komponen Livewire `ReleaseTaskProgressPanel`; ringkasan read-only menjadi partial `release-task-summary`
* \[x] Audit keterbacaan layar sempit dan aksesibilitas
* \[x] Perbaikan kecil panel progres (format riwayat estimasi, keterangan tombol)
* \[x] Dashboard: pencarian, penanda jatuh tempo, tab Riwayat
* \[x] Dashboard: aksi cepat estimasi dan catatan kegiatan
* \[x] Analisis risiko wajib sebelum mulai, label hasil "Berhasil" dan "Belum Berhasil", pemisahan notifikasi diskusi dan lainnya
* \[x] Audit halaman Kalender (otorisasi, konsistensi, layar sempit)

