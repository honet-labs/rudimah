# RUDIMAH: Ruang Digital Manajemen Harian

Platform Manajemen Hidup Pribadi (*Personal Life Management Platform*) berbasis web modular modern, multi-tenant, dan terintegrasi dengan shared authentication, MFA, RBAC, serta modul keuangan harian (PEKAN).

## Gambaran Umum

RUDIMAH menyediakan satu portal terpadu untuk mengelola berbagai aplikasi web harian. Platform ini dibangun menggunakan arsitektur modular monolith berbasis Laravel 13, Vue 3 TypeScript, Inertia.js, dan MariaDB dengan isolasi data multi-tenant yang ketat.

### Modul Utama
1. **Core Platform & Identity**:
   - Single Sign-On (SSO) internal dan session management
   - Otentikasi multi-faktor (MFA): TOTP dan Passkey WebAuthn
   - Multi-tenancy fail-closed (Personal, Family, Business/Team)
   - Role-Based Access Control (RBAC): Super Admin, Tenant Owner, Member, Viewer
   - Audit trail tamper-evident untuk semua aktivitas sensitif

2. **PEKAN (Pengeluaran dan Keuangan Harian)**:
   - Manajemen akun dan dompet multi-currency dengan presisi integer minor
   - Pencatatan transaksi pendapatan, pengeluaran, dan transfer antar akun
   - Manajemen anggaran (budget) dinamis dengan sistem peringatan
   - Target tabungan (savings goals) bertahap
   - Pengingat tagihan dan piutang berulang
   - Ekstraksi struk belanja via AI OCR dengan alur verifikasi manusia (Human-in-the-Loop)
   - Laporan visual dan analitik keuangan

3. **Kecerdasan Buatan (AI) dan Asisten Cerdas Terisolasi**:
   - **Fungsi 1 (AI OCR Struk Belanja)**: Ekstraksi draf transaksi kuitansi berbasis model visi (Google Gemini, OpenAI, atau Local Vision). Mengikuti prinsip Human in the Loop (ADR-005) di mana draf wajib diverifikasi oleh pengguna sebelum disimpan final.
   - **Fungsi 2 (Asisten AI per Ruang Kerja)**: Layanan percakapan cerdas yang terintegrasi ke penyedia Google Gemini, OpenAI, Hermes Agent (Nous Hermes 3), OpenClaw Agent, dan Local Mock.
   - **Isolasi Keamanan Data Mutlak**: Asisten di Ruang Kerja A terisolasi secara mutlak pada konteks data miliknya (`TenantScope`). Jika pengguna menanyakan data mengenai ruang kerja/tenant lain, asisten secara teknis tidak memiliki akses ke data tersebut dan menolak permintaan secara tegas demi menjaga kerahasiaan data.

## Tumpukan Teknologi (Tech Stack)

- **Backend**: Laravel 13, PHP 8.3+, Eloquent ORM
- **Frontend**: Vue 3 (Composition API, `<script setup lang="ts">`), Inertia.js 3, TypeScript strict
- **Desain & Gaya**: Tailwind CSS 4, Custom Design Tokens RUDIMAH (netral, minimalis, kontras tinggi WCAG 2.2 AA)
- **Database**: MariaDB 11.x (InnoDB Engine, dual connection: `platform` dan `pekan`)
- **Cache & Queue**: Redis 7.x
- **Container**: Docker & Docker Compose dengan multi-stage production build

## Panduan Instalasi Cepat

### Persyaratan Sistem
- Linux (Ubuntu 22.04 LTS / Debian 12 direkomendasikan) atau Windows dengan Docker Desktop / WSL2
- Docker 24.0+ dan Docker Compose v2.20+
- RAM minimal 2 GB, CPU minimal 2 Core

### Menjalankan Installer Otomatis
```bash
# Clone repositori
git clone https://github.com/honet-labs/rudimah.git
cd rudimah

# Jalankan skrip installer interaktif
bash installer.sh
```

Installer akan memandu proses:
1. Konfigurasi domain dan port HTTP/HTTPS
2. Kredensial database MariaDB terisolasi
3. Kredensial akun Super Admin awal
4. Konfigurasi SSL Let's Encrypt atau Self-Signed Certificate
5. Setup worker antrean Redis dan scheduler cron

### Perintah Operasional
- Cek Status Layanan: `bash status.sh`
- Pembaruan Versi: `bash update.sh`
- Pencadangan Data: `bash backup.sh`
- Pemulihan Data: `bash restore.sh [path-arsip-backup]`
- Rekonfigurasi: `bash reconfigure.sh`
- Uninstall Bersih: `bash uninstall.sh`

## Pengujian dan Kualitas Kode

Suite pengujian otomatis mencakup unit, feature, dan security tests:
```bash
# Jalankan seluruh test suite
php artisan test
```

Uji spesifik:
- `tests/Feature/TenantIsolationTest.php`: Menjamin proteksi kebocoran data antar-tenant
- `tests/Feature/MfaAssuranceTest.php`: Memverifikasi penegakan MFA untuk aksi sensitif
- `tests/Feature/TransactionPrecisionTest.php`: Memastikan integritas presisi mata uang
- `tests/Feature/ReceiptOcrHumanVerificationTest.php`: Memastikan verifikasi struk sebelum commit

## Dokumentasi Teknis

Dokumentasi lengkap arsitektur dan spesifikasi tersedia pada direktori `docs/`:
- `docs/ARCHITECTURE.md`: Arsitektur sistem, topologi layanan, dan isolasi tenant
- `docs/DESIGN_SYSTEM.md`: Panduan token, tipografi, dan komponen antarmuka
- `docs/SECURITY.md`: Matriks ancaman, kebijakan auth, dan enkripsi
- `docs/LEGACY_ANALYSIS.md`: Hasil audit dan pemetaan repositori awal PEKAN
- `docs/FEATURE_PARITY_MATRIX.md`: Matriks paritas fitur legacy vs RUDIMAH PEKAN
- `docs/adr/`: Architecture Decision Records (ADR-001 hingga ADR-007)
- `docs/INSTALLATION.md`: Panduan instalasi manual dan kustom
- `docs/DEPLOYMENT.md`: Prosedur deployment production dan hardening

## Lisensi dan Hak Cipta

Hak Cipta (c) 2026 HONET Labs. Seluruh hak cipta dilindungi undang-undang.
