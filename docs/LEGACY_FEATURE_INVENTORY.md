# Inventaris Fitur Legacy PEKAN

Dokumen ini mendata seluruh fitur terverifikasi dari repositori `https://github.com/honet-labs/pekan` (commit `2c8a1d5`).

## 1. Modul Autentikasi dan Identitas
* Masuk Akun: Form autentikasi email dan kata sandi dengan perlindungan pembatasan laju (rate limiting).
* Lupa Kata Sandi: Pengiriman tautan atau OTP pemulihan kata sandi.
* Lupa Kode Ruang Kerja: Pencarian tenant yang diasosiasikan dengan alamat email pengguna.
* Reset Kata Sandi: Pembaruan kredensial dengan token verifikasi sekali pakai.
* Profil Pengguna: Pembaruan nama lengkap, nomor telepon, dan preferensi antarmuka.

## 2. Modul Dasbor Keuangan (Finance Dashboard)
* Kartu Metrik Ringkasan: Total pemasukan bulan berjalan, total pengeluaran, saldo bersih, dan akumulasi tabungan.
* Grafik Tren Kas: Grafik garis visualisasi pergerakan arus kas masuk dan keluar per hari atau per bulan.
* Daftar Transaksi Terkini: Tabel mutasi 5 transaksi terakhir dengan status dan rincian nominal.
* Ringkasan Anggaran Aktif: Bilah kemajuan pemakaian anggaran kategori dengan indikator ambang batas.
* Status Capaian Tabungan: Persentase capaian target celengan atau pos tabungan aktif.

## 3. Modul Manajemen Transaksi (Transactions)
* Daftar Transaksi: Tabel transaksi dengan filter rentang tanggal, jenis mutasi, kategori, dompet atau rekening, dan pencarian teks.
* Tambah Transaksi Tunggal / Multi-item: Pencatatan mutasi kas masuk, kas keluar, transfer antar rekening, serta alokasi ke pos tabungan.
* Rincian Item Transaksi: Dukungan multi-item dalam satu kuitansi dengan jumlah kuantitas, harga satuan, dan potongan harga.
* Metadata Struk: Nomor kuitansi, nama merchant penyedia, metode pembayaran, pajak, biaya layanan, dan diskon faktur.
* Lampiran Bukti: Unggah kuitansi foto atau dokumen PDF dengan verifikasi tipe file.
* Hapus Transaksi: Mekanisme soft delete dengan pencatatan pembatalan pada saldo rekening terkait.

## 4. Modul Tabungan dan Impian Finansial (Savings)
* Daftar Tabungan: Visualisasi kartu target tabungan dengan nama impian, target nominal, dan tanggal target.
* Tambah Pos Tabungan: Penetapan nama pos, target akumulasi, rekening penampung, dan tenggat waktu.
* Mutasi Setor dan Tarik Tabungan: Alokasi transaksi langsung dari dompet utama ke pos tabungan terkait.
* Riwayat Transaksi Tabungan: Daftar mutasi yang terhubung dengan pos tabungan spesifik.

## 5. Modul Anggaran Pengeluaran (Budgets)
* Monitoring Anggaran: Pengawasan batas maksimal belanja per kategori untuk periode mingguan, bulanan, atau tahunan.
* Peringatan Ambang Batas: Indikator saat belanja melampaui persentase ambang batas (contoh 80 persen atau 100 persen).
* Penyesuaian Anggaran: Pembaruan limit alokasi dana dan perpanjangan periode anggaran.

## 6. Modul Pengingat Tagihan (Reminders)
* Daftar Tagihan: Pengingat kewajiban pembayaran berkala seperti listrik, internet, sewa, asuransi, dan cicilan.
* Pelacakan Tenor: Pencatatan nomor tenor berjalan terhadap total keseluruhan tenor (misal bulan ke-3 dari 12).
* Pencatatan Pembayaran Tagihan: Form konfirmasi pembayaran dengan unggah bukti transfer.
* Status Tagihan: Penandaan status belum dibayar, lunas tepat waktu, atau lewat jatuh tempo.

## 7. Modul Data Master (Master Data)
* Rekening dan Dompet: Pengelolaan daftar rekening bank, dompet digital, dan kas tunai beserta saldo awal.
* Kategori Keuangan: Kategori bersarang (parent dan subcategory) untuk pos pemasukan dan pos pengeluaran.
* Penanda (Tags): Label kontekstual tambahan untuk pengelompokan transaksi lintas kategori.

## 8. Modul Pemindai Kuitansi (Receipt Scanner)
* Unggah Berkas Kuitansi: Penerimaan berkas gambar JPG, PNG, atau PDF melalui antarmuka drag and drop.
* Ekstraksi OCR: Pemrosesan gambar untuk mendeteksi merchant, tanggal, total, pajak, biaya layanan, dan daftar belanja.
* Verifikasi Interaktif: Antarmuka tinjauan hasil ekstraksi agar pengguna dapat mengoreksi sebelum data disimpan ke transaksi final.
* Konfigurasi Provider: Pengaturan kunci API dan model penyedia layanan AI.

## 9. Modul Laporan Keuangan (Reports)
* Laporan Arus Kas: Perbandingan agregat pemasukan dan pengeluaran dalam periode yang dipilih.
* Komposisi Kategori: Diagram lingkaran distribusi belanja per kategori.
* Ekspor Data: Unduh data mutasi keuangan ke format CSV dan PDF.

## 10. Modul Pengaturan dan Integrasi (Settings)
* Pengaturan Kanal Notifikasi: Konfigurasi pengiriman notifikasi pengingat via email atau pesan instan.
* Integrasi WhatsApp: Konfigurasi sesi bot WhatsApp untuk input mutasi via chat dan webhook balasan.
* Log Audit Keamanan: Riwayat aktivitas dan perubahan data oleh seluruh anggota tenant.
