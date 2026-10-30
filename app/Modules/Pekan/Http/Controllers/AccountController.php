<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountController
{
    /**
     * Simpan metode pembayaran / akun keuangan baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'account_type' => 'required|string|in:cash,bank,ewallet,credit_card,other',
            'opening_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $balanceMinor = isset($validated['opening_balance']) ? (int) round(((float) $validated['opening_balance'])) : 0;

        FinanceAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => trim($validated['name']),
            'account_type' => $validated['account_type'],
            'currency' => 'IDR',
            'opening_balance_minor' => $balanceMinor,
            'current_balance_minor' => $balanceMinor,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', "Metode pembayaran '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Perbarui data metode pembayaran / akun keuangan.
     */
    public function update(Request $request, string $tenantSlug, ?string $id = null): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $accountId = $request->route('id') ?: $id;
        $account = FinanceAccount::where('id', $accountId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'account_type' => 'required|string|in:cash,bank,ewallet,credit_card,other',
            'is_active' => 'required|boolean',
        ]);

        $account->update([
            'name' => trim($validated['name']),
            'account_type' => $validated['account_type'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('status', "Metode pembayaran '{$account->name}' berhasil diperbarui.");
    }

    /**
     * Hapus metode pembayaran jika belum memiliki relasi transaksi.
     */
    public function destroy(Request $request, string $tenantSlug, ?string $id = null): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $accountId = $request->route('id') ?: $id;
        $account = FinanceAccount::where('id', $accountId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $hasTransactions = FinanceTransaction::where('account_id', $account->id)->exists();
        if ($hasTransactions) {
            return back()->with('error', "Metode pembayaran '{$account->name}' tidak dapat dihapus karena sudah memiliki riwayat transaksi. Silakan ubah statusnya menjadi 'Nonaktif'.");
        }

        $accountName = $account->name;
        $account->delete();

        return back()->with('status', "Metode pembayaran '{$accountName}' berhasil dihapus.");
    }
}
