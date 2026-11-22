# ADR-009: Integritas struk, tanggal kalender, dan review mobile

## Status
Diterima untuk implementasi berdasarkan permintaan eksplisit pengguna, 23 November 2026. Validasi perangkat fisik dan penyedia AI produksi tetap diperlukan.

## Keputusan
- Total OCR yang tercetak tidak ditimpa oleh jumlah item saat preview dimuat. Pengguna boleh mengoreksi angka; backend menolak konfirmasi jika subtotal + pajak + biaya layanan − diskon tidak sama dengan total yang diverifikasi.
- Diskon/pajak/biaya layanan dipertahankan ketika mengedit transaksi. Konfirmasi scan dikunci dalam transaksi database PEKAN dan scan yang sudah dikonfirmasi tidak dapat disimpan lagi.
- Semua adapter vision komersial menggunakan normalizer yang sama. Simpan teks tanggal mentah, baca format Indonesia hari-bulan-tahun, validasi kalender, dan jangan mengganti tanggal yang hilang dengan hari ini.
- Tanggal bisnis diserialisasi sebagai `YYYY-MM-DD`, bukan instant UTC. Presentasi tanggal/waktu menggunakan Asia/Jakarta, termasuk preview tanggal tekstual yang tidak bergantung format date-picker OS.
- Lampiran mobile dikirim multipart dan disalin dari temporary upload tanpa memindahkannya sebelum OCR selesai. Lampiran scan untuk konfirmasi diambil dari rekaman server tenant aktif, bukan URL dari browser.
- Asisten memakai agregat seluruh riwayat tenant dan pencarian/paginasi rincian 50 transaksi per halaman. Total filter mencakup semua halaman. Tren dibatasi 240 kelompok bulan/jenis dan cakupannya dijelaskan, agar prompt tidak mengaku membaca baris yang tidak dimuat.
- UI menggunakan `Ruang`; ikon informasi mendahului judul. Kontrak internal tenant, URL, nama tabel dan nama ruang pengguna tetap.

## Konsekuensi dan batasan
- Tidak ada migrasi atau koreksi otomatis data transaksi lama. Struk tidak terbaca harus diverifikasi manusia.
- Perbaikan tidak menjamin OCR selalu akurat; uji fixture bukan benchmark model vision nyata. Retry hanya sekali untuk connection failure/HTTP 429, 502, 503, 504, bukan untuk mutasi finansial.
- Penyimpanan lampiran publik yang sudah ada belum dimigrasikan ke storage privat dalam perubahan ini; risiko tersebut tetap memerlukan pekerjaan terpisah.
- Jalur OCR chat eksternal legacy masih terpisah dari konfirmasi web. ADR ini tidak mengklaim jalur chat sudah menerapkan human-in-the-loop.
