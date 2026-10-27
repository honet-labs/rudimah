<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceSavings;
use App\Modules\Pekan\Services\AttachmentUploadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController
{
    protected AttachmentUploadService $uploadService;

    public function __construct(?AttachmentUploadService $uploadService = null)
    {
        $this->uploadService = $uploadService ?: new AttachmentUploadService();
    }

    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $savings = FinanceSavings::where('status', 'active')->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Savings/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'savings' => $savings,
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Savings/Create', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
        ]);
    }

    public function store(Request $request, ?string $tenantSlug = null): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'target_amount_minor' => 'required|integer|min:1',
            'current_amount_minor' => 'nullable|integer|min:0',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $uploadedImages = [];
        if ($request->hasFile('images')) {
            $uploadedImages = $this->uploadService->uploadMultiple($request->file('images'), 'savings');
        }

        $savings = new FinanceSavings();
        $savings->id = (string) Str::uuid();
        $savings->tenant_id = $tenant->id;
        $savings->name = $validated['name'];
        $savings->target_amount_minor = $validated['target_amount_minor'];
        $savings->current_amount_minor = $validated['current_amount_minor'] ?? 0;
        $savings->currency = 'Rp';
        $savings->start_date = now()->toDateString();
        $savings->target_date = $validated['target_date'] ?? null;
        $savings->notes = $validated['notes'] ?? null;
        $savings->images = $uploadedImages;
        $savings->status = 'active';
        $savings->created_by = auth()->id();

        try {
            $savings->save();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), "Unknown column 'images'")) {
                Schema::connection('pekan')->table('finance_savings', function (Blueprint $table) {
                    $table->json('images')->nullable()->after('notes');
                });
                $savings->save();
            } else {
                throw $e;
            }
        }

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/savings")
            ->with('status', 'Pos tabungan impian berhasil dibuat.');
    }

    public function edit(Request $request, string $tenantSlug, string $id): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        $savingsId = $request->route('id') ?: $id;
        $saving = FinanceSavings::findOrFail($savingsId);

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Savings/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'saving' => $saving,
        ]);
    }

    public function update(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $savingsId = $request->route('id') ?: $id;
        $saving = FinanceSavings::findOrFail($savingsId);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'target_amount_minor' => 'required|integer|min:1',
            'current_amount_minor' => 'nullable|integer|min:0',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'existing_images' => 'nullable|string',
        ]);

        $existingImages = [];
        if ($request->filled('existing_images')) {
            $decoded = json_decode($request->input('existing_images'), true);
            if (is_array($decoded)) {
                $existingImages = $decoded;
            }
        }

        $newImages = [];
        if ($request->hasFile('images')) {
            $newImages = $this->uploadService->uploadMultiple($request->file('images'), 'savings');
        }

        $allImages = array_merge($existingImages, $newImages);

        $savingData = [
            'name' => $validated['name'],
            'target_amount_minor' => $validated['target_amount_minor'],
            'current_amount_minor' => $validated['current_amount_minor'] ?? 0,
            'target_date' => $validated['target_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'images' => $allImages,
        ];

        try {
            $saving->update($savingData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), "Unknown column 'images'")) {
                Schema::connection('pekan')->table('finance_savings', function (Blueprint $table) {
                    $table->json('images')->nullable()->after('notes');
                });
                $saving->update($savingData);
            } else {
                throw $e;
            }
        }

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/savings")
            ->with('status', 'Pos tabungan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $savingsId = $request->route('id') ?: $id;
        $saving = FinanceSavings::findOrFail($savingsId);

        if (is_array($saving->images)) {
            foreach ($saving->images as $img) {
                if (isset($img['url'])) {
                    $this->uploadService->deleteFile($img['url']);
                }
            }
        }

        $saving->delete();

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/savings")
            ->with('status', 'Pos tabungan berhasil dihapus.');
    }
}

