# ADR 008: Arsitektur Asisten Cerdas AI Multi-Tenant dan Batasan Keamanan Data

## Status
Diterima

## Konteks
Platform RUDIMAH memerlukan dua fungsi utama kecerdasan buatan (AI):
1. Ekstraksi visual kuitansi/struk belanja (AI OCR) pada modul keuangan PEKAN.
2. Asisten cerdas interaktif (AI Assistant) berbasis model bahasa besar (LLM) di setiap ruang kerja (tenant), dengan dukungan penyedia eksternal seperti Google Gemini dan OpenAI, maupun model mandiri seperti Hermes Agent dan OpenClaw.

Tantangan arsitektur terpenting adalah pencegahan kebocoran data antar-tenant (*cross-tenant data leakage*). Apabila Pengguna di Ruang Kerja A bertanya kepada asisten mengenai data apa pun yang berkaitan dengan Ruang Kerja B, asisten harus secara teknis tidak dapat mengakses data tersebut dan secara tegas menolak permintaan tersebut.

## Keputusan
1. **Dua Fungsi AI Terpisah**:
   - **Fungsi 1 (AI OCR)**: Dikelola oleh `ReceiptOcrService` menggunakan vision model adapter untuk mengekstrak draf transaksi struk belanja. Prinsip *Human in the Loop* (ADR-005) tetap ditegakkan secara mutlak (tidak ada *auto-commit*).
   - **Fungsi 2 (AI Assistant per Tenant)**: Dikelola oleh `TenantAssistantService` dengan multi-provider adapter (`GeminiProvider`, `OpenAiProvider`, `HermesProvider`, `OpenClawProvider`, dan `LocalMockProvider`).

2. **Pertahanan Berlapis Isolasi Tenant (Multi-Layer Defense)**:
   - **Lapisan 1 (Middleware Enforced Context)**: Rute asisten dilindungi oleh `ResolveTenantMiddleware` yang memvalidasi bahwa sesi pengguna merupakan anggota aktif dari tenant tersebut.
   - **Lapisan 2 (Fail-Closed Database Scoping)**: Model percakapan (`AiConversation`) dan pesan (`AiMessage`) menerapkan `TenantScope`. Query tanpa konteks tenant yang sah akan langsung gagal tertutup (*fail closed*).
   - **Lapisan 3 (Retrieval Data Builder Terisolasi)**: `TenantFinancialContextBuilder` secara eksplisit hanya mengeksekusi query database dengan filter `where('tenant_id', TenantContext::id())`. Tidak ada mekanisme bagi asisten untuk menjangkau rekaman di luar tenant aktif.
   - **Lapisan 4 (Guardrails Prompt Sistem)**: Prompt sistem menginstruksikan asisten bahwa ia hanya memiliki wewenang untuk ruang kerja aktif dan diwajibkan menolak dengan tegas setiap pertanyaan yang meminta data ruang kerja lain.
   - **Lapisan 5 (Enkripsi Kredensial)**: Kunci API khusus yang diatur oleh administrator ruang kerja disimpan secara terenkripsi (`Crypt::encryptString`).

## Konsekuensi
Positif:
* Menjamin perlindungan privasi finansial dan operasional antar-pengguna dan antar-organisasi secara mutlak.
* Fleksibilitas tinggi dalam memilih model AI: pengguna dapat menggunakan model komersial (Gemini, OpenAI) atau model mandiri privat (Hermes, OpenClaw).
* Riwayat percakapan tersimpan rapi dan terisolasi per pengguna dan per ruang kerja.

Negatif:
* Asisten tidak dapat menjawab pertanyaan yang bersifat lintas ruang kerja (misalnya konsolidasi laporan antar-organisasi), yang memang sengaja dibatasi demi keamanan.
