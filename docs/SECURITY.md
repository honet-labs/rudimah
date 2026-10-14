# RUDIMAH Security Baseline

**Sifat dokumen:** mandatory technical acceptance rules untuk Core, PEKAN, dan modul lain. Bukan sertifikasi keamanan.

## 1. Threat model
Aset: kredensial, sessions, MFA seeds, tenant membership, receipt images, transaksi finansial, AI provider keys, database backups, pengguna admin dan otoritas tenant. Aktor: pengguna sah satu/multi tenant, tenant admin, platform admin, attacker tak terautentikasi, insider, provider integrasi dan compromised dependency. Trust boundaries: browser→Nginx→Laravel; tenant A→B; Core→modules; worker→DB; worker→external AI; platform→messaging provider; storage→download.

## 2. Authorization rules
- Deny by default; hierarchical platform/tenant/application/resource scopes.
- `tenant_id` dari browser tidak dipercaya. Selalu resolve dari authenticated tenant context.
- Semua route termasuk read, export, batch operations, downloads, background jobs, websockets dan API dicek auth/MFA/tenant/entitlement/policy/ownership.
- Mass-assignment guarded, input whitelisting, resource ownership check after lookup. Cache, search indexes, queue payload, notifications, storage path, report export memuat tenant context dan tidak bocor.
- Jangan anggap hidden UI sama dengan authorization.

## 3. Identity/MFA policy
- Login gunakan secure sessions dan Fortify sesuai dukungan yang sudah diverifikasi.
- Passkey/WebAuthn diprioritaskan untuk akun privileged, TOTP sebagai alternatif kuat (namun tidak phishing-resistant); email/Telegram/WhatsApp OTP sebagai channel tambahan ber-assurance lebih rendah.
- Jangan izinkan fallback email/WhatsApp/Telegram untuk melewati requirement Passkey/TOTP seorang admin.
- Provider messaging hanya mengirim ke channel yang **sudah diverifikasi dan linked**; re-link memerlukan recent strong authentication.
- OTP acak CSPRNG, sekali pakai, ttl awal 5 menit, max 5 percobaan, resend cooldown 60 detik, atomic consume; rate-limit IP/user/tenant/destination dan biaya provider. Simpan HMAC keyed atas OTP, tidak plaintext/log; jangan bocorkan via referrer/URL/API. Tidak ada privileged session sebelum MFA selesai.
- Recovery codes satu kali, tersimpan aman; rescue flow anti-account takeover; audit factor add/remove/reset, session revocation.
- Step-up auth untuk ganti faktor, admin, ekspor sensitif, pengaturan integration/financial action tertentu. Verifikasi terikat operasi, tidak reusable lintas tujuan.

## 4. Web/API & frontend
- CSRF untuk session actions, HttpOnly + Secure cookie + SameSite sesuai flow; session regeneration. TLS only; strict allowed origins. Rate limits + bot protection wajar tanpa menyebabkan denial of service akibat IP spoofing.
- Output encoding (Vue escaping), render HTML mentah hanya jika melalui sanitization terpercaya. CSP tidak boleh dibypass massal dengan unsafe-inline script tanpa alasan.
- Validasi Input FormRequest, query parameterization, tidak ada command interpolation; SSRF block private/metadata IP jika user dapat memilih URL dan allowlist provider.
- Error API tidak membocorkan stack trace, SQL, secrets atau lokasi filesystem.

## 5. File, OCR, dan AI
- Validate MIME by content, extension allowlist, max size/pages, image parsing limits; scanning malware bila feasible; file storage private, signed temporary authorization, per-tenant prefix + ownership enforced in app.
- OCR output untrusted: schema parse, bounds check, duplicate checking, human approval, audit trail. LLM output tidak pernah menjalankan instruksi/system commands atau menulis transaksi tanpa validasi.
- Tenant AI Assistant Isolation:
  - Asisten AI di setiap ruang kerja terikat mutlak pada `TenantContext::id()`.
  - Context builder data finansial hanya melakukan query dengan filter tenant aktif via `TenantScope`.
  - Jika pengguna meminta informasi mengenai ruang kerja/tenant lain atau memanipulasi prompt (*prompt injection*), asisten wajib menolak secara tegas demi keamanan data.
  - Percakapan dan pesan (`ai_conversations`, `ai_messages`) terisolasi per tenant dan per pengguna; akses silang menghasilkan HTTP 403/404.
  - Kunci API kustom yang dikonfigurasi per tenant wajib disimpan terenkripsi (`Crypt::encryptString`).
- Protect provider prompts: minimalkan PII, prinsip least data, consent and retention policy, no unrestricted production receipt upload to unapproved providers.
- Protect cache and logs: redact images, raw OCR content and credentials. Encrypt sensitive backups, key rotation, restore drills.

## 6. Container & operations
- Compose network tersegmentasi, MariaDB/Redis internal-only, least privilege, avoid root, no Docker socket mount; secrets via protected files/Compose secrets, never embedded in Dockerfile or Git.
- Installer no hardcoded passwords, `umask 077`, validate values and escaping, no `eval`, no `source` untrusted config; never leak passwords in logs/process args.
- Updates non-destructive: preserve secrets/data, validate backups, controlled migrations, rollback plan. Uninstall leaves data by default.
- TLS issuance & renewal, staging test, verify domain/port 80 requirements for HTTP-01; strict HTTPS and privacy headers.

## 7. Security verification gates
- Tenant A → Tenant B attempts GET/POST/PUT/DELETE, file download, cache reads, background job, report export rejected.
- Role escalation, reusing OTP, wrong channel, bad signature, race/parallel verification, session fixation, revoked session, reset factor abuse rejected.
- Scanner minimum: `composer audit`, `npm audit`, Gitleaks, Semgrep, Trivy, Larastan + tests; human review for business authorization.
- No unresolved Critical/High without explicit risk acceptance and mitigation.
- Security review required for every new external integration, data export endpoint, and privileged action.

## 8. Referensi
- https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html
- https://owasp.org/www-project-application-security-verification-standard/
- https://pages.nist.gov/800-63-4/sp800-63b.html
- https://docs.docker.com/compose/how-tos/use-secrets/
