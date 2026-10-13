# RUDIMAH  :  Frontend Implementation Contract

**Normative implementation companion** untuk `DESIGN_SYSTEM.md` dan `UI_UX_GOVERNANCE.md`.

## 1. Modul harus mengonsumsi kontrak global

Setiap halaman webapp (PEKAN, App1, App2) wajib memakai:
- `RudimahAppShell.vue` global **satu implementasi**.
- `RdPageHeader`, `RdButton`, `RdInput`, `RdSelect`, `RdTable`, `RdModal`, `RdToast`, `RdAlert`, `RdEmptyState`, `RdErrorState` dari satu shared directory.
- `--rd-*` tokens; Tailwind semantic mappings dari platform.
- `id-ID` formatting helpers dan glossary.

Contoh page: **ilustratif, bukan implementasi final PEKAN**.

```vue
<script setup lang="ts">
import RudimahAppShell from '@/layouts/RudimahAppShell.vue'
import RdPageHeader from '@/design-system/RdPageHeader.vue'
import RdButton from '@/design-system/RdButton.vue'

defineProps<{ title: string; canCreate: boolean }>()
</script>

<template>
  <RudimahAppShell app-key="pekan">
    <RdPageHeader :title="title" description="Kelola data dalam ruang kerja aktif">
      <template #actions>
        <RdButton v-if="canCreate" variant="primary">Tambah data</RdButton>
      </template>
    </RdPageHeader>
    <!-- Konten bisnis spesifik PEKAN; loading, empty, error wajib -->
  </RudimahAppShell>
</template>
```

**Catatan:** `canCreate` untuk UX; controller/policy tetap wajib memeriksa izin terhadap POST endpoint. API sebenarnya dapat menggunakan typed Inertia props.

## 2. Semantics komponen
- `RdButton` variants: primary/secondary/outline/ghost/destructive; loading state dan on-submit double click protection.
- `RdModal`: accessible dialog semantics, focus trap/restore, close via Escape bila aman, destructive confirm eksplisit.
- `RdTable`: filtering/sorting/pagination dengan parameter URL terkontrol, currency/date id-ID.
- `RdMoney`: integer minor unit atau fixed-precision string → formatter, bukan floating arithmetic untuk pemrosesan bisnis.
- `RdTenantSwitcher`: perubahan tenant memicu request server yang memvalidasi membership, memutus akses/cache local tenant lama.
- `RdAppLauncher`: daftar hanya dari server-authorized apps; UI bukan security boundary.

## 3. Template layout wajib
```text
RUDIMAH AppShell
├── Global header: brand, tenant switcher, app launcher, notifications, account
├── Global sidebar / mobile drawer (app-level navigation)
└── Main
    ├── Breadcrumbs
    ├── PageHeader + actions
    ├── Optional search/filter toolbar
    ├── PageBody (load / empty / error / forbidden)
    └── Domain-specific content
```
PEKAN compatibility adapter berada di **main content region saja**, tidak meng-overwrite global shell.

## 4. CSS/Tailwind standards
**Contoh baik**:
```css
.rd-panel { background: var(--rd-surface); color: var(--rd-text); border-color: var(--rd-border); }
```
**Contoh buruk**:
```css
.pekan-dashboard { background: #ffffff; border-radius: 17px; }
```
Pengecualian: library chart yang memerlukan angka warna raw gunakan TS semantic token adapter resmi, bukan color values baru per modul.

## 5. Penegakan otomatis dalam CI
Terapkan rule set (sesuai build tooling yang benar-benar dipakai):
- ESLint/typecheck menolak implicit any, unused imports, noncompliant module boundaries.
- Stylelint/custom lint menolak warna hex/hardcoded font/spacing di `resources/js/modules/**` dan `resources/css/modules/**`, kecuali file compatibility adapter yang di-allowlist dan dijelaskan dalam ADR.
- Modul hanya boleh mengimpor global shell/components dari public API/barrel resmi.
- Komponen shared memiliki unit/interaction tests; visual baseline Playwright untuk perubahan UI.
- PR yang mengubah tokens/components harus men-trigger regression untuk **semua** modul.
- Lakukan review aksesibilitas pada komponen shared; hindari kontras token yang tidak lolos.
- Gunakan dependency lockfile; perubahan major UI libraries harus punya RFC dan screenshot.

## 6. Versioned design system
- `1.0.0-draft` → `1.0.0` setelah approval baseline.
- Perubahan token yang mengubah tampilan semua modul = review global dan dicatat di changelog.
- Depracate old components dengan codemod/migration plan daripada duplikasi baru.
- Lakukan screenshot diff dan contoh sebelum/sesudah untuk design RFC.

## 7. Review UI/UX untuk fitur baru
1. Page contract tersedia.
2. Komponen shared direuse; tidak ada library UI baru.
3. Layout dan copy konsisten.
4. Keyboard navigation, focus dan responsive bekerja.
5. State loading/empty/error/forbidden tersedia.
6. Semua data dan tenant context benar dari server.
7. Visual regression valid untuk 360/768/1440.
8. Legacy PEKAN parity tidak terganggu.
