# Template Analisis PEKAN  :  Legacy Feature & UI Parity

> **Status awal: BELUM DIAUDIT.** Jangan mengisi seolah-olah sudah diverifikasi dari repository `https://github.com/honet-labs/pekan`.

| ID | Modul | URL/halaman lama | File/rute sumber | Perilaku lama | Rules/perhitungan | Tabel/data | Screenshot baseline | API/route baru | Test case | Status | Bukti |
|---|---|---|---|---|---|---|---|---|---|---|---|
| LEG-001 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | Belum diperiksa | - |

Status: `Belum diperiksa`, `Terverifikasi legacy`, `Dalam proses`, `Diimplementasi`, `Lulus uji`, `Disetujui parity`.

## Page contract template
- Actor dan tenant context:
- Tujuan halaman:
- Kondisi autentikasi/MFA:
- Permission/policy:
- Entry route:
- Dataset/filter/sorting/pagination:
- Form fields & validation:
- Business rules & angka:
- State: loading/empty/error/success/forbidden:
- Error handling:
- Perilaku responsive & keyboard:
- Screenshot desktop/tablet/mobile:
- Test automation:
- Hal yang berubah dan alasan disetujui:

## Wajib sebelum status `Disetujui parity`
1. Source lama benar-benar diperiksa.
2. Seluruh alur bisnis diuji.
3. Output finansial/data dibandingkan.
4. Screenshot baseline diperiksa.
5. Flow baru tidak memiliki regresi keamanan.
6. Migration data diverifikasi jika fitur memakai data existing.
