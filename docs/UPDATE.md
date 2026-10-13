# Prosedur Pembaruan Sistem RUDIMAH

Dokumen ini menjelaskan tata kelola pembaruan versi platform RUDIMAH yang menjamin keamanan data dan kompatibilitas skema.

## 1. Prinsip Pembaruan Non-Destruktif
1. Dilarang menjalankan perintah yang merusak data seperti `migrate:fresh` atau `docker compose down -v`.
2. Seluruh perubahan skema basis data wajib dirancang dengan pola *expand-contract* (kompatibel mundur).
3. Sebelum proses pembaruan dimulai, skrip `update.sh` secara otomatis mengeksekusi pencadangan data logis dan konfigurasi.
4. Kunci aplikasi `APP_KEY` dan kredensial basis data dipertahankan dan tidak boleh digenerate ulang selama proses pembaruan.

## 2. Alur Eksekusi Pembaruan
```bash
./update.sh
```

Tahapan yang dijalankan oleh skrip:
1. Memeriksa keberadaan file kunci pengunci proses (`/tmp/rudimah_update.lock`).
2. Menjalankan skrip `backup.sh` untuk mencadangkan database dan lampiran berkas.
3. Menarik (pull) image basis terbaru dan membangun ulang image aplikasi.
4. Menjalankan migrasi basis data aman (`php artisan migrate --force`).
5. Memuat ulang layanan kontainer dan membersihkan cache aplikasi (`php artisan optimize`).
6. Memverifikasi status kesehatan sistem pasca-pembaruan.
