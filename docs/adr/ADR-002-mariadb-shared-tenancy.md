# ADR 002: Model Multi-Tenant Shared Database Berbasis MariaDB

## Status
Diterima

## Konteks
Sistem harus mampu melayani multi-tenant (ruang kerja individu, keluarga, maupun organisasi) dengan pemisahan data yang ketat namun efisien secara pemakaian sumber daya perangkat keras.

## Keputusan
1. Menggunakan MariaDB InnoDB sebagai mesin basis data tunggal dengan pemisahan database logis:
   * `platform_db`: Tabel pengguna, tenant, keanggotaan, peran, dan sesi.
   * `pekan_db`: Tabel transaksi keuangan, rekening, anggaran, dan tabungan.
2. Seluruh tabel transaksional pada `pekan_db` wajib memuat kolom `tenant_id` bertipe UUID/VARCHAR(36).
3. Penerapan Global Scopes dan Laravel Policies yang memastikan query dieksekusi secara fail-closed terhadap tenant aktif yang tervalidasi dari sesi.
4. Nilai `tenant_id` tidak pernah diambil mentah dari input pengguna melainkan diekstraksi dari konteks autentikasi.

## Konsekuensi
Positif:
* Efisiensi tinggi pada skenario deployment Docker single host.
* Migrasi dan pencadangan (backup) dapat dilakukan secara terpadu melalui skrip backup.sh.

Negatif:
* Wajib melakukan audit berkala terhadap raw query untuk mencegah bypass scope tenant.
