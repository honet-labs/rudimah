# ADR 001: Pilihan Arsitektur Modular Monolith untuk Platform RUDIMAH

## Status
Diterima

## Konteks
Platform RUDIMAH dirancang sebagai portal manajemen harian terpadu yang akan menampung aplikasi keuangan PEKAN sebagai modul pertama serta aplikasi lain di masa depan. Dibutuhkan arsitektur yang mudah dirawat untuk deployment self-hosted di satu server tanpa overhead orkestrasi microservices yang berlebihan, namun tetap menjaga isolasi batas domain antara modul platform dan modul aplikasi.

## Keputusan
Memilih arsitektur Laravel Modular Monolith dengan Vue 3 Composition API, TypeScript, dan Inertia 3.
1. Direktori `app/Core` mengelola identitas, tenancy, otorisasi, MFA, audit logging, dan katalog aplikasi.
2. Direktori `app/Modules/Pekan` mengelola seluruh domain bisnis keuangan.
3. Direktori `app/Modules/App1` dan `app/Modules/App2` disiapkan sebagai modul placeholder terdaftar.
4. Komunikasi antarmodul dilakukan melalui kontrak layanan (Service Contracts) dan event berstatus decoupled.

## Konsekuensi
Positif:
* Siklus deployment terpadu dalam satu image Docker dan satu prosesor antrean.
* Konsistensi transaksi database dan pengelolaan cache Redis yang terintegrasi.
* Efisiensi pemakaian memori server pada skenario self-hosted.

Negatif:
* Memerlukan disiplin ketat agar modul aplikasi tidak melakukan query langsung ke tabel internal modul platform tanpa melalui kontrak layanan.
