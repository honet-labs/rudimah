# ADR 006: Manajemen Rahasia dan Integritas Pencadangan Sistem

## Status
Diterima

## Konteks
Penyebaran Docker Compose mandiri rentan terhadap kebocoran kredensial atau kerusakan data jika kata sandi dibiarkan default atau skrip pembaruan menghapus volume data.

## Keputusan
1. Skrip `installer.sh` tidak boleh menggunakan kata sandi default statis. Semua kata sandi database dan kunci enkripsi aplikasi harus digenerate secara acak atau dimasukkan manual oleh operator dengan input tersembunyi.
2. Izin berkas konfigurasi `.env` dibatasi menggunakan `chmod 600` dan `umask 077`.
3. Skrip `update.sh` menerapkan prinsip non-destruktif: tidak boleh mengeksekusi `migrate:fresh` atau menghapus volume Docker.
4. Skrip `backup.sh` membuat cadangan logis basis data secara konsisten bersama dengan berkas lampiran tenant dan manifes konfigurasi terenkripsi.

## Konsekuensi
Positif:
* Keamanan deployment self-hosted terlindungi dari serangan credential stuffing.
* Pemulihan bencana (disaster recovery) dapat diuji secara berkala dengan skrip `restore.sh`.

Negatif:
* Operator bertanggung jawab menyimpan berkas cadangan dan kunci dekripsi di lokasi offsite yang aman.
