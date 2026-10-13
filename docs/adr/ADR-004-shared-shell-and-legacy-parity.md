# ADR 004: Standardisasi Shared AppShell dan Paritas Visual Legacy PEKAN

## Status
Diterima

## Konteks
Pembangunan modul baru berpotensi memunculkan fragmentasi gaya visual dan tata letak jika setiap modul dibiarkan membuat komponen navigasi tersendiri. Di sisi lain, pengguna lama modul PEKAN memerlukan alur bisnis dan tata letak informasi yang tidak asing.

## Keputusan
1. Seluruh modul (PEKAN, App1, App2) diwajibkan menggunakan layout tunggal `RudimahAppShell.vue` dan pustaka komponen bersama berawalan `Rd*` dari direktori `resources/js/design-system`.
2. Dilarang menginstal pustaka UI pihak ketiga tambahan di luar yang telah ditentukan sistem desain platform.
3. Area konten PEKAN mempertahankan tata letak data, format angka rupiah, dan alur interaksi seperti pada repositori legacy, disesuaikan ke dalam semantic design tokens di `tokens.css`.
4. Mengurangi penggunaan ikon dan emotikon berwarna cerah, mengutamakan simbol monokrom netral dan indikator berbasis teks yang lulus uji kontras WCAG 2.2 AA.

## Konsekuensi
Positif:
* Penampilan seluruh aplikasi dalam portal terasa terintegrasi dan konsisten secara estetika.
* Mempermudah pemeliharaan jangka panjang dan pengujian regresi visual otomatis.

Negatif:
* Penambahan komponen baru harus melalui review sistem desain platform terlebih dahulu.
