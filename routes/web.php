<?php

use App\Core\AI\Http\Controllers\AiAssistantController;
use App\Core\Identity\Http\Controllers\AdminController;
use App\Core\Identity\Http\Controllers\AuthController;
use App\Core\Identity\Http\Controllers\ProfileController;
use App\Core\Identity\Http\Controllers\WorkspaceController;
use App\Core\MFA\Http\Controllers\MfaController;
use App\Core\MFA\Http\Middleware\EnsureMfaVerified;
use App\Core\Tenancy\ResolveTenantMiddleware;
use App\Modules\App1\Http\Controllers\App1Controller;
use App\Modules\App2\Http\Controllers\App2Controller;
use App\Modules\Pekan\Http\Controllers\AccountController;
use App\Modules\Pekan\Http\Controllers\BudgetController;
use App\Modules\Pekan\Http\Controllers\CategoryController;
use App\Modules\Pekan\Http\Controllers\DashboardController;
use App\Modules\Pekan\Http\Controllers\ReceiptScanController;
use App\Modules\Pekan\Http\Controllers\ReminderController;
use App\Modules\Pekan\Http\Controllers\ReportController;
use App\Modules\Pekan\Http\Controllers\SavingsController;
use App\Modules\Pekan\Http\Controllers\SettingsController;
use App\Modules\Pekan\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Rute Publik & Tamu
Route::get('/up', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});
Route::get('/health', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

Route::match(['get', 'head'], '/', function (\Illuminate\Http\Request $request) {
    if ($request->isMethod('head')) {
        return response('', 200);
    }
    return auth()->check() ? redirect('/workspaces') : redirect('/login');
});

// Endpoint ringan untuk refresh CSRF token sesi aktif secara dinamis
Route::get('/csrf-token', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
    ]);
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

// Rute Portal Utama RUDIMAH (Telah Login & Lolos Verifikasi MFA)
Route::middleware(['auth', EnsureMfaVerified::class])->group(function () {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/chat-pairings', [ProfileController::class, 'generateChatPairingCode'])->name('profile.chat-pairings.generate');
    Route::delete('/profile/chat-sessions/{session}', [ProfileController::class, 'disconnectChatSession'])->name('profile.chat-sessions.destroy');
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/admin/notifications', [AdminController::class, 'updateNotifications'])->name('admin.notifications.update');
    Route::post('/admin/notifications/test', [AdminController::class, 'testNotification'])->name('admin.notifications.test');
    Route::post('/admin/ai', [AdminController::class, 'updateAi'])->name('admin.ai.update');
    Route::post('/admin/ai/test', [AdminController::class, 'testAi'])->name('admin.ai.test');
    Route::post('/admin/ai/models', [AdminController::class, 'detectAiModels'])->name('admin.ai.models');

    // Peluncur Cerdas Aplikasi Global (Mencegah 404 ketika diakses tanpa slug tenant spesifik)
    Route::get('/apps', [WorkspaceController::class, 'launchApp'])->name('apps.index');
    Route::get('/apps/{appSlug}', [WorkspaceController::class, 'launchApp'])->name('apps.launch');
});

// Rute Terikat Ruang Kerja (Tenant Scoped: Asisten AI & Modul Aplikasi)
Route::prefix('t/{tenantSlug}')->middleware(['auth', EnsureMfaVerified::class, ResolveTenantMiddleware::class])->group(function () {
    // Pengalihan cerdas jika pengguna mengakses akar URL ruang kerja
    Route::get('/', function (string $tenantSlug) {
        return redirect("/t/{$tenantSlug}/apps/pekan/dashboard");
    });

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
        // Pengalihan cerdas jika pengguna mengakses /t/{tenantSlug}/apps langsung
        Route::get('/', function (string $tenantSlug) {
            return redirect("/t/{$tenantSlug}/apps/pekan/dashboard");
        });

        // Modul PEKAN
        Route::prefix('pekan')->name('pekan.')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

            // Transaksi
            Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
            Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::get('/transactions/{id}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
            Route::put('/transactions/{id}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');
            Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

            // Anggaran
            Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
            Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
            Route::get('/budgets/{id}/edit', [BudgetController::class, 'edit'])->name('budgets.edit');
            Route::put('/budgets/{id}', [BudgetController::class, 'update'])->name('budgets.update');
            Route::delete('/budgets/{id}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

            // Tabungan
            Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
            Route::get('/savings/create', [SavingsController::class, 'create'])->name('savings.create');
            Route::post('/savings', [SavingsController::class, 'store'])->name('savings.store');
            Route::get('/savings/{id}/edit', [SavingsController::class, 'edit'])->name('savings.edit');
            Route::put('/savings/{id}', [SavingsController::class, 'update'])->name('savings.update');
            Route::delete('/savings/{id}', [SavingsController::class, 'destroy'])->name('savings.destroy');

            // Pengingat Tagihan
            Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
            Route::get('/reminders/create', [ReminderController::class, 'create'])->name('reminders.create');
            Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
            Route::get('/reminders/{id}/edit', [ReminderController::class, 'edit'])->name('reminders.edit');
            Route::put('/reminders/{id}', [ReminderController::class, 'update'])->name('reminders.update');
            Route::delete('/reminders/{id}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
            Route::post('/reminders/{id}/pay', [ReminderController::class, 'pay'])->name('reminders.pay');

            // Pindai Struk OCR
            Route::get('/receipt-scan', [ReceiptScanController::class, 'index'])->name('receiptscan.index');
            Route::post('/receipt-scan/upload', [ReceiptScanController::class, 'upload'])->name('receiptscan.upload');
            Route::post('/receipt-scan/confirm', [ReceiptScanController::class, 'confirm'])->name('receiptscan.confirm');

            // Laporan & Pengaturan
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

            // Manajemen Kategori Transaksi
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Manajemen Metode Pembayaran / Akun Keuangan
            Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::put('/accounts/{id}', [AccountController::class, 'update'])->name('accounts.update');
            Route::delete('/accounts/{id}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        });

        // Modul Skeleton App1
        Route::prefix('app1')->name('app1.')->group(function () {
            Route::get('/', [App1Controller::class, 'index'])->name('index');
            Route::get('/dashboard', [App1Controller::class, 'index'])->name('dashboard');
        });

        // Modul Skeleton App2
        Route::prefix('app2')->name('app2.')->group(function () {
            Route::get('/', [App2Controller::class, 'index'])->name('index');
            Route::get('/dashboard', [App2Controller::class, 'index'])->name('dashboard');
        });
    });
});
