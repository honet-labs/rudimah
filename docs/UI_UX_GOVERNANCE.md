# RUDIMAH UI/UX Governance  :  aturan wajib lintas aplikasi

**Aturan tertinggi frontend:** consistency by default, changes by design review, bukan desain ad-hoc oleh tiap developer/coding agent.

## 1. Pembagian kepemilikan
- **Platform team**: global layout, tenant switcher, application launcher, identity pages, navigasi, tokens, shared components, accessibility, audit visual.
- **Module team**: konten dan UX domain (PEKAN, App1, App2) *di dalam* shell; tidak boleh menimpa atau menduplikasi shell.
- **Desain PEKAN existing**: tetap menjadi baseline fidelity sampai migrasi visual disetujui. Buat adapter CSS terbatas `legacy-pekan` jika dibutuhkan; jangan menerapkan legacy styles ke seluruh platform.

## 2. Single source of truth (SSOT)
- `docs/DESIGN_SYSTEM.md` = spec semantik.
- `resources/css/tokens.css` = implementation tokens dan theme.
- `resources/js/design-system/` = shared components.
- `resources/js/layouts/RudimahAppShell.vue` = shell tunggal.
- Storybook/Histoire atau katalog komponen setara = demonstrasi komponen interaktif; pilih hanya satu setelah kompatibilitas dicek.
- `tests/visual/` = baseline visual Playwright yang direview dan di-versioning.

## 3. Aturan larangan
1. Jangan buat `Button`, `Modal`, `Table`, `Sidebar`, `Topbar` versi modul kecuali komponen spesifik domain yang benar-benar tidak bisa direpresentasikan oleh primitives bersama.
2. Jangan hardcode warna, radius, spacing, font, elevation, shadow, atau breakpoint di halaman bisnis; gunakan token.
3. Jangan ganti UI library, font, ikon, default tombol, animasi, atau modal dari satu modul saja.
4. Jangan mengubah order menu, nomenklatur, atau desain navigasi global tanpa persetujuan.
5. Jangan merombak tampilan PEKAN tanpa parity review/approval.
6. Jangan sembunyikan security error; tampilkan state konsisten tanpa membocorkan data sensitif.
7. Jangan mengandalkan custom CSS `!important` atau selector global yang bocor ke modul lain.
8. Jangan set `z-index:999999`; gunakan layer tokens untuk overlay/dropdown/dialog/toast.
9. Jangan memilih gaya baru dari screenshot yang hanya menggambarkan satu halaman dan menerapkannya secara global tanpa desain review.
10. Jangan mengubah user flow eksisting atas dasar estetika saja.

## 4. Workflow implementasi halaman baru
1. Klasifikasikan kebutuhan: global/shared, reusable module-specific, atau bespoke.
2. Cari existing shared component; reuse lebih dulu.
3. Dokumentasikan page contract (tujuan, actor, data, permissions, fields, states, responsiveness).
4. Buat mockup/wireframe sesuai design system.
5. Terapkan komponen dan token; gunakan layout bersama.
6. Tambahkan screenshot desktop/tablet/mobile dan interaksi inti.
7. Uji keyboard/aksesibilitas; uji role dan tenant.
8. Dapatkan review visual; update katalog komponen jika ada pattern baru.

## 5. Proses perubahan desain global
Perubahan token, global navigation, icon pack, layout, atau komponen publik wajib dilengkapi mini RFC/ADR:
- Mengapa harus berubah.
- Sebelum/sesudah screenshot.
- Komponen dan modul terdampak.
- Rencana backward compatibility atau migrasi.
- Dampak aksesibilitas dan responsive.
- Regression test serta approval.
- Design-system semver dan changelog.

Tidak boleh upgrade library UI otomatis yang menghasilkan breaking visual tanpa evaluasi dan baseline approval.

## 6. Kriteria visual regression
- Reference viewports: 360x800, 390x844, 768x1024, 1280x800, 1440x900.
- Test mode light dan dark **setelah** kedua mode didukung global.
- Snapshot tiap halaman kritis dan state: default, loading, validation error, empty, modal, forbidden.
- Mask hanya data dinamis yang tidak deterministik; jangan mask keseluruhan komponen untuk menyembunyikan regresi.
- Setiap perubahan screenshot dibandingkan manual; perubahan yang disengaja harus tercatat di PR.
- Jangan menyatakan pixel-perfect jika source legacy tidak menyediakan aset/baseline; tandai confidence dan gap.

## 7. Content design / microcopy Bahasa Indonesia
- Navigasi: `Beranda`, `Aplikasi`, `Anggota`, `Pengaturan`, `Keamanan`.
- Tindakan: `Tambah`, `Simpan`, `Ubah`, `Hapus`, `Ekspor`, `Kembali`, `Batal`.
- Konfirmasi destruktif: `Hapus transaksi ini? Tindakan ini tidak dapat dibatalkan.`
- Pesan berhasil: `Perubahan berhasil disimpan.`
- Error jaringan: `Tidak dapat terhubung. Periksa koneksi lalu coba lagi.`
- Status OCR: `Menunggu diproses`, `Sedang membaca struk`, `Perlu diperiksa`, `Selesai`, `Gagal`.
- Istilah dipilih sekali dalam glossary; satu konsep satu istilah, jangan kadang `organisasi` kadang `tenant` di antarmuka publik tanpa alasan. UI gunakan `Ruang Kerja` atau `Organisasi` sebagai label, internal boleh `tenant`.
- Label tidak menjanjikan kemampuan yang belum terimplementasi.

## 8. Checklist reviewer PR frontend
- [ ] Tidak ada komponen/platform shell baru yang redundan.
- [ ] Semua visual memakai token RUDIMAH.
- [ ] Copy sepenuhnya Bahasa Indonesia.
- [ ] Responsif di 360/768/1440px.
- [ ] Focus, keyboard, label, kontras diuji.
- [ ] Loading/empty/error/forbidden terimplementasi.
- [ ] Tidak ada regresi pada modul lain.
- [ ] Visual parity PEKAN tidak berubah tanpa persetujuan.
- [ ] Component docs dan snapshots diperbarui bila perlu.
- [ ] Skenario authorization UI tidak menggantikan backend security.
