# Catatan Migrasi

## 2026-10-26 — Nomor RFC draft dan dokumen pendukung

### Migrasi yang terlibat

| Migrasi | Perubahan | Status rollback |
| --- | --- | --- |
| `2026_10_26_223252_allow_null_rfc_number_and_add_submission_token_to_change_requests_table` | `rfc_number` boleh NULL; menambah `submission_token` unik | Kondisional |
| `2026_10_26_223253_add_document_type_to_change_request_attachments_table` | Menambah `document_type` pada lampiran | Dapat di-rollback |
| `2026_10_26_223254_normalize_draft_rfc_numbers` | Mengosongkan nomor pada draft lama dan mencatatnya di activity log | Forward-only |

### Aturan rollback

- Migrasi `223253` dapat di-rollback karena hanya menghapus kolom
  `document_type`. Ekspor data klasifikasi lampiran terlebih dahulu jika masih
  dibutuhkan.
- Migrasi `223252` hanya dapat di-rollback jika **tidak ada** baris
  `change_requests` dengan `rfc_number` NULL. Jika masih ada draft, `down()`
  berhenti sebelum menjalankan DDL dan melempar `RuntimeException`. Draft tidak
  boleh diberi nomor fiktif hanya agar kolom bisa kembali NOT NULL.
- Migrasi `223254` sengaja forward-only. `down()` selalu melempar
  `RuntimeException`. Nomor yang dikosongkan berasal dari draft yang tidak
  pernah diajukan, sehingga memulihkannya akan melanggar invariant "draft tanpa
  nomor resmi" dan berpotensi konflik dengan nomor yang sudah terbit.
- `223252 down()` terdiri dari dua operasi DDL terpisah. Guard mencegah
  rollback parsial karena data NULL, tetapi kegagalan DDL di tengah proses
  (misalnya lock timeout) masih dapat meninggalkan state parsial. Jika itu
  terjadi, jangan mengulang `down()`; pulihkan dari backup atau gunakan migrasi
  forward yang baru.

### Audit nomor RFC-2026-0002

Audit dilakukan pada 26 Oktober 2026 sebelum normalisasi dianggap final:

- Nomor `RFC-2026-0002` tercatat pada `change_requests` id 2 dengan status
  `draft`.
- Activity log hanya menunjukkan soft delete dengan `previous_status: draft`;
  tidak ada event pengajuan resmi.
- Tidak ada decision, risk assessment, attachment, atau notifikasi terkait.
- Nomor dikosongkan dan activity log dipertahankan. Identitas tampilan menjadi
  `Draft #2`.
- Karena nomor tersebut tidak pernah terbit, nomor tidak perlu direservasi
  permanen.

### Prosedur pemulihan

1. **Backup database sebelum operasi apa pun.** Contoh:
   `mysqldump -u <user> -p <database> > backup-sebelum-recovery.sql`
2. Jalankan `php artisan migrate:status` dan pastikan batch migrasi yang
   terpasang.
3. **Jangan menjalankan `php artisan migrate:rollback` pada batch yang memuat
   ketiga migrasi ini di database penting/production.** Migrasi `223254` akan
   menghentikan proses dan mencegah rollback parsial.
4. Jika hanya perlu membatalkan `223253`, lakukan pada salinan database atau
   environment non-production dan ekspor `document_type` terlebih dahulu. Pada
   database penting, gunakan migrasi forward baru untuk menghapus kolom.
5. Jika perlu mengubah skema yang dicakup `223252` atau `223254`, buat migrasi
   forward baru. Jangan mengembalikan nomor draft secara massal.
6. Jika suatu nomor draft harus dipulihkan karena alasan audit, cari event
   `draft_number_cleared` di `activity_log`, pastikan nomor tersebut tidak
   dipakai oleh RFC resmi mana pun, lalu lakukan perubahan melalui migrasi
   forward yang terdokumentasi dan teruji.

### Verifikasi setelah recovery

```sql
SELECT id, rfc_number, status, deleted_at
FROM change_requests
WHERE rfc_number IS NULL OR status = 'draft';

SELECT id, subject_id, event, properties
FROM activity_log
WHERE event = 'draft_number_cleared'
ORDER BY id;
```
