<?php

use App\Core\AI\Http\Controllers\AiAssistantController;
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
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
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

// Rute Terikat Ruang Kerja (Tenant Scoped: Asisten AI & Modul Aplikasi)
Route::prefix('t/{tenantSlug}')->middleware(['auth', ResolveTenantMiddleware::class])->group(function () {
    // Asisten Cerdas AI (Terisolasi per Tenant)
    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::get('/', [AiAssistantController::class, 'index'])->name('index');
        Route::post('/conversations', [AiAssistantController::class, 'store'])->name('conversations.store');
        Route::get('/conversations/{id}', [AiAssistantController::class, 'show'])->name('conversations.show');
        Route::post('/conversations/{id}/messages', [AiAssistantController::class, 'sendMessage'])->name('messages.send');
        Route::delete('/conversations/{id}', [AiAssistantController::class, 'destroy'])->name('conversations.destroy');
        Route::put('/settings', [AiAssistantController::class, 'updateSettings'])->name('settings.update');
    });

    // Rute Modul Aplikasi
    Route::prefix('apps')->group(function () {
        // Modul PEKAN
        Route::prefix('pekan')->name('pekan.')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // Transaksi
            Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
            Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show')->where('id', '[0-9a-zA-Z\-]{10,}');
            Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('transactions.destroy')->where('id', '[0-9a-zA-Z\-]{10,}');

            // Anggaran
            Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
            Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');

            // Tabungan
            Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
            Route::get('/savings/create', [SavingsController::class, 'create'])->name('savings.create');
            Route::post('/savings', [SavingsController::class, 'store'])->name('savings.store');

            // Pengingat Tagihan
            Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
            Route::get('/reminders/create', [ReminderController::class, 'create'])->name('reminders.create');
            Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');

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
});
