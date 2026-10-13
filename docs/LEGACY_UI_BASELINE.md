# Baseline Antarmuka Pengguna (UI) Legacy PEKAN

Dokumen ini mendokumentasikan spesifikasi visual, tata letak, dan perilaku responsif dari antarmuka pengguna legacy PEKAN sebagai acuan rekontruksi visual di platform RUDIMAH.

## 1. Tata Letak dan Grid
* Navigasi Desktop (Lebar > 1024px): Bilah sisi tetap (sticky sidebar) selebar 240px di sisi kiri, dengan area konten utama di sisi kanan berkisi fleksibel hingga batas maksimal 1440px.
* Navigasi Tablet (768px hingga 1024px): Bilah sisi dapat diringkas dengan transisi mulus dan ikon netral.
* Navigasi Seluler (< 768px): Bilah atas (topbar) dengan tombol navigasi drawer laci yang dapat ditutup melalui tombol Escape atau klik area luar.

## 2. Tipografi dan Warna Baseline
* Font Utama: Plus Jakarta Sans atau fallback sans-serif sistem.
* Ukuran Huruf: Judul halaman 24px hingga 28px tebal, judul kartu 18px hingga 20px semi-tebal, teks isi 14px reguler, label kolom dan status 12px hingga 13px medium.
* Skema Warna:
  * Latar Belakang: `#F8FAFC` (permukaan cerah netral)
  * Kartu dan Panel: `#FFFFFF` dengan bayangan lembut
  * Teks Utama: `#0F172A`
  * Teks Sekunder: `#475569`
  * Aksen Utama: `#0F766E` (warna teal khas PEKAN)
  * Border dan Pemisah: `#CBD5E1`
  * Status Berhasil: `#15803D`
  * Status Peringatan: `#B45309`
  * Status Bahaya / Kesalahan: `#B91C1C`

## 3. Spesifikasi Komponen Halaman Kritis
* Tabel Transaksi: Mendukung perataan angka ke sisi kanan, pemformatan rupiah id-ID, filter rentang tanggal dinamis, dan status indikator baris.
* Kartu Anggaran: Menampilkan persentase keterpakaian dengan bilah progres netral berindikator teks ambang batas tanpa mengandalkan warna semata.
* Kartu Tabungan: Menampilkan akumulasi dana terkumpul terhadap total target dengan rincian tenggat waktu.
* Pemindai Struk: Area seret lepas berkas dengan indikator ukuran maksimal, pratinjau gambar monokrom, dan tabel rincian item hasil ekstraksi.

## 4. Keadaan Tampilan Standar (State Management)
* Sedang Memuat (Loading State): Komponen skeleton abu-abu netral dengan animasi kilau halus.
* Data Kosong (Empty State): Ilustrasi garis minimalis dengan pesan kontekstual dan tombol aksi primer.
* Kesalahan (Error State): Panel peringatan dengan penjelasan sebab kesalahan dan tombol coba lagi.
* Akses Ditolak (Forbidden State): Halaman resmi 403 dengan tombol kembali ke dasbor utama.
