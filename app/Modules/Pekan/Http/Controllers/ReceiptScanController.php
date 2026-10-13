<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Services\ReceiptOcrService;
use App\Modules\Pekan\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptScanController
{
    public function __construct(
        protected ReceiptOcrService $ocrService,
        protected TransactionService $transactionService
    ) {}

    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        return Inertia::render('Pekan/ReceiptScan/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'receipt_file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $this->ocrService->processUpload($request->file('receipt_file'));

        $tenant = TenantContext::get();
        return redirect("/t/{$tenant->slug}/apps/pekan/receipt-scan")
            ->with('status', 'Struk berhasil diproses untuk ditinjau.');
    }
}
