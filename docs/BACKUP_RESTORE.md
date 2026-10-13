# Panduan Pencadangan dan Pemulihan Sistem (Backup & Restore)

Dokumen ini memuat prosedur operasional standar untuk pencadangan data rutin dan pemulihan bencana pada platform RUDIMAH.

## 1. Prosedur Pencadangan Rutin (Backup)
Jalankan perintah berikut:
```bash
./backup.sh
```
Proses ini akan menghasilkan:
* Berkas arsip kompresi: `backups/rudimah_backup_<timestamp>.tar.gz`
* Berkas checksum: `backups/rudimah_backup_<timestamp>.tar.gz.sha256`

Komponen yang dicadangkan meliputi:
1. Skema dan data seluruh basis data MariaDB (`platform_db` dan `pekan_db`).
2. Berkas konfigurasi lingkungan dan kunci enkripsi `.env`.
3. Direktori berkas lampiran kuitansi privat.

Disarankan untuk mengatur cron job berkala di tingkat server host untuk menjalankan `./backup.sh` setiap malam pada pukul 02:00 WIB.

## 2. Prosedur Pemulihan (Restore)
Untuk memulihkan sistem dari berkas cadangan:
```bash
./restore.sh backups/rudimah_backup_<timestamp>.tar.gz
```
Tahapan yang dilakukan:
1. Verifikasi kecocokan checksum SHA256 untuk memastikan berkas cadangan tidak rusak.
2. Meminta konfirmasi eksplisit dari operator sebelum menimpa data berjalan.
3. Mengimpor kembali data basis data ke kontainer MariaDB.
4. Menjalankan pemeriksaan integritas pasca-pemulihan.
