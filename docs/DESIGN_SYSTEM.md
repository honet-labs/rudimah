# RUDIMAH Design System (RDS)

**Dokumen normatif.** Versi awal `1.0.0-draft`, pemilik: tim platform, status: rancangan awal yang harus divalidasi terhadap UI PEKAN existing sebelum dikunci. Bahasa antarmuka utama: **Bahasa Indonesia (id-ID)**.

## 1. Tujuan dan cakupan
Satu identitas visual serta perilaku interaksi untuk **RUDIMAH  :  Ruang Digital Manajemen Harian**, termasuk Control Panel, PEKAN, dan seluruh modul mendatang. Desain bukan sekadar CSS: mencakup navigasi, feedback, aksesibilitas, formulir, error states, tabel, dan aturan otorisasi visual. Semua modul harus menggunakan **RUDIMAH App Shell**, design tokens dan komponen platform; modul boleh memiliki konten bisnis dan visualisasi khusus tanpa menciptakan desain sistem kedua.

**Aturan prioritas saat rebuild:** (1) fitur dan data PEKAN tidak boleh berubah, (2) layar PEKAN existing harus dipertahankan sebagai visual parity baseline dalam proses migrasi, (3) RUDIMAH shell baru konsisten di seluruh modul, (4) penyelarasan tampilan area PEKAN ke design system baru dilakukan sebagai perubahan UI terencana, tidak diam-diam. Jika visual parity lama bertentangan dengan komponen global, dokumentasikan adaptor sementara dan jadwalkan approval migrasi.

## 2. Brand & bahasa
- Brand induk: **RUDIMAH** (kapital di logo formal); nama lengkap: **Ruang Digital Manajemen Harian**.
- Produk/modul pertama: **PEKAN**; aplikasi berikutnya: App1, App2 sebagai placeholder sampai requirement tersedia.
- UI/copy **Bahasa Indonesia baku dan mudah dimengerti**: `Simpan`, `Batal`, `Hapus`, `Sedang diproses`, `Coba lagi`, `Berhasil disimpan`. Jangan mencampur bahasa pada label navigasi; istilah teknis yang diperlukan boleh diberi bantuan singkat.
- Zona waktu default `Asia/Jakarta`; tampilan tanggal `13 Okt 2026`, waktu format 24 jam; format angka `id-ID`, mata uang `IDR`/`Rp` dengan pembulatan benar. Penyimpanan waktu UTC, konversi hanya pada presentation boundary.
- Keputusan penggunaan ilustrasi, tone, maupun aset visual dicatat di `UI_UX_GOVERNANCE.md`.
- **Prinsip Estetika Bersih (No Emojis & Minimal Iconography)**: Antarmuka RUDIMAH mengusung gaya profesional dan tenang (*clean enterprise*). Dilarang menyematkan emoji/emoticon berwarna (seperti ✉️, 💬, 🤖, 📊, 💳, 💰, dll) pada teks kontrol, heading, tab, tombol, atau navigasi. Penggunaan ikon ditekan semaksimal mungkin, hanya digunakan saat memberikan nilai fungsional (*affordance*) dan wajib berupa outline/monokromatis netral.

## 3. Sumber kebenaran dan token
**Tidak boleh** mengulang hex, spacing, font family, shadow, z-index atau durasi transisi di tiap modul. Token didefinisikan **sekali** di `resources/css/tokens.css` dan dipetakan ke Tailwind theme; TypeScript exports bila diperlukan untuk library grafik.

### 3.1 Draft semantic palette  :  perlu persetujuan visual
| Token | Mode terang (draft) | Pemakaian |
|---|---|---|
| `--rd-bg` | `#F8FAFC` | permukaan halaman |
| `--rd-surface` | `#FFFFFF` | card, modal |
| `--rd-surface-muted` | `#F1F5F9` | panel sekunder |
| `--rd-text` | `#0F172A` | isi utama |
| `--rd-text-muted` | `#475569` | informasi sekunder |
| `--rd-border` | `#CBD5E1` | border |
| `--rd-primary` | `#4F46E5` | aksi utama |
| `--rd-primary-text` | `#FFFFFF` | teks aksi utama |
| `--rd-success` | `#15803D` | berhasil |
| `--rd-warning` | `#B45309` | peringatan |
| `--rd-danger` | `#B91C1C` | kesalahan/destruktif |
| `--rd-focus` | `#2563EB` | focus ring |

Warna ini hanya **baseline usulan**, bukan klaim warna existing PEKAN. Kontras setiap kombinasi harus diuji; status tidak boleh disampaikan melalui warna saja. Dark mode harus menggunakan pasangan token semantik terpisah di `[data-theme="dark"]`, bukan pembalikan warna otomatis. Semua nilai yang disetujui dipindah ke `tokens.css` (bukan dokumentasi semata).

### 3.2 Spacing & ukuran
- Grid jarak dasar 4px: `1=4`, `2=8`, `3=12`, `4=16`, `6=24`, `8=32`, `12=48` px.
- Konten halaman desktop max-width **1440px**, padding desktop 24–32px, tablet 20px, mobile 16px. Lebar khusus untuk editor/data-table harus terdokumentasi.
- Tinggi kontrol default **40px**, large **44px**, small **32px** untuk konteks padat (tetap penuhi target sentuh minimal dan spacing), ikon konsisten **16/20/24px**.
- Radius: `sm=6px`, `md=10px`, `lg=14px`, `xl=18px`. Border 1px, elevation via shadow tokens `sm/md/lg` yang tunggal.
- Typography: font sans **Inter** atau system sans fallback (pastikan lisensi dan ketersediaan); body `14–16px`, line-height `1.5`; judul halaman `24–28px`, judul section `18–20px`, label `13–14px`. Tetapkan typographic scale final setelah audit UI.
- Motion: durasi `120–200ms` untuk interaksi pendek, `200–300ms` untuk drawer; hormati `prefers-reduced-motion`.

### 3.3 Breakpoint dan layout
- Mobile-first; perangkat acuan **360px, 390px, 768px, 1024px, 1280px, 1440px**.
- Shell desktop: global topbar + sidebar aplikasi; shell mobile: topbar + drawer yang dapat ditutup via Escape/focus management.
- Navigasi global berisi brand, tenant switcher, app launcher, profil, keamanan, notifikasi. Navigasi **modul** berada di wilayah yang jelas (subnav/sidebar kontekstual) tanpa menduplikasi global menu.
- Setiap modul memperoleh `AppShell` yang sama, page heading/breadcrumb, padding, card primitives, dan component library. **Modul tidak boleh membuat versi sidebar/topbar sendiri**.

## 4. Komponen wajib shared library
Direktori **`resources/js/design-system/`** atau **`resources/js/components/ui/`**; setiap komponen menerima props bertipe dan state standar.

| Komponen | Varian & kontrak |
|---|---|
| `RdButton` | primary, secondary, outline, ghost, destructive; loading/disabled; icon optional |
| `RdInput` / `RdTextarea` | label, hint, error, required, aria-describedby |
| `RdSelect`, `RdCombobox` | keyboard navigation, empty state, async loading |
| `RdCheckbox`, `RdRadio`, `RdSwitch` | status jelas, proper labels |
| `RdModal` / `RdDrawer` | focus trap, close, descriptive title; mobile behavior |
| `RdAlert` / `RdToast` | success, info, warning, error; non-color indicators |
| `RdTable` | sort, filter, pagination, loading, empty, permission-aware actions |
| `RdCard`, `RdBadge`, `RdTabs` | spacing/typography/interaction tokenized |
| `RdPageHeader` | title, breadcrumbs, optional action slot |
| `RdConfirmDialog` | destructive action confirmation; safe defaults |
| `RdFileUpload` | file type/size, drag-drop, preview, validation |
| `RdSkeleton`, `RdEmptyState`, `RdErrorState` | consistent lifecycle patterns |
| `RdTenantSwitcher`, `RdAppLauncher` | platform-only controlled components |
| `RdMoney`, `RdDateTime` | locale id-ID; format business values consistently |

Use shadcn-vue/Reka UI **sebagai primitives**, tetapi wrap dan stabilkan API komponen RUDIMAH. Dilarang memakai implementasi komponen bawaan dari library secara langsung tersebar di setiap modul jika menyebabkan style/behavior berbeda.

## 5. Mandatory page template
Urutan konsisten: `AppShell` → navigasi global → modul menu → breadcrumb → `PageHeader` → actions/filters → body → feedback. Semua halaman punya title, loading, empty, error, dan permission-denied state. Informasi penting tidak boleh mengandalkan toast yang menghilang sebagai satu-satunya feedback.

### Tabel/data
- Penamaan kolom, align angka kanan, tanggal dan uang terformat lokal, sorting server-side untuk dataset besar.
- Filter dapat direset; state filter mendukung URL query bila cocok; tidak mengubah isi tanpa indikator loading.
- Pagination dan batas dataset ada pada server; ekspor hanya untuk user berizin.
- Bulk/destructive action selalu konfirmasi, jelas apa yang terdampak.

### Formulir
- Label persisten; placeholder bukan pengganti label.
- Validasi client untuk UX dan server untuk kebenaran; error muncul dekat field.
- Primary action eksplisit; submit disable saat processing; retry tidak membuat data duplikat.
- Dialog confirm untuk destruktif; autosave hanya jika fitur existing memang memilikinya atau disetujui.

### Kondisi aplikasi
- Loading: skeleton / progress yang relevan.
- Empty: konteks dan CTA relevan tanpa promosi tak diperlukan.
- Error: pesan manusiawi + tindakan perbaikan; tidak tampilkan stack trace.
- Unauthorized: tampilkan halaman 403 resmi, tidak sekadar sembunyikan tombol.
- Offline/reconnect: beri status jelas jika fitur memerlukan jaringan.

## 6. Aksesibilitas & kualitas
Target **WCAG 2.2 AA**: kontras teks reguler ≥4.5:1 (berdasarkan pengecualian WCAG yang berlaku), teks besar ≥3:1, keyboard access, focus visible, semantic headings, form labels, non-color cues, screen reader announcements. Interactive target sesuai WCAG 2.2 target-size minimum **24x24 CSS pixels** atau pengecualiannya; rekomendasikan **44x44px** untuk target utama sentuh mobile. `prefers-reduced-motion` wajib dihormati.

## 7. Consistency acceptance checks
Setiap modul/halaman **WAJIB**:
1. Menggunakan `AppShell`, shared navigation, tokens, komponen RUDIMAH.
2. Memakai copy Bahasa Indonesia dan format lokal.
3. Menyediakan loading/empty/error/forbidden state.
4. Lulus keyboard/focus checks, kontras, dan responsivitas.
5. Mendukung light/dark jika mode tersebut telah dirilis secara global; tidak boleh satu modul saja.
6. Memiliki screenshot Playwright di baseline 360/768/1440px; bandingkan terhadap snapshot approved.
7. Tidak menambah package UI, font, ikon, atau icon set baru tanpa ADR dan persetujuan.
8. Tidak mengubah token global dari dalam fitur tertentu; perubahan token melalui RFC/ADR dan regression seluruh modul.
9. Mematuhi pemisahan PEKAN legacy parity dan shell RUDIMAH.
10. Dicatat dalam perubahan design system jika mengubah komponen publik.

## 8. Versi dan governance
RDS menggunakan semantic version. Patch = perubahan nonvisual/bug fix; minor = komponen/varian baru kompatibel; major = breaking visual/API changes. Setiap perubahan harus dicatat di `docs/UI_UX_GOVERNANCE.md`, melalui review desain & frontend, visual regression, dan release notes. Tidak ada perubahan desain global tanpa peninjauan dampak lintas modul.

## 9. Dokumen pendukung
- `docs/UI_UX_GOVERNANCE.md`: proses review, penanganan pengecualian.
- `docs/FEATURE_PARITY_MATRIX.md`: baseline PEKAN.
- `docs/ARCHITECTURE.md`: ownership shell, module & tenancy.
- `AGENTS.md`: aturan otomatis untuk coding agent.

## 10. Referensi
- https://www.w3.org/TR/WCAG22/
- https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum
- https://laravel.com/starter-kits
