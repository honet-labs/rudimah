# Perbaikan mobile, struk, dan konteks asisten — 23 November 2026

## Temuan dan perubahan
- Form tambah transaksi sebelumnya mengabaikan event pemilih lampiran. Tambah/edit kini mengirim file multipart, mempertahankan lampiran lama yang sah, menampilkan validasi, dan server menyimpan salinan file berdasarkan MIME. Pemilih file dapat memilih ulang file yang sama dan tidak memicu click rekursif.
- Receipt preview sebelumnya menghitung ulang total dari item saat hasil dimuat; edit transaksi juga mengabaikan diskon/pajak. Preview mempertahankan total tercetak; edit mempertahankan komponen nominal. Contoh regresi: subtotal Rp48.500 − diskon Rp3.500 = total tersimpan Rp45.000.
- Normalisasi empat provider vision disatukan, termasuk Rupiah dengan separator, item gratis, tanggal mentah Indonesia, dan tanggal invalid. Tanggal kosong tidak diganti hari ini. Gambar tidak lagi dikompresi agresif dua kali ke 1280px; batas 2560px dan kualitas JPEG lebih tinggi mempertahankan teks kecil. Batas output vision dinaikkan dan gangguan provider sementara mendapat satu retry.
- Konfirmasi web memvalidasi formula dan tanggal, mengambil lampiran dari scan server tenant aktif, serta menolak penggunaan scan tenant lain atau scan yang sudah disimpan.
- Asisten sebelumnya hanya mendapat 10 transaksi terbaru. Agregat mencakup seluruh riwayat; rincian berisi kategori, rekening, merchant, item, pajak, diskon, dan catatan, dengan filter tanggal/bulan/tahun, kata kunci dan halaman. Contoh: `transaksi "bensin" November 2026 halaman 2`. Rincian dibatasi 50 per halaman; agregat tidak dihitung dari halaman tersebut saja.
- Copy UI `Ruang`; ikon informasi sebelum judul pada komponen bersama dan header kustom. Tidak mengganti URL, schema, slug, atau nama ruang pengguna.

## Pengujian yang dijalankan
- PHP 8.3.37 / Laravel 12.73.0 / PHPUnit 11.5.56. Dependensi pengujian disiapkan lokal/temporer, tanpa deployment atau koneksi provider AI nyata.
- Suite baru: `tests/Unit/ReceiptDataNormalizerTest.php` + `tests/Feature/ReceiptWorkflowRegressionTest.php`: **13 tes, 67 assertions, lulus**. Feature test menggunakan dua SQLite in-memory, seluruh migrasi hanya di database disposable, dan HTTP fake dengan larangan stray request.
- Kasus: tanggal Indonesia/invalid/leap year, separator Rupiah, total mismatch, koreksi tanggal, upload tanpa ekstensi, lampiran fisik tersimpan, draft OCR tanpa transaksi, retry 503, total/diskon/tanggal setelah konfirmasi/edit, submit ganda, scan lintas tenant, dan pencarian transaksi lama/paginasi tanpa data tenant lain.
- Build Vite lulus; masih ada warning ukuran chunk besar bawaan aplikasi. `publicDir: false` mencegah public/build menyalin direktori publik ke dirinya sendiri.
- Browser integration menjalankan bundle production Vue/Inertia dengan API fixture deterministik: multipart upload mobile, preview→payload konfirmasi, perubahan diskon/pajak, tanggal kosong/mismatch yang memblokir simpan, tanggal kalender di timezone America/Los_Angeles, urutan ikon, serta overflow 360/768/1440px. Screenshot light/dark mencakup PEKAN, pemilih ruang, App1 dan App2.
- Suite lama tidak seluruhnya hijau: baseline commit `d27f520` memiliki **4 errors + 4 failures pada 36 tes** di environment ini (database MariaDB tidak tersedia pada test lama; dua assertion parser AI dan satu key parser yang tidak sesuai). Hasil yang sama ditemukan pada salinan baseline terpisah. Jangan menganggap seluruh release gates telah lulus.
- Suite gabungan terbaru: **49 tes / 209 assertions**, tetap 4 errors + 4 failures baseline yang sama; 13 tes baru semuanya lulus.

### Bukti tampilan fixture
- [Review struk mobile](evidence/receipt-review-mobile.png): subtotal 48.500, diskon 3.500, total 45.000, dan tanggal kalender 3 Nov 2026.
- [Detail transaksi mobile dark](evidence/receipt-detail-mobile-dark.png): metadata diskon, total tersimpan, tanggal, dan lampiran tetap tampil. Gambar struk pada fixture menggunakan pixel uji, bukan foto pengguna.

## Menjalankan ulang
```bash
composer install
vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/ReceiptDataNormalizerTest.php tests/Feature/ReceiptWorkflowRegressionTest.php
npm install
npm run build
npx playwright install chromium
npm run test:browser
```
Gunakan host pengembangan/disposable, bukan direktori deployment produksi. Untuk Chrome terpasang, set `PLAYWRIGHT_CHANNEL=chrome`. `RUDIMAH_SCREENSHOTS` dapat menentukan direktori bukti; default berada di temporary directory `opencode/rudimah-mobile-evidence`.

## Keterbatasan dan review operasional
- Tidak mengirim foto pengguna ke provider nyata, tidak melakukan migrasi produksi, tidak mengubah data lama, dan tidak mengeksekusi installer/update/deployment.
- Akurasi OCR foto struk asli, Android/iOS fisik, Safari, gateway AI produksi, locking MariaDB paralel, dan screenshot diff terhadap baseline legacy belum diverifikasi. Screenshot saat ini adalah bukti tampilan fixture, bukan klaim pixel-perfect.
- Data lama yang total atau tanggalnya sudah salah perlu diperiksa dan dikoreksi pengguna; perubahan ini mencegah jalur kesalahan ke depan, bukan memperbaiki riwayat otomatis.
