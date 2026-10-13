# ADR 005: Arsitektur Ekstraksi AI OCR dan Prinsip Human in the Loop

## Status
Diterima

## Konteks
Fitur pemindaian struk bertujuan mempermudah pencatatan transaksi pengguna. Namun, model visi komputer AI dapat berhalusinasi atau salah membaca angka, tanggal, atau nama toko.

## Keputusan
1. Hasil ekstraksi AI OCR tidak boleh langsung mengubah basis data transaksi final secara otomatis.
2. Alur data wajib melalui antarmuka peninjauan (Human in the Loop) di mana hasil pembacaan ditampilkan sebagai draf kuitansi.
3. Pengguna wajib memverifikasi nilai total kuitansi, tanggal, kategori, dan rincian baris item sebelum menekan tombol simpan transaksi.
4. Antarmuka menerapkan validasi konsistensi internal: subtotal ditambah pajak dikurangi diskon harus cocok dengan total kuitansi sebelum draf dapat difinalkan.

## Konsekuensi
Positif:
* Mencegah anomali saldo akibat kesalahan baca karakter oleh model AI.
* Memberikan kendali penuh kepada pengguna terhadap kebenaran pembukuan mereka.

Negatif:
* Memerlukan satu tahapan klik konfirmasi tambahan sebelum data tercatat ke mutasi rekening.
