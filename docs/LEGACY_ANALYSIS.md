# Analisis Sistem Legacy PEKAN

Dokumen ini mencatat hasil reverse engineering faktual terhadap repositori `https://github.com/honet-labs/pekan` pada commit `2c8a1d5`.

## 1. Ringkasan Repositori Legacy
* Nama Modul: PEKAN (Pengelolaan Keuangan)
* Stack Backend: Go 1.22/1.23, router chi v5, driver database pgx/sql, PostgreSQL 15, Redis 7
* Stack Frontend: React 18.3.1, TypeScript 5.7, Vite 5.4, Apache ECharts 5.4, React Router 6.30
* Pola Arsitektur: Modul modular monolith dengan pendekatan Clean Architecture (Domain, UseCase, Infra, Delivery HTTP)
* Database Engine: PostgreSQL dengan skema relasional terpusat dan multi-tenant berbasis `tenant_id`

## 2. Struktur Direktori Sumber
* `backend/cmd/api`: Titik masuk HTTP API server
* `backend/cmd/worker`: Background worker untuk sinkronisasi dan scheduled task
* `backend/cmd/ai`: Worker untuk receipt scanning dan integrasi AI
* `backend/internal/platform`: Komponen platform bersama mencakup auth JWT, RBAC, audit logging, rate limiting, filescan, storage abstraction, dan tenancy context
* `backend/internal/modules/core`: Submodul autentikasi, manajemen tenant, subscription, dan administrasi platform
* `backend/internal/modules/finance`: Submodul inti keuangan mencakup transaksi, anggaran (budgets), tabungan (savings), pengingat tagihan (reminders), master data (rekening dan kategori), pemindai struk (receipt scan), laporan, notifikasi, dan integrasi WhatsApp
* `backend/migrations`: 45 file migrasi SQL terurut
* `frontend/src/app`: Konfigurasi router, pelindung otorisasi (guards), dan layout AppShell
* `frontend/src/features/core`: Halaman login, profil, dan dashboard admin
* `frontend/src/features/finance`: Komponen antarmuka pengguna untuk semua fitur transaksi keuangan

## 3. Alur Data dan Permintaan (Request and Data Flow)
1. Klien mengirim permintaan HTTP dengan header `Authorization: Bearer <jwt_token>` dan `X-Tenant-ID: <tenant_uuid>`.
2. Middleware memeriksa struktur header, validitas token JWT, masa berlaku sesi, dan status tenant.
3. Kontrol akses memvalidasi hak akses (permission) pengguna dan entitlement modul.
4. HTTP handler memetakan payload permintaan ke DTO (Data Transfer Object).
5. UseCase mengeksekusi logika bisnis dalam cakupan tenant aktif.
6. Repository mengeksekusi query database berlabel `tenant_id` dengan parameter terikat.
7. Transaksi keuangan disimpan menggunakan satuan minor (sen / rupiah penuh tanpa desimal floating point) untuk mencegah kehilangan presisi.

## 4. Keamanan dan Kendali Akses Legacy
* Autentikasi: JWT access token berdurasi singkat dengan refresh token bertransaksi di database dan dukungan rotasi token.
* Multi-tenant: Isolasi logis berbasis kolom `tenant_id` di setiap tabel data tenant.
* Kontrol Akses: Model RBAC dengan tabel `roles`, `permissions`, `role_permissions`, dan `membership_roles`.
* Audit Trail: Setiap mutasi keuangan dicatat dalam `audit_logs` dan `finance_audit_logs`.
* Perlindungan File: File diunggah ke storage terisolasi dengan verifikasi tipe MIME dan tabel `file_scan_jobs`.

## 5. Keterbatasan dan Utang Teknis Legacy
* Dua ekosistem terpisah: Backend Go dan Frontend React SPA membutuhkan orkestrasi build dan deployment terpisah.
* Ketergantungan PostgreSQL: Repositori awal menggunakan fitur PostgreSQL seperti `uuid-ossp`, `BIGSERIAL`, dan tipe `INET` yang memerlukan translasi ke MariaDB InnoDB pada RUDIMAH.
* Ketiadaan antarmuka shared platform tunggal untuk mengintegrasikan modul lain di masa mendatang.
