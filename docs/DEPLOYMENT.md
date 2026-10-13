# Panduan Deployment & Topologi Layanan RUDIMAH

Dokumen ini mendeskripsikan topologi kontainer Docker Compose dan konfigurasi jaringan produksi untuk platform RUDIMAH.

## 1. Diagram Topologi Layanan Kontainer

```
               [ Internet / Klien Web ]
                          |
              Port 80 / 443 (Publik)
                          v
         +----------------------------------+
         |      rudimah_nginx (Alpine)      |
         |  - Reverse Proxy & TLS Handler   |
         |  - Header Keamanan & Rate Limit  |
         +----------------------------------+
                          |
               Jaringan Internal Docker
                          |
     +--------------------+--------------------+
     |                                         |
     v                                         v
+-------------------------+       +-------------------------+
|   rudimah_app (PHP 8.3) |       |  rudimah_horizon        |
| - Laravel Modular       |       | - Background Worker     |
| - Inertia & Vue 3       |       | - AI OCR Job Processor  |
+-------------------------+       +-------------------------+
     |                    |                    |
     v                    v                    v
+----------------+   +----------------+   +-----------------+
| rudimah_mariadb|   | rudimah_redis  |   | rudimah_scheduler
| - Port Internal|   | - Port Internal|   | - Cron Per Menit|
| - Volume Data  |   | - Cache & Queue|   +-----------------+
+----------------+   +----------------+
```

## 2. Segmentasi Jaringan dan Isolasi Keamanan
1. Hanya kontainer `rudimah_nginx` yang membuka port keluar (`80` dan `443`).
2. Kontainer `rudimah_mariadb` dan `rudimah_redis` berada di jaringan jembatan internal (`rudimah_network`) dan tidak dipublikasikan ke jaringan publik host secara default.
3. Hak akses berkas `.env` dibatasi untuk pengguna root dan runner aplikasi dengan izin `0600`.
4. Seluruh lampiran berkas kuitansi disimpan pada direktori penyimpanan privat yang diverifikasi melalui token berbatas waktu (signed URL).
