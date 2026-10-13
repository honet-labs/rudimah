# Matriks Paritas Fitur PEKAN ke RUDIMAH

Dokumen ini memetakan seluruh fitur legacy PEKAN yang terverifikasi ke implementasi Laravel 13, Inertia 3, dan Vue 3 pada platform RUDIMAH.

| ID | Modul | URL / Halaman Lama | File Sumber Legacy | Perilaku Legacy | Rute RUDIMAH Target | Handler / Controller Target | Status |
|---|---|---|---|---|---|---|---|
| PAR-001 | Auth | `/login` | `frontend/src/features/core/auth/pages/LoginPage.tsx` | Autentikasi email dan password, return JWT | `/login` | `App\Core\Identity\Http\Controllers\AuthController@login` | Disetujui parity |
| PAR-002 | Auth | `/forgot-password` | `frontend/src/features/core/auth/pages/ForgotPasswordPage.tsx` | Request link / kode OTP reset password | `/forgot-password` | `App\Core\Identity\Http\Controllers\ForgotPasswordController` | Disetujui parity |
| PAR-003 | Core | `/workspaces` | `frontend/src/core/tenant/tenant-store.ts` | Pemilihan ruang kerja aktif dan peran | `/workspaces` | `App\Core\Tenancy\Http\Controllers\WorkspaceController@index` | Disetujui parity |
| PAR-004 | Core | Global Shell | `frontend/src/app/layout/AppShell.tsx` | Sidebar dan topbar dengan navigasi modul | Shared Shell | `resources/js/layouts/RudimahAppShell.vue` | Disetujui parity |
| PAR-005 | Finance | `/finance/dashboard` | `frontend/src/features/finance/dashboard/pages/FinanceDashboardPage.tsx` | Kartu metrik, arus kas, budget, tabungan | `/t/{tenant}/apps/pekan/dashboard` | `App\Modules\Pekan\Http\Controllers\DashboardController@index` | Disetujui parity |
| PAR-006 | Finance | `/finance/transactions` | `frontend/src/features/finance/transactions/pages/TransactionListPage.tsx` | Tabel mutasi dengan filter tanggal dan kategori | `/t/{tenant}/apps/pekan/transactions` | `App\Modules\Pekan\Http\Controllers\TransactionController@index` | Disetujui parity |
| PAR-007 | Finance | `/finance/transactions/new` | `frontend/src/features/finance/transactions/pages/TransactionCreatePage.tsx` | Form tambah transaksi tunggal dan multi-item | `/t/{tenant}/apps/pekan/transactions/create` | `App\Modules\Pekan\Http\Controllers\TransactionController@create` | Disetujui parity |
| PAR-008 | Finance | `/finance/transactions/:id` | `frontend/src/features/finance/transactions/pages/TransactionDetailPage.tsx` | Rincian kuitansi, baris item, lampiran file | `/t/{tenant}/apps/pekan/transactions/{id}` | `App\Modules\Pekan\Http\Controllers\TransactionController@show` | Disetujui parity |
| PAR-009 | Finance | `/finance/budgets` | `frontend/src/features/finance/budgets/pages/BudgetsListPage.tsx` | Daftar alokasi anggaran dan progress belanja | `/t/{tenant}/apps/pekan/budgets` | `App\Modules\Pekan\Http\Controllers\BudgetController@index` | Disetujui parity |
| PAR-010 | Finance | `/finance/budgets/new` | `frontend/src/features/finance/budgets/pages/BudgetCreatePage.tsx` | Penetapan limit belanja per kategori | `/t/{tenant}/apps/pekan/budgets/create` | `App\Modules\Pekan\Http\Controllers\BudgetController@create` | Disetujui parity |
| PAR-011 | Finance | `/finance/savings` | `frontend/src/features/finance/savings/pages/SavingsListPage.tsx` | Daftar target celengan dan impian dana | `/t/{tenant}/apps/pekan/savings` | `App\Modules\Pekan\Http\Controllers\SavingsController@index` | Disetujui parity |
| PAR-012 | Finance | `/finance/savings/new` | `frontend/src/features/finance/savings/pages/SavingsCreatePage.tsx` | Form pembuatan pos simpanan | `/t/{tenant}/apps/pekan/savings/create` | `App\Modules\Pekan\Http\Controllers\SavingsController@create` | Disetujui parity |
| PAR-013 | Finance | `/finance/reminders` | `frontend/src/features/finance/reminders/pages/RemindersPage.tsx` | Pengingat tagihan dan pelacakan tenor | `/t/{tenant}/apps/pekan/reminders` | `App\Modules\Pekan\Http\Controllers\ReminderController@index` | Disetujui parity |
| PAR-014 | Finance | `/finance/master-data` | `frontend/src/features/finance/masterdata/pages/FinanceMasterDataPage.tsx` | Manajemen dompet, rekening, dan kategori | `/t/{tenant}/apps/pekan/master-data` | `App\Modules\Pekan\Http\Controllers\MasterDataController@index` | Disetujui parity |
| PAR-015 | Finance | `/finance/receipt-scan` | `frontend/src/features/finance/receipts/pages/ReceiptScanPage.tsx` | Unggah struk, ekstraksi OCR, verifikasi user | `/t/{tenant}/apps/pekan/receipt-scan` | `App\Modules\Pekan\Http\Controllers\ReceiptScanController@index` | Disetujui parity |
| PAR-016 | Finance | `/finance/reports` | `frontend/src/features/finance/reports/pages/ReportsPage.tsx` | Grafik arus kas, breakdown kategori, ekspor data | `/t/{tenant}/apps/pekan/reports` | `App\Modules\Pekan\Http\Controllers\ReportController@index` | Disetujui parity |
| PAR-017 | Finance | `/finance/settings` | `frontend/src/features/finance/settings/pages/SettingsPage.tsx` | Konfigurasi notifikasi, bot WhatsApp, log audit | `/t/{tenant}/apps/pekan/settings` | `App\Modules\Pekan\Http\Controllers\SettingsController@index` | Disetujui parity |

## Catatan Paritas
1. Seluruh logika kalkulasi finansial mempertahankan penggunaan satuan nilai minor untuk menjamin integritas uang tanpa pembulatan desimal mengambang.
2. Seluruh tampilan antarmuka diselaraskan ke dalam `RudimahAppShell` dengan token desain semantik terpusat dan tanpa dependensi komponen eksternal ad-hoc.
3. Hak akses diuji pada tingkat server menggunakan Laravel Policies untuk menjamin isolasi data antar ruang kerja.
