# ADR 003: Tingkat Kepercayaan Otentikasi dan Kebijakan Multi-Factor Authentication

## Status
Diterima

## Konteks
Sistem mengelola data keuangan pribadi yang sangat sensitif sehingga memerlukan mekanisme autentikasi berlapis yang membedakan tingkat privilese pengguna.

## Keputusan
1. Autentikasi akun utama menggunakan Laravel Fortify dengan hashing Argon2id atau Bcrypt berstandar tinggi.
2. Kebijakan MFA berjenjang (Tiered Assurance):
   * Peran Berprivilese Tinggi (Tenant Owner, Platform Admin, Tenant Admin): Wajib menggunakan Passkeys (FIDO2/WebAuthn) atau TOTP berbasis RFC 6238.
   * Peran Biasa: Dapat menggunakan TOTP, atau saluran ber-assurance lebih rendah seperti OTP Email, Telegram Bot, dan WhatsApp.
3. Larangan Fallback Tidak Aman: Kanal pengiriman ber-assurance rendah (Email, Telegram, WhatsApp) tidak boleh diizinkan untuk melewati (bypass) kewajiban Passkey/TOTP bagi peran administrator.
4. Token OTP disimpan dalam bentuk verifier hash terenkripsi dengan masa berlaku 5 menit, maksimal 5 kali percobaan, dan cooldown kirim ulang 60 detik.

## Konsekuensi
Positif:
* Perlindungan kuat terhadap serangan pengambilalihan akun dan phishing.
* Pemenuhan kepatuhan terhadap standar keamanan NIST 800-63B.

Negatif:
* Memerlukan verifikasi kepemilikan nomor telepon atau chat ID sebelum saluran OTP sekunder dapat diaktifkan.
