# Keputusan Proyek: Sistem Manajemen Perubahan Layanan TI (RFC), Diskominfostandi Kota Bekasi
(Laravel 13, Livewire 4, Spatie Permission dan Activity Log. Perbarui file ini setiap ada keputusan baru.)

## Role target
- Pemohon: mendaftar sendiri lewat halaman register (NIP dan data pegawai, tanpa dibuatkan akun); membuat dan merevisi RFC; melihat notifikasi dan
  perkembangan RFC dari awal sampai akhir; mengunduh dua template PDF di dashboard (surat persetujuan untuk dicetak, ditandatangani, dipindai, dan diunggah
  ke form; buku panduan matriks risiko); memberi penilaian kepuasan bintang 1-5 setelah RFC selesai (bintang 5 tanpa komentar, bintang 1-4 wajib komentar).
- Change Manager: menerima RFC langsung setelah diajukan; analisis risiko; keputusan; disposisi ke Tim Rilis; mereview hasil Tim Rilis; menutup RFC;
  mengunduh PDF hasil RFC; melihat notifikasi dari awal sampai akhir.
- Tim Rilis (role baru): hanya melihat RFC yang ditugaskan; memberi estimasi jadwal dan rencana awal (terlihat oleh CM dan Pemohon); perpanjangan jadwal
  dengan alasan; tombol Selesai (masuk review CM); tombol Rollback dengan alasan; catatan keterlambatan.
- Admin dan Super Admin: tidak ada dalam desain akhir. Dipensiunkan paling akhir (tahap 7), JANGAN dihapus lebih dulu.
- CAB dan ECAB adalah proses rapat, bukan role login.

## Alur target
Pemohon register -> membuat RFC (satu nomor RFC sejak diajukan) -> langsung ke antrean Change Manager -> analisis risiko -> keputusan (CAB dibahas bersama
Tim Rilis; bentuk di aplikasi menunggu konfirmasi) -> disposisi ke Tim Rilis -> Tim Rilis memberi estimasi -> pelaksanaan (perpanjangan bila perlu) -> Selesai
atau Rollback -> review dan evaluasi CM -> penutupan -> penilaian kepuasan Pemohon.

## Keputusan yang sudah diambil
- Nomor tiket CHG tidak dipakai lagi pada alur baru (kolom lama dibiarkan untuk data lama). Satu nomor RFC.
- Tanpa status baru jika memungkinkan; nilai status lama (mis. returned_to_admin) TIDAK di-rename, cukup label tampilan.
- Penugasan (CM) dan pelaksanaan (Tim Rilis) adalah dua kewenangan berbeda; Tim Rilis tidak boleh memiliki rfc.view_all.
- Penjagaan status dan duplikasi berada di service, bukan hanya policy.
- Histori, activity log, bukti, akun lama, dan data soft delete dipertahankan.
- Tanpa dependensi baru kecuali disetujui. Zona waktu Asia/Jakarta.
- Pengukuran: satu prompt satu tujuan; tes hijau, cek manual, lalu commit sebelum prompt berikutnya.

## Menunggu konfirmasi mentor
1. Arti "Rollback": pemulihan teknis setelah gagal, atau penolakan penugasan oleh Tim Rilis?
2. CAB: perlu fitur di aplikasi (tanggal, peserta, hasil) atau cukup proses di luar aplikasi?
3. Siapa yang membuat akun Tim Rilis dan menonaktifkan akun bermasalah?
4. Dokumen asli kedua template PDF dari mentor (isi tidak boleh dikarang).
5. PDF hasil RFC: boleh menambah pustaka PDF, atau cukup halaman cetak?
   Keputusan sementara: memakai dompdf; view pdf/rfc-result.blade.php dapat dipakai sebagai halaman cetak jika pustaka tidak disetujui mentor.
6. Register: langsung aktif, verifikasi email, atau persetujuan akun?

## Urutan kerja
0 tutup kebocoran daftar RFC (viewAny) | 1 register Pemohon | 2 dashboard Pemohon dan dua unduhan PDF | 3 role Tim Rilis dan disposisi CM |
4 alur kerja Tim Rilis | 5 PDF hasil RFC | 6 penilaian kepuasan | 7 perapian Admin, Super Admin, label. Dashboard Admin dijeda.