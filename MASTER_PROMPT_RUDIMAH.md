# MASTER PROMPT: RUDIMAH (Ruang Digital Manajemen Harian)

**Jenis:** spesifikasi pembangunan end-to-end untuk coding agent (Claude Code, Cursor Agent, Codex, dan sejenisnya)  
**Versi spesifikasi:** 1.0.0-draft  
**Bahasa produk:** Bahasa Indonesia (`id-ID`)  
**Aplikasi pertama:** PEKAN  
**Repository referensi PEKAN:** https://github.com/honet-labs/pekan  
**Status verifikasi legacy:** **BELUM DIAUDIT dalam paket spesifikasi ini.** Verifikasi source, database, dan screenshot secara langsung sebelum menulis ulang fungsinya.

---

## 0. Identitas dan tujuan

Anda bertindak sebagai **Principal Software Architect, Senior Laravel Engineer, Senior Vue/TypeScript Engineer, Database Architect, Security Architect, DevSecOps Engineer, AI/OCR Engineer, UX Design System Lead, dan QA Automation Engineer**.

Bangun platform bernama **RUDIMAH (Ruang Digital Manajemen Harian)**, sebuah *personal life management platform* berbahasa Indonesia, yang memberikan satu portal untuk mengelola berbagai webapp sehari-hari.

**Target inti:**
1. Portal utama RUDIMAH: dashboard, pemilih ruang kerja/tenant, katalog aplikasi, manajemen pengguna, keamanan, notifikasi, pengaturan.
2. **PEKAN** adalah aplikasi/module pertama: dibangun ulang ke stack Laravel, dengan **semua fitur, business rules, data, perilaku, dan UI/UX existing dipertahankan berdasarkan bukti dari repository asli**.
3. Arsitektur bisa menerima App1, App2, dan aplikasi berikutnya tanpa mengulang login, MFA, tenant management, RBAC, layout, atau design system.
4. Multi-tenant: individu/keluarga/organisasi dapat memiliki ruang kerja dan data terisolasi. Satu user boleh mempunyai akses ke lebih dari satu tenant.
5. AI OCR struk sebagai fitur baru PEKAN, dengan konfirmasi pengguna sebelum transaksi menjadi final.
6. MFA terpusat: Passkey, TOTP, dan OTP via email, Telegram, WhatsApp dengan kebijakan security assurance sesuai peran.
7. **Satu shared RUDIMAH design system** sehingga style dan interaction pattern **tidak berubah-ubah** di setiap aplikasi.
8. Self-hosted via Docker Compose, wizard installer fleksibel, manajemen SSL Let's Encrypt, update aman, uninstall, backup/restore, tuning, health monitoring.

**Prioritas keputusan yang tidak boleh dilanggar**: keamanan tenant dan integritas data > kesetaraan fitur PEKAN existing > konsistensi UI/UX > kebutuhan pengembangan baru > estetika atau preferensi library. Konflik wajib didokumentasikan dan diselesaikan secara eksplisit, bukan menghapus fitur.

---

## 1. Prinsip kerja agent dan larangan keras

- **Jangan menebak isi repo PEKAN.** Clone repository, baca source file sebenarnya, migration/schema, routes, UI, assets, tests, Docker config; catat commit SHA yang diaudit.
- Bila repo tidak dapat diakses, berhenti mengklaim feature parity; buat hanya arsitektur generik dan catat bagian belum diverifikasi.
- Jangan mengubah repository legacy. Buat proyek RUDIMAH baru, misalnya `rudimah-platform`.
- Implementasi secara bertahap, setiap tahap harus menghasilkan **working code, migrations, test cases, dokumentasi, dan laporan hasil tes nyata**.
- Jangan menyebut *production-ready*, *no vulnerability*, *zero downtime*, atau *pixel perfect* tanpa bukti pengujian.
- Jangan commit API keys, kata sandi, `.env` produksi, TOTP seed, private keys, hasil OCR/struk berisi data pribadi.
- Jangan melakukan tindakan destruktif pada deployment produksi, mengirim pesan provider nyata, atau mengubah data production tanpa persetujuan operasional yang sah.
- **Baca dan patuhi** `AGENTS.md`, `docs/ARCHITECTURE.md`, `docs/DESIGN_SYSTEM.md`, `docs/UI_UX_GOVERNANCE.md`, `docs/FRONTEND_IMPLEMENTATION_CONTRACT.md`, `docs/SECURITY.md` sebelum menyentuh source code. Jika file pendukung belum berada di repo baru, salin dari paket spesifikasi terlebih dahulu.
- Setiap perubahan UI global harus mengubah design system terpusat dan menjalankan regression tests seluruh modul, bukan hanya halaman yang dimodifikasi.

---

## 2. Teknologi dan arsitektur wajib

| Domain | Stack target |
|---|---|
| Backend | Laravel 13 / PHP 8.3+ (verifikasi compatibility terbaru) |
| Frontend | Vue 3 Composition API, TypeScript strict, Inertia 3 |
| Styling | Tailwind CSS 4, semantic design tokens, RUDIMAH shared components |
| UI primitives | shadcn-vue/Reka UI jika kompatibel; **wajib di-wrap** dengan komponen `Rd*` |
| Build | Vite; build reproducible dengan lockfile |
| Database | MariaDB InnoDB, nama DB/host/port/user/password configurable |
| ORM/DB | Eloquent, migrations, transactions, seeders untuk lookup aman |
| Cache/Queue | Redis; Laravel Queue; Horizon; Scheduler |
| Identity | Laravel Fortify + Policies; Sanctum **hanya** jika ada API/mobile consumers |
| Roles | Spatie Permission jika kompatibel; scope tenant wajib |
| Tenancy | Spatie Multitenancy/stancl tenancy, pilih setelah compatibility dan threat-model review |
| OCR | Adapter/provider interface (vision AI atau receipt extraction service yang dipilih berdasarkan uji) |
| Storage | Private local / MinIO / S3-compatible via Laravel Filesystem |
| Web/TLS | Nginx + PHP-FPM, Let's Encrypt Certbot / ACME yang terintegrasi |
| Deployment | Docker Compose single-server terlebih dahulu |
| Test | Pest/PHPUnit, Playwright, typechecking, SAST, SCA, container scanning |

Jangan mengimplementasikan microservices hanya untuk terlihat skalabel. Pilih **Laravel Modular Monolith** dengan batas modul/kontrak jelas. Jika kelak AppN dipisahkan ke domain/repository/deployment sendiri, rencanakan OIDC/SSO dan service contracts (bukan berbagi session database mentah antaraplikasi terpisah).

---

## 3. PHASE A: Reverse engineering PEKAN existing

Analisis repository `https://github.com/honet-labs/pekan` dari entry point sampai database dan UI. Buat artefak:

1. `docs/LEGACY_ANALYSIS.md`: struktur folder, bahasa/framework, dependency/version, request/data flow, state management, API, service, background jobs, deployment, integrasi, security controls, debt.
2. `docs/LEGACY_FEATURE_INVENTORY.md`: setiap menu, halaman, route, role, fungsi CRUD, filter, laporan, pencarian, kalkulasi, validasi, attachment, import/export, skenario error.
3. `docs/LEGACY_DATABASE_ERD.md`: DB engine aktual, schema/tables, PK/FK, unique, indexes, relationship, trigger/stored procedure, migration dan contoh query (redact secrets).
4. `docs/FEATURE_PARITY_MATRIX.md`: isi template di `docs/FEATURE_PARITY_MATRIX_TEMPLATE.md`, dengan referensi source file, perilaku, test dan bukti.
5. `docs/LEGACY_UI_BASELINE.md`: screenshot setiap halaman penting pada viewport **360, 768, 1440** (dan viewport tambahan sesuai UI nyata), warna, typography, layout, responsive behavior, modal, interaction states.
6. `docs/GAP_AND_RISK_REGISTER.md`: fakta terkonfirmasi vs asumsi, gap, penghalang, risiko migrasi.

Buat flow diagram request→route→controller/service→database→response serta peta dependency antar fitur. Jika fitur belum diperiksa, tandai `BELUM TERVERIFIKASI`. Jangan membuat ulang PEKAN dari README semata.

**Penting: ada dua jenis konsistensi:**
- **Control Panel RUDIMAH** dan seluruh **AppShell** harus konsisten lintas webapp sejak awal.
- **Area konten PEKAN existing** tetap dijaga sedekat mungkin dengan visual lama selama fase rebuild (*legacy visual parity*). Bila desain PEKAN bertentangan dengan shared design system, buat adaptor transisi lokal dan dokumentasi; harmonisasi baru boleh dilakukan dalam release desain terencana dan disetujui.

---

## 4. PHASE B: Domain design, architecture, & ADR

Implementasikan Core dan Modules terpisah.

**Core domain responsibilities:**
- User/account identity dan session security.
- Tenant/workspace onboarding, membership, switcher, lifecycle.
- App registry, entitlement, application launcher, global navigation.
- RBAC global/tenant/app/resource, Laravel Policies.
- MFA/OTP, notification delivery, audit logs, rate limiting.
- Shared layouts, design system, locale/theme settings.

**Module responsibilities:**
- `PEKAN`: semua verified legacy behavior dan fitur OCR baru, data finansial, reports, domain-specific UI.
- `App1`, `App2`: hanya skeleton terdaftar, authorized, page placeholder; jangan buat fungsionalitas spekulatif.

**Flow RUDIMAH:**
`login → (MFA sesuai policy) → pilih tenant → validasi membership → tampilkan app launcher menurut entitlement → buka modul → cek permission action dan ownership resource → query data tenant → render Inertia Vue page`.

**Flow request protected:**
`route → session/auth → recent MFA (bila diperlukan) → active tenant resolver → tenant membership → app entitlement → policy action → resource ownership → service/action → tenant-scoped repository/model → DB transaction (bila dibutuhkan) → Inertia/JSON response`.

Dokumentasi ADR wajib untuk pilihan: modular monolith; MariaDB shared tenancy; auth/MFA assurance; PEKAN visual parity; OCR provider; deploy/secrets; future extraction independent apps.

---

## 5. PHASE C: Multi-tenant & database architecture

### Data model inti (contoh target; sesuai implementasi nyata)
`users`, `tenants`, `tenant_memberships`, `applications`, `tenant_applications`, `role_assignments`/package-owned roles-permissions, `mfa_methods`, `mfa_challenges`, `mfa_delivery_attempts`, `recovery_codes`, `tenant_security_policies`, `security_audit_logs`, `sessions`.

PEKAN: **pertahankan schema yang diverifikasi dari legacy**, tambahkan `tenant_id` pada data tenant-owned melalui migration plan. Tambahan OCR seperti `receipts`, `receipt_items`, `ocr_jobs`, `ocr_results`, `ocr_corrections` hanya jika tidak menduplikasi model legacy.

**Awal implementasi:** MariaDB shared tenancy berlabel `tenant_id` pada tabel bisnis, dengan pemisahan DB logis `platform_db` dan `pekan_db` apabila cocok dengan source/schema sebenarnya; keduanya dapat berada pada satu MariaDB server. Jangan mengasumsikan FK lintas database selalu tepat; gunakan kontrak core dan rekonsiliasi sesuai kebutuhan. **Database-per-tenant bukan syarat awal**, tetapi siapkan extension path untuk dedicated tenant.

**Aturan isolasi wajib:**
- Query, validation (`exists/unique`), export, file, cache, queue, reports, search indexes, notifications: tenant-scoped.
- `tenant_id` tidak boleh dipercaya dari request; derive dari authenticated context yang tervalidasi.
- Global scope membantu, tetapi tidak menggantikan explicit ownership check / policy. Raw query tidak mendapatkan scope otomatis; tinjau setiap pemakaiannya.
- Saat switching tenant dan antar job queue, konteks tenant harus dibersihkan untuk mencegah bleed.
- Unique constraints harus memperhitungkan tenant (`tenant_id`, `slug` dll).
- `users` dapat menjadi global identity dan punya keanggotaan multi-tenant; otorisasi per tenant tidak boleh terbawa ke tenant lain.
- Format mata uang: simpan menggunakan DECIMAL fixed precision atau integer minor units; **jangan floating point untuk authoritative financial values**, jangan mengubah rounding logic PEKAN legacy tanpa persetujuan.
- Gunakan DB transactions, foreign keys dalam satu DB jika tepat, query indexes, pagination, audit trail untuk financial mutations.

Migration legacy: backup→inventory→field mapping→dry run→record reconciliation→tenant assignment approved→staging parity→cutover→rollback plan. Jangan reset password menggunakan metode tidak aman; jika hash incompatible gunakan reset terverifikasi. Jangan pernah `migrate:fresh` pada production.

---

## 6. PHASE D: Shared design system dan UX consistency

**WAJIB baca detail** `docs/DESIGN_SYSTEM.md`, `docs/UI_UX_GOVERNANCE.md`, dan `docs/FRONTEND_IMPLEMENTATION_CONTRACT.md`. Dokumen tersebut adalah kontrak non-negotiable untuk seluruh modul.

### 6.1 Identitas UI
- Brand: **RUDIMAH**.
- Label platform dan semua UI berbahasa Indonesia, locale `id-ID`, waktu `Asia/Jakarta`, format uang IDR.
- AppShell tunggal (global topbar, tenant switcher, app launcher, profile/security, module navigation, main content).
- UX global harus konsisten dari PEKAN ke App1/App2: posisi menu, grid, spacing, font, empty states, toast, modal, tabel, form, status, filter, responsive behavior, focus ring.

### 6.2 Implementasi design system
Buat:
- `resources/css/tokens.css`: CSS variables untuk semantic colors light/dark, typography, spacing, radii, shadow, z-index, motion, breakpoints.
- `resources/js/layouts/RudimahAppShell.vue` satu-satunya global shell.
- `resources/js/design-system/` shared `RdButton`, `RdInput`, `RdSelect`, `RdModal`, `RdTable`, `RdCard`, `RdPageHeader`, `RdTenantSwitcher`, `RdAppLauncher`, `RdToast`, `RdEmptyState`, `RdErrorState`, `RdMoney`, `RdDateTime`.
- Katalog komponen (Histoire/Storybook atau setara yang dipilih setelah compatibility check), examples plus keyboard controls.
- `docs/DESIGN_SYSTEM.md`, `docs/UI_UX_GOVERNANCE.md`, visual regression snapshots.

**Larangan:** UI library baru per module; CSS hex/font/spacing hardcoded di halaman; membuat layout/sidebar/modal global ganda; mengubah komponen shared diam-diam; dark mode di satu app saja; menyalin komponen yang sama dengan implementasi berbeda.

### 6.3 Accessibility & responsive
- Target WCAG 2.2 AA: color contrast, keyboard navigation, focus visible, label forms, accessible dialogs, semantic heading, no color-only status, reduced motion.
- Browser snapshots kritikal minimal di viewport 360x800, 768x1024, 1440x900; theme modes jika diaktifkan. Mask hanya nondeterministic content; jangan menyembunyikan perubahan UI nyata.
- Shared component changes memerlukan seluruh regression suite dan changelog.

### 6.4 Rule of UI change
Desain **tidak boleh berubah hanya karena agent memilih pendekatan UI baru**. Setiap perubahan global memerlukan ADR/RFC, screenshot before-after, impacted modules, accessibility review, automated tests, approval, update design-system version. PEKAN legacy redesign harus menjadi task eksplisit.

---

## 7. PHASE E: Security, identity, MFA, & RBAC

### 7.1 Autentikasi
- Laravel Fortify sessions, login/logout, reset password, verified email, protected registration, device sessions, revoke sessions, CSRF, HttpOnly/Secure cookies, same-site policy, login limits.
- `SUPER_ADMIN` platform vs `TENANT_OWNER`, `TENANT_ADMIN`, `APP_MANAGER`, `MEMBER`, `VIEWER` dengan permission scoped per tenant/app. Role adalah contoh, final disesuaikan kebutuhan.
- AuthN ≠ AuthZ: guard semua REST/Inertia/API/export/storage/OCR/job endpoints pada server; deny by default. Object ownership wajib diverifikasi.

### 7.2 MFA tier
- Untuk high-privilege: **Passkeys/WebAuthn (phishing-resistant) atau TOTP**; passkeys diprioritaskan. Cek fitur nyata Fortify versi terpasang.
- Email, Telegram, WhatsApp OTP adalah **delivery channels ber-assurance lebih rendah**; jangan menyediakan channel tersebut sebagai fallback yang melewati persyaratan faktor kuat pada admin.
- Faktor hanya dapat didaftarkan, digunakan, atau dialihkan ke tujuan yang sudah diverifikasi. Recovery codes sekali pakai, factor replacement melalui recent strong MFA + auditable recovery.

### 7.3 OTP rule
- CSPRNG 6–8 digit, TTL awal 5 menit, max 5 attempts, resend cooldown awal 60 detik; nilai final configurable dan diuji.
- Simpan OTP sebagai keyed HMAC/verifier yang aman, bukan plaintext; atomic consume mencegah reuse/race; challenge bound ke user+login transaction+purpose+tenant context bila relevan.
- Full authenticated session hanya sesudah MFA memenuhi policy; rate-limit per user/IP/destination/tenant; audit metadata tanpa kode OTP.
- Email via Laravel Mail; Telegram via official Bot API + verified chat_id/link token sekali pakai; WhatsApp via official Meta Business Cloud API + approved auth template/verified number/webhook validation.
- Provider unavailable = reject/offer faktor lain yang **memenuhi assurance policy**, bukan bypass MFA.
- Step-up verification untuk admin role change, faktor keamanan, financial export/critical actions, integration secret changes.

### 7.4 Web/infra security
- OWASP ASVS & multi-tenant security: SSRF, SQLi, XSS, CSRF, IDOR/BOLA, mass assignment, path traversal, insecure uploads, brute force, secret leakage, DoS.
- CSP + HSTS setelah HTTPS siap, TLS 1.2/1.3, proxy trust terbatas, error redaction, audit trail, restrictive CORS.
- Runtime non-root where possible, no Docker sock, MariaDB/Redis internal-only; secrets in protected files/Compose secrets, no default passwords; signed URLs only after ownership validation.
- Mandatory security tests and threat model di `docs/SECURITY.md`.

---

## 8. PHASE F: PEKAN rebuild feature-for-feature

Untuk **setiap** legacy feature:
1. Locate actual legacy route/view/service/queries; record baseline and screenshots.
2. Extract domain invariants, formats, calculations, validation, permissions, edge cases.
3. Implement Laravel routes, middleware, Form Requests, Policies, services/actions, models/migrations.
4. Implement Vue page using `RudimahAppShell`, shared components, approved PEKAN compatibility adapter when required.
5. Verify data output, UX interaction, keyboard and responsive behavior.
6. Add unit/feature/E2E/visual regression tests; update parity matrix with evidence.

Never mark feature complete because page screenshot looks right; behavioral and data parity must be verified. Do not modify business logic or financial rounding invisibly.

---

## 9. PHASE G: AI OCR struk PEKAN

Flow end-to-end:
`user upload foto/PDF → MIME+size+tenant authorization validation → private storage → create OCR job → Redis/Horizon OCR worker → provider adapter (AI vision) → structured JSON schema validation → normalize tanggal, format IDR, total, diskon, pajak → detect duplicate/inconsistent totals → preview hasil → edit/koreksi user → confirm → atomic save to transaction`.

Rules:
- AI **tidak mengesahkan transaksi otomatis**; human-in-the-loop required.
- Jaga asal-usul file/hasil AI, correction history, provider/cost metadata tanpa menyimpan rahasia.
- Tenant quotas (file/requests/cost), provider timeout, exponential backoff, idempotency, bounded concurrency, safe retries, malformed data handling.
- Receipt/AI content untrusted; jangan dijadikan system instructions, shell args, atau raw SQL.
- Model/provider configurable; perbandingkan akurasi untuk struk Indonesia sebelum provider final; sensitive data retention & consent policy.
- Semua UI OCR Bahasa Indonesia dan shared components.

---

## 10. PHASE H: Docker Compose & service topology

**Satu host awal**, layanan:
- `nginx`: 80/443 publik, static assets, TLS/security headers.
- `app`: Laravel/PHP-FPM; **tidak** direct public.
- `horizon`: queue groups `security`, `notifications`, `ocr`, `reports`, `default` sesuai kebutuhan.
- `scheduler`: Laravel Scheduler.
- `mariadb`: internal jika dipilih; volume persisten.
- `redis`: internal jika dipilih; queue/cache/session sesuai konfigurasi; atur memory eviction agar queue tidak hilang.
- `certbot`/ACME: certificate bootstrap & renewal yang terdokumentasi.
- `storage`: private application data, OCR receipts; external S3/MinIO optional.

Gunakan image version pinning yang masuk akal, Docker internal network, health checks, log rotation, resource constraints, graceful process shutdown, non-root, read-only mounts where possible, no Docker socket, reproducible multi-stage builds. Database dan Redis **tidak dipublish** ke publik secara default.

Folder wajib:
```
docker-compose.yml
Dockerfile
.env.example
.dockerignore
installer.sh
update.sh
uninstall.sh
backup.sh
restore.sh
status.sh
reconfigure.sh
docker/nginx/{http.conf,https.conf,security.conf}
docker/php/{php.ini,opcache.ini,www.conf}
docker/mariadb/custom.cnf
docker/redis/redis.conf
scripts/{renew-ssl.sh,preflight.sh,healthcheck.sh}
```

Docker Compose deployments tidak otomatis zero-downtime. Jelaskan maintenance windows jika lifecycle membutuhkan downtime.

---

## 11. PHASE I: Fully customizable interactive installer

`sudo ./installer.sh` wajib membuka wizard interaktif, dengan opsi `--config /path/install.conf --non-interactive`, `--dry-run`, dan `--help`. **Setiap input user harus benar-benar diterapkan**, bukan tampilan pura-pura.

Input yang dapat dipilih:
1. Identitas aplikasi, nama platform/domain/base URL, timezone, install directory, Compose project name.
2. Database **internal MariaDB (Docker)** atau **external MariaDB**, host/IP, port, **nama core DB**, **nama PEKAN DB**, username per DB, password per DB, admin provisioning credential bila diperlukan.
3. PHP-FPM, MariaDB, Redis worker resource performance profile: small/medium/large/custom; RAM, max connections, workers, PHP upload size, OCR concurrency.
4. HTTP/HTTPS host ports, bind address, safe internal Docker network. External DB port dapat berbeda; jangan expose internal DB/Redis tanpa alasan, bind loopback jika disetujui.
5. SSL Let's Encrypt automatic, DNS-01 advanced, custom cert, atau external reverse proxy. HTTP-01 membutuhkan **port publik 80**, walau host port aplikasi lainnya custom.
6. Admin bootstrap (secure one-time CLI/invitation, **tanpa default admin password**).
7. SMTP, email verification, Telegram Bot API, WhatsApp Business Cloud API, AI OCR provider, object storage (opsional dan dapat dikonfigurasi kemudian).
8. Preflight: OS support Ubuntu LTS/Debian/Rocky 9/AlmaLinux 9 yang diuji, disk/RAM/CPU, Docker, Compose, DNS, port conflict, connection tests, certificate challenge prerequisites.
9. Review summary dengan rahasia dimasking, konfirmasi default `No` sebelum mengubah host.
10. Install dependency containers, provision DB idempotent, migrations aman, start app/worker/scheduler/Nginx, setup TLS dengan bootstrap HTTP first, readiness, final URL + status tanpa rahasia.

Sediakan **generate secure random password** atau **manual entry hidden** untuk seluruh secret. Validasi database identifiers, host, path, port, special characters. Tidak boleh `eval`, `source` file tak terpercaya, atau SQL interpolasi user-supplied tanpa quoting aman. `umask 077`, secret permissions restrictive, logs ter-redact. Simpan config/keys antar update; jangan regen `APP_KEY` saat update. MariaDB init env hanya bootstrap pada datadir kosong: **tidak** otomatis mengganti user/password pada initialized volume.

`reconfigure.sh` adalah workflow terkontrol untuk mengganti domain/ports/SMTP/database connection/tuning setelah instalasi dengan preflight dan rollback configuration; perubahan DB password butuh prosedur rotasi credential yang aman.

---

## 12. PHASE J: SSL, backups, update, uninstall, & tuning

### TLS
- Nginx bootstrap tanpa langsung mereferensikan cert yang belum ada.
- Let's Encrypt staging mode uji terlebih dahulu; issuance real dengan user approval dan readiness check.
- Automatic renewal + Nginx reload successful-only; expiry monitoring.
- HTTP→HTTPS after cert success; custom host ports dan proxy path disinkronkan dengan callback URLs; HSTS preload tidak otomatis.

### update.sh
`lock → preflight → version compatibility → backup verified → image build/pull → config validation → safe forward-compatible migrations → controlled service replacement → queue drain/restart → health checks → record outcome → rollback guidance if required`.
**Dilarang** `docker compose down -v`, menghapus/uploads/DB, overwrite `.env`, regen APP_KEY, `migrate:fresh`, destructive seeding. Expand-contract schema changes untuk kompatibilitas; rollback app image tidak menjamin rollback DB changes.

### uninstall.sh
Default: hanya hapus container/network scoped project, **preserve semua data, backups, keys, certs**. `--purge-data` memerlukan explicit double confirmation + precise path/volume listing; jangan uninstall Docker Engine, jangan `docker system prune` unrestricted.

### backup/restore/status
- `backup.sh`: consistency-aware DB logical backup + tenant file storage + config manifest, encrypted, checksums, offsite optional, retention, verified restore testing.
- `restore.sh`: compatibility checks, backup current state, explicit confirmation, verify integrity and post-restore health, document data loss window.
- `status.sh`: services, health, DB/Redis, Horizon, queue failed/backlog, TLS expiry, disk, backup freshness, app version. Tidak tampilkan secrets.

### Performance
- PHP-FPM: OPcache, pool workers dari measured memory per worker, max requests, upload/time limits.
- Laravel: optimize config/routes/views, DB eager loading, idempotent queues, caching dengan tenant keys.
- MariaDB: buffer pool sesuai **RAM host yang dibagi dengan app/Redis**, slow query log, durable commit settings, proper indexes, connection limits.
- Redis: maxmemory dan persistence/eviction agar queue safe; separate queue/cache instances jika diperlukan.
- Nginx: rate limits, body limit, static asset caching, timeouts, proxy trust & headers.
- Profiles small/medium/large/custom, **tanpa klaim kapasitas pasti tanpa benchmark**. Metrics CPU, RSS, DB query latency, queue delays, OCR p95, failures.

---

## 13. PHASE K: Observability, quality, CI/CD

Implement:
- Structured logs dengan correlation ID dan tenant-aware metadata (redact private content).
- Laravel health & readiness, Horizon queue/failed jobs, DB slow queries, SMTP/Telegram/WhatsApp delivery status, AI OCR success/error/latency/cost, backup freshness, TLS expiry.
- Optional Prometheus/Grafana bila integrasi nyata diperlukan.
- CI: PHP lint, `php artisan test`, Pest/PHPUnit, Larastan, Pint, TS strict typecheck, ESLint, Vite production build, Playwright E2E+visual, `composer audit`, `npm audit`, Gitleaks, Semgrep, Trivy, Compose validation.
- Test installer/update/uninstall/backup/restore on disposable VM/container host environment, **jangan menguji destructive scripts di production**.

Critical tests:
1. Tenant A tidak bisa read/write/list/download/export/report/search/queue milik Tenant B.
2. Role Viewer tidak dapat POST/DELETE meskipun menggunakan HTTP manual.
3. Admin tanpa strong MFA tidak dapat bypass via email/WhatsApp OTP.
4. OTP expired/replay/parallel requests/rate limiting semuanya ditolak sesuai policy.
5. Invoice/struk OCR tidak menghasilkan transaksi sebelum user confirm; currency precision benar.
6. `update.sh` tidak menghapus tenant/receipts/uploads/keys; backup restore diuji.
7. Visual change RUDIMAH shared components tidak mengubah halaman modul tanpa approval.
8. Existing PEKAN business flow parity tests passing; source evidence documented.

---

## 14. Struktur repository yang disarankan

```
rudimah-platform/
├── AGENTS.md
├── app/
│   ├── Core/{Identity,Tenancy,Authorization,Applications,MFA,Audit,Notifications}/
│   ├── Modules/
│   │   ├── Pekan/{Http,Models,Services,Actions,Jobs,Policies,Database,Routes}/
│   │   ├── App1/
│   │   └── App2/
│   └── Shared/{Contracts,DTOs,Exceptions}/
├── bootstrap/
├── config/
├── database/{migrations,seeders,factories}/
├── resources/
│   ├── css/{app.css,tokens.css}
│   └── js/{design-system,layouts,modules,pages,composables,types}/
├── routes/
├── tests/{Unit,Feature,Browser,visual}/
├── docker/{nginx,php,mariadb,redis}/
├── scripts/
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DESIGN_SYSTEM.md
│   ├── UI_UX_GOVERNANCE.md
│   ├── FRONTEND_IMPLEMENTATION_CONTRACT.md
│   ├── SECURITY.md
│   ├── FEATURE_PARITY_MATRIX.md
│   ├── DATABASE_ERD.md
│   ├── MFA_SECURITY.md
│   ├── OCR_ARCHITECTURE.md
│   ├── INSTALLATION.md
│   ├── DEPLOYMENT.md
│   ├── UPDATE.md
│   ├── BACKUP_RESTORE.md
│   ├── TUNING.md
│   ├── OPERATIONS_RUNBOOK.md
│   └── TEST_REPORT.md
├── docker-compose.yml
├── Dockerfile
├── installer.sh
├── update.sh
├── uninstall.sh
├── backup.sh
├── restore.sh
├── reconfigure.sh
├── status.sh
└── .env.example
```

Tree adalah target awal; sesuaikan konvensi framework tanpa menghilangkan ownership/pemisahan.

---

## 15. Urutan pelaksanaan dan deliverables

**Stage 0 Discovery**: audit repo PEKAN, fitur/UI/data matrix, capture screenshots, data flow, top risks. Output factual documents only; jangan mengarang code legacy.

**Stage 1 Foundation**: Laravel/Vue/Inertia app, MariaDB/Redis Compose, shared app shell, tokens, `Rd*` components, test harness.

**Stage 2 Core & Tenancy**: users, tenants, memberships, app registry/launcher, policy enforcement, test two-tenant isolation.

**Stage 3 Identity & MFA**: Fortify, Passkeys/TOTP, OTP channels, step-up, recovery, audit logs, abuse controls.

**Stage 4 PEKAN**: rebuild feature-by-feature dengan behavior/data/UI parity, migration mapping.

**Stage 5 OCR**: upload→queue→provider→normalize→manual review→final transaction, robust tests.

**Stage 6 DevOps**: fully configurable installer, Let's Encrypt, update/uninstall/backup/restore/status, tuning, staging failure testing.

**Stage 7 Security & Quality**: SAST/SCA, tenant negative tests, UX/accessibility/visual regression, load/restore testing, documentation.

**Stage 8 Staging & Controlled Cutover**: data migration dry-run, UAT, deploy plan, freeze/rollback, post-cutover monitoring.

Setiap stage buat laporan: files changed, verified legacy evidence, migrations, route changes, tests executed/results, known issues, user-visible difference, ADR/design system updates. Jangan menampilkan seolah semua stage selesai jika baru fase awal.

---

## 16. Release gates / Definition of Done

Sistem hanya dianggap selesai bila:
- [ ] Legacy PEKAN routes, business rules, screens, data schema di-audit dengan bukti file/commit.
- [ ] Feature parity matrix seluruh fitur in-scope `Disetujui parity`.
- [ ] Tidak ada perubahan UI/UX PEKAN tanpa approval atau dokumentasi transisi.
- [ ] Seluruh RUDIMAH/PEKAN/AppN memakai **satu** shell, tokens dan shared components (kecuali adapater legacy terdokumentasi).
- [ ] UI Indonesia, responsive, WCAG 2.2 AA target diuji; visual regression accepted.
- [ ] Tenant isolation negatif A/B lulus termasuk file/cache/queue/report.
- [ ] RBAC app/resource lulus dan deny-by-default.
- [ ] MFA privilege assurance, OTP replay/expiry, recovery/step-up aman.
- [ ] OCR human approval dan precision finansial diuji.
- [ ] Database migrations aman, backup/restore berhasil diuji.
- [ ] `docker-compose.yml`, `installer.sh`, `update.sh`, `uninstall.sh`, `backup.sh`, `restore.sh`, `status.sh`, `reconfigure.sh` benar-benar berfungsi di environment pengujian.
- [ ] Manual/custom DB names/users/password/ports/SSL/profile di installer benar-benar diterapkan.
- [ ] SSL renewal, PHP/MariaDB/Redis/Nginx performance configuration diverifikasi.
- [ ] No open Critical/High security issues tanpa explicit risk acceptance + mitigation.
- [ ] Monitoring, audit, CI/CD, runbook, operator docs lengkap dan sesuai source.

**Tidak boleh melewati gate dengan laporan fiktif.**

---

## 17. Instruksi pertama saat prompt dijalankan

1. Konfirmasi identitas produk: `RUDIMAH: Ruang Digital Manajemen Harian`, dengan PEKAN sebagai modul pertama.
2. Baca `AGENTS.md` dan seluruh governance docs.
3. Clone dan inspect PEKAN (jika akses memungkinkan), catat commit SHA dan semua fakta source.
4. Buat `docs/LEGACY_ANALYSIS.md`, `docs/FEATURE_PARITY_MATRIX.md`, `docs/LEGACY_UI_BASELINE.md`, `docs/LEGACY_DATABASE_ERD.md` dan daftar risiko.
5. Beri usulan ADR, dependency matrix, serta urutan pengerjaan yang bisa diverifikasi.
6. Mulai Stage 1 hanya setelah discovery menyediakan bukti yang cukup; bangun foundation yang diuji, bukan mass-generate seluruh aplikasi tanpa validasi.
7. Terus bekerja di tahap yang bisa dikerjakan dalam sesi tersebut; bila diblokir kredensial atau akses repo, laporkan apa yang tidak bisa diverifikasi, tanpa membuat data palsu.

### Desired final result
Sebuah **RUDIMAH** self-hosted platform multi-tenant, multi-app, aman dan dikelola lewat Docker Compose, dengan PEKAN direbuild secara fungsional dan visual berdasarkan legacy yang diverifikasi; foundation UI/UX bersama yang mencegah perubahan tampilan inkonsisten pada App1, App2, dan semua webapp berikutnya.

---

## Referensi resmi
- Laravel starter kits: https://laravel.com/starter-kits
- Laravel production deployment: https://laravel.com/framework/docs/deployment
- Laravel multitenancy (Spatie): https://spatie.be/docs/laravel-multitenancy/v4/introduction
- OWASP multi-tenant security: https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html
- OWASP ASVS: https://owasp.org/www-project-application-security-verification-standard/
- NIST authentication: https://pages.nist.gov/800-63-4/sp800-63b.html
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
- Docker Compose secrets: https://docs.docker.com/compose/how-tos/use-secrets/
- Let's Encrypt challenge types: https://letsencrypt.org/docs/challenge-types/
