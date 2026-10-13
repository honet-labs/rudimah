# ADR 007: Jalur Ekstraksi Aplikasi Eksternal dan Integrasi SSO

## Status
Diterima

## Konteks
Di masa mendatang, aplikasi tertentu mungkin memerlukan infrastruktur independen atau basis data terpisah di luar modular monolith RUDIMAH.

## Keputusan
1. Menyiapkan pola Single Sign-On (SSO) berbasis protokol OAuth2 / OpenID Connect (OIDC) melalui Laravel Passport atau Fortify terstandarisasi.
2. Melarang pembagian sesi mentah (raw database session sharing) antar aplikasi yang dipisahkan ke repositori eksternal.
3. Aplikasi eksternal harus bertindak sebagai Resource Server yang memverifikasi token bertanda tangan kriptografis dari Core RUDIMAH.

## Konsekuensi
Positif:
* Memungkinkan evolusi arsitektur bertahap tanpa membongkar arsitektur modular monolith yang sudah stabil.

Negatif:
* Membutuhkan sinkronisasi metadata profil pengguna saat migrasi ke deployment terdistribusi.
