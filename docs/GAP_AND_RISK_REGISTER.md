# Daftar Gap dan Manajemen Risiko Migrasi

Dokumen ini mencatat mitigasi teknis dan perbedaan antara repositori awal PEKAN dan target arsitektur RUDIMAH.

## 1. Fakta Terkonfirmasi vs Asumsi
* Fakta: Modul legacy PEKAN ditulis menggunakan Go dan PostgreSQL dengan skema multi-tenant terpusat.
* Fakta: Seluruh nilai transaksi pada legacy disimpan dalam format integer minor (rupiah penuh) untuk menghindari isu pembulatan desimal.
* Fakta: Modul AI receipt scan pada legacy mengandalkan background worker terpisah dan membutuhkan persetujuan manusia sebelum menyimpan transaksi final.
* Asumsi Awal (Terkoreksi): Arsitektur target RUDIMAH adalah Laravel Modular Monolith dengan MariaDB InnoDB dan Redis, didukung Vue 3 TypeScript dan Inertia 3.

## 2. Matriks Risiko dan Tindakan Mitigasi

| Kode Risiko | Deskripsi Risiko | Dampak | Probabilitas | Tindakan Mitigasi Terencana |
|---|---|---|---|---|
| RSK-001 | Kebocoran data antar tenant (cross-tenant leakage) | Kritis | Rendah | Penerapan Laravel Policies ketat, penegakan tenant_id dari sesi tervalidasi, serta unit test negatif Tenant A vs B. |
| RSK-002 | Kehilangan presisi nilai mata uang saat kalkulasi | Tinggi | Rendah | Mempertahankan integer minor units di seluruh service dan DTO, melarang kalkulasi floating point untuk data finansial. |
| RSK-003 | Bypass otentikasi MFA oleh peran administrator | Kritis | Rendah | Mengunci akun privileged hanya dengan Passkey atau TOTP, melarang fallback ke kanal ber-assurance rendah seperti email atau pesan instan. |
| RSK-004 | Hasil halusinasi OCR menghasilkan data transaksi fiktif | Tinggi | Menengah | Menerapkan alur Human-in-the-loop wajib di mana pengguna harus meninjau dan mengonfirmasi hasil ekstraksi sebelum transaksi disimpan. |
| RSK-005 | Perbedaan sintaks SQL antara PostgreSQL dan MariaDB | Sedang | Menengah | Menerjemahkan seluruh fungsi skema PostgreSQL ke sintaks kompatibel MariaDB InnoDB melalui migrasi Laravel terstandarisasi. |
| RSK-006 | Inkonsistensi antarmuka antarmodul di masa depan | Sedang | Rendah | Pembatasan komponen antarmuka ke pustaka bersama Rd* dan AppShell terpusat, melarang penulisan CSS ad-hoc pada halaman modul. |
