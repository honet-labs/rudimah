# RUDIMAH: Mandatory Instructions for AI Coding Agents

**Always read first:** `MASTER_PROMPT_RUDIMAH.md`, `docs/ARCHITECTURE.md`, `docs/DESIGN_SYSTEM.md`, `docs/UI_UX_GOVERNANCE.md`, `docs/FRONTEND_IMPLEMENTATION_CONTRACT.md`, `docs/SECURITY.md`.

## Non-negotiable
1. Nama platform: **RUDIMAH (Ruang Digital Manajemen Harian)**; semua UI berbahasa Indonesia (id-ID).
2. PEKAN source: `https://github.com/honet-labs/pekan`. **Jangan mengarang fitur/schema/UI existing.** Inspeksi repository sebenarnya, laporkan akses yang gagal dan fakta yang belum diketahui.
3. Implementasi di repository proyek **baru**; jangan memodifikasi PEKAN existing tanpa instruksi eksplisit.
4. Tech direction: Laravel 13/PHP≥8.3, Vue 3 TS + Inertia 3 + Tailwind 4, MariaDB, Redis, Docker Compose; verifikasi dukungan versi/paket.
5. Gunakan **shared shell, tokens dan components** platform dari semua webapp. Jangan bebas ganti CSS, font, warna, ikon, breakpoint, layout.
6. UI PEKAN existing = baseline legacy parity. Shell RUDIMAH baru tetap konsisten; adopsi design system di area PEKAN hanya dengan prosedur migrasi yang didokumentasikan dan persetujuan.
7. Semua view punya loading, empty, error, forbidden; semua UI text Bahasa Indonesia; aksesibilitas WCAG 2.2 AA; responsive tests.
8. Modul tidak boleh menduplikasi identitas, MFA, tenant management, global navigation, RBAC.
9. Authorization server-side minimal: auth, MFA, tenant membership, entitlement app, permission action, object ownership; negative test tenant A/B.
10. MariaDB core+PEKAN dengan nama DB/user/password/host/port yang dapat dikonfigurasi installer. Jangan hardcode dan jangan klaim membutuhkan PostgreSQL.
11. Multi-tenant shared DB `tenant_id` dengan fail-closed access, tenant-scoped jobs/cache/storage; dedicated tenant DB nanti bila beralasan.
12. MFA: Passkeys/TOTP prioritas, OTP Email/Telegram/WhatsApp opsional dan tidak boleh menjadi bypass policy admin.
13. OCR hanya usulkan transaksi, pengguna memverifikasi sebelum simpan final, precision uang wajib terjaga.
14. Docker Compose deployment lengkap: installer/update/uninstall/backup/restore/status/reconfigure, SSL Let's Encrypt dan internal-only MariaDB/Redis. Installer fully customizable dan non-destructive.
15. Buat unit/feature/e2e/security/visual tests. Jangan klaim tested sebelum benar-benar dijalankan. Security, performance & UI changes butuh dokumentasi dan review.
16. Jangan menjalankan destructive scripts, migrasi produksi, mengirim email/WhatsApp/Telegram live, menghapus data, atau deploy production tanpa izin operasional yang sesuai.
17. Buat output per tahap: temuan faktual, perubahan file, perintah & hasil testing, gap, risiko, langkah lanjut.
18. **Desain Bersih & Profesional (No Emojis / Minimal Ikon Berwarna)**: Dilarang menggunakan emoticon/emoji unicode berwarna (seperti ✉️, 💬, 🤖, 📊, 💳, 💰, 🚀, dll) maupun ikon warna-warni yang berlebihan pada antarmuka RUDIMAH. Gunakan tipografi bersih, hierarki teks yang jelas, token netral, dan badge status semantik resmi platform. Penggunaan ikon harus diminimalkan hanya untuk aksi esensial dan wajib berupa outline/monokromatis netral.
19. **Bebas Dead Code (Dead Code Hygiene)**: Dilarang keras membiarkan dead code tersisa dalam codebase. Saat menghapus, merestrukturisasi, atau membatalkan suatu fitur, agen wajib membersihkan seluruh residunya: hapus unused imports, unused variables, unused props, unused computed properties, dead routes, dead controller actions, dan unused database queries/eager-loads (`with(...)`). Jangan meninggalkan kode mati atau komentar blok kode basi.
20. **Selalu Commit & Push ke Remote Repository (Always Push to Remote)**: Setiap kali menyelesaikan instruksi perbaikan kode, penambahan fitur, atau refaktorisasi, agen wajib membuat git commit dengan pesan deskriptif dan langsung melakukan push ke remote repository (`git push origin <branch>`) tanpa harus menunggu instruksi berulang dari pengguna.

## Mandatory PR gate
- [ ] Feature parity mapped and verified where affected.
- [ ] No new custom UI patterns bypassing design system.
- [ ] Antarmuka bersih tanpa emoji/emoticon atau icon berwarna yang berlebihan.
- [ ] Bebas dari dead code (tidak ada unused imports, dead variables/props, dead queries, atau dead endpoints).
- [ ] Ter-commit rapi dan ter-push ke remote repository.
- [ ] Screenshot and UI regression evidence.
- [ ] Tests: tenant isolation, permission & session where applicable.
- [ ] Security + secrets checks.
- [ ] Migrations backward compatible or deployment plan recorded.
- [ ] Docs, tokens/component changelog and ADR updated.

## Coding conventions
Backend controllers thin; form requests validate; policies authorize; services/actions own logic; Eloquent models represent data; DB transaction for financial state; queues idempotent; typed DTOs where useful; bersihkan unused eager loading dan dead queries. Frontend Vue Composition API + TS strict; wrappers `Rd*`; tokens dari shared CSS; isolasi module CSS; hindari proliferasi library UI; utamakan tipografi dan hierarki teks bersih di atas dekorasi ikon visual; no emoji di teks UI; bersihkan unused imports/props/refs; shared reusable patterns. Avoid needless abstraction and overengineering.
