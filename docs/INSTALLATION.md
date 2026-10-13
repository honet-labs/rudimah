# Panduan Instalasi Platform RUDIMAH

Dokumen ini memuat panduan langkah demi langkah untuk melakukan instalasi platform RUDIMAH (Ruang Digital Manajemen Harian) pada lingkungan server mandiri (self-hosted).

## 1. Kebutuhan Sistem Minimum
* Sistem Operasi: Linux (Ubuntu 22.04 LTS / 24.04 LTS, Debian 12, Rocky Linux 9, atau AlmaLinux 9)
* Arsitektur CPU: x86_64 atau ARM64 (minimal 2 Core CPU)
* Memori RAM: Minimal 2 GB (direkomendasikan 4 GB untuk beban kerja AI OCR)
* Ruang Penyimpanan: Minimal 10 GB ruang kosong pada partisi SSD
* Perangkat Lunak: Docker Engine 24+ dan Docker Compose Plugin v2+

## 2. Prosedur Instalasi Menggunakan Wizard

1. Unduh atau clone repositori:
   ```bash
   git clone https://github.com/honet-labs/rudimah.git
   cd rudimah
   ```

2. Jalankan skrip installer interaktif:
   ```bash
   chmod +x installer.sh update.sh uninstall.sh backup.sh restore.sh status.sh reconfigure.sh
   sudo ./installer.sh
   ```

3. Wizard akan meminta masukan parameter berikut:
   * Port HTTP publik (default: 80)
   * Port HTTPS publik (default: 443)
   * Nama basis data platform (default: `rudimah_platform`)
   * Nama basis data modul PEKAN (default: `rudimah_pekan`)
   * Username basis data
   * Alamat email administrator platform

4. Skrip akan menghasilkan kata sandi kriptografis yang aman dan menyimpannya ke berkas `.env` dengan hak akses terbatas (`chmod 600`).

5. Jalankan kontainer aplikasi:
   ```bash
   docker compose up -d
   ```

## 3. Instalasi Otomatis (Non-Interaktif)
Untuk skenario otomatisasi CI/CD atau orkestrasi server, gunakan argumen berikut:
```bash
sudo ./installer.sh --non-interactive
docker compose up -d
```

## 4. Verifikasi Pasca-Instalasi
Periksa status kontainer dan kesehatan koneksi dengan menjalankan:
```bash
./status.sh
```
Akses portal melalui browser di `http://<ip_server>:<http_port>` dan masuk menggunakan email administrator yang ditampilkan saat instalasi.
