<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController
{
    /**
     * Simpan kategori transaksi baru untuk ruang kerja saat ini.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_type' => 'required|string|in:expense,income',
            'parent_id' => 'nullable|string|max:36',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        FinanceCategory::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => trim($validated['name']),
            'category_type' => $validated['category_type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', "Kategori transaksi '{$validated['name']}' berhasil dibuat.");
    }

    /**
     * Perbarui data kategori transaksi yang sudah ada.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_type' => 'required|string|in:expense,income',
            'parent_id' => 'nullable|string|max:36',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $category = FinanceCategory::findOrFail($id);

        $category->update([
            'name' => trim($validated['name']),
            'category_type' => $validated['category_type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'icon' => $validated['icon'] ?? $category->icon,
            'color' => $validated['color'] ?? $category->color,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', "Kategori transaksi '{$category->name}' berhasil diperbarui.");
    }

    /**
     * Hapus kategori transaksi atau nonaktifkan bila sudah terdapat riwayat mutasi kas.
     */
    public function destroy(string $id): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja tidak valid.');
        }

        $category = FinanceCategory::findOrFail($id);
        $name = $category->name;

        // Periksa integritas keuangan: bila sudah digunakan pada transaksi kas, jangan hapus fisik
        $isUsedInTransactions = FinanceTransaction::where('category_id', $category->id)->exists();

        if ($isUsedInTransactions) {
            $category->update(['is_active' => false]);
            return back()->with('status', "Kategori '{$name}' telah memiliki riwayat transaksi, status diubah menjadi nonaktif untuk menjaga arsip buku kas.");
        }

        $category->delete();

        return back()->with('status', "Kategori transaksi '{$name}' berhasil dihapus.");
    }
}
