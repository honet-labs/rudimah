<?php

use App\Core\Identity\Http\Controllers\AdminController;
use App\Core\Identity\Http\Controllers\AuthController;
use App\Core\Identity\Http\Controllers\ProfileController;
use App\Core\Identity\Http\Controllers\WorkspaceController;
use App\Core\MFA\Http\Controllers\MfaController;
use App\Core\Tenancy\ResolveTenantMiddleware;
use App\Modules\App1\Http\Controllers\App1Controller;
use App\Modules\App2\Http\Controllers\App2Controller;
use App\Modules\Pekan\Http\Controllers\BudgetController;
use App\Modules\Pekan\Http\Controllers\DashboardController;
use App\Modules\Pekan\Http\Controllers\MasterDataController;
use App\Modules\Pekan\Http\Controllers\ReceiptScanController;
use App\Modules\Pekan\Http\Controllers\ReminderController;
use App\Modules\Pekan\Http\Controllers\ReportController;
use App\Modules\Pekan\Http\Controllers\SavingsController;
use App\Modules\Pekan\Http\Controllers\SettingsController;
use App\Modules\Pekan\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Rute Publik & Tamu
Route::get('/', function () {
    return auth()->check() ? redirect('/workspaces') : redirect('/login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rute MFA
Route::middleware('auth')->group(function () {
    Route::get('/mfa/challenge', [MfaController::class, 'showChallenge'])->name('mfa.challenge');
    Route::post('/mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify');
});

// Rute Portal Utama RUDIMAH (Telah Login)
Route::middleware('auth')->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
});

// Rute Modul Terikat Ruang Kerja (Tenant & App Scoped)
Route::prefix('t/{tenantSlug}/apps')->middleware(['auth', ResolveTenantMiddleware::class])->group(function () {
    // Modul PEKAN
    Route::prefix('pekan')->name('pekan.')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Transaksi
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

        // Anggaran, Tabungan, Pengingat
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');

        // Master Data
        Route::get('/master-data', [MasterDataController::class, 'index'])->name('masterdata.index');

        // Pindai Struk OCR
        Route::get('/receipt-scan', [ReceiptScanController::class, 'index'])->name('receiptscan.index');
        Route::post('/receipt-scan/upload', [ReceiptScanController::class, 'upload'])->name('receiptscan.upload');

        // Laporan & Pengaturan
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });

    // Modul Skeleton App1
    Route::prefix('app1')->name('app1.')->group(function () {
        Route::get('/', [App1Controller::class, 'index'])->name('index');
    });

    // Modul Skeleton App2
    Route::prefix('app2')->name('app2.')->group(function () {
        Route::get('/', [App2Controller::class, 'index'])->name('index');
    });
});
