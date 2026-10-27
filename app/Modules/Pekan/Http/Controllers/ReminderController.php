<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReminder;
use App\Modules\Pekan\Services\AttachmentUploadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController
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

        $reminders = FinanceReminder::orderBy('due_date')->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Reminders/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'reminders' => $reminders,
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Reminders/Create', [
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
            'title' => 'required|string|max:150',
            'amount_minor' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'repeat_interval' => 'required|in:once,daily,weekly,monthly,yearly',
            'total_tenor' => 'nullable|integer|min:1',
            'current_tenor' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $uploadedImages = [];
        if ($request->hasFile('images')) {
            $uploadedImages = $this->uploadService->uploadMultiple($request->file('images'), 'reminders');
        }

        $reminder = new FinanceReminder();
        $reminder->id = (string) Str::uuid();
        $reminder->tenant_id = $tenant->id;
        $reminder->title = $validated['title'];
        $reminder->amount_minor = $validated['amount_minor'];
        $reminder->currency = 'Rp';
        $reminder->due_date = $validated['due_date'];
        $reminder->repeat_interval = $validated['repeat_interval'];
        $reminder->total_tenor = $validated['total_tenor'] ?? null;
        $reminder->current_tenor = $validated['current_tenor'] ?? 1;
        $reminder->description = $validated['description'] ?? null;
        $reminder->images = $uploadedImages;
        $reminder->status = 'pending';
        $reminder->created_by = auth()->id();

        try {
            $reminder->save();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), "Unknown column 'images'")) {
                Schema::connection('pekan')->table('finance_reminders', function (Blueprint $table) {
                    $table->json('images')->nullable()->after('description');
                });
                $reminder->save();
            } else {
                throw $e;
            }
        }

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/reminders")
            ->with('status', 'Pengingat tagihan berhasil dijadwalkan.');
    }

    public function edit(Request $request, string $tenantSlug, string $id): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        $reminderId = $request->route('id') ?: $id;
        $reminder = FinanceReminder::findOrFail($reminderId);

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Reminders/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'reminder' => $reminder,
        ]);
    }

    public function update(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $reminderId = $request->route('id') ?: $id;
        $reminder = FinanceReminder::findOrFail($reminderId);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'amount_minor' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'repeat_interval' => 'required|in:once,daily,weekly,monthly,yearly',
            'total_tenor' => 'nullable|integer|min:1',
            'current_tenor' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:500',
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
            $newImages = $this->uploadService->uploadMultiple($request->file('images'), 'reminders');
        }

        $allImages = array_merge($existingImages, $newImages);

        $reminderData = [
            'title' => $validated['title'],
            'amount_minor' => $validated['amount_minor'],
            'due_date' => $validated['due_date'],
            'repeat_interval' => $validated['repeat_interval'],
            'total_tenor' => $validated['total_tenor'] ?? null,
            'current_tenor' => $validated['current_tenor'] ?? 1,
            'description' => $validated['description'] ?? null,
            'images' => $allImages,
        ];

        try {
            $reminder->update($reminderData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), "Unknown column 'images'")) {
                Schema::connection('pekan')->table('finance_reminders', function (Blueprint $table) {
                    $table->json('images')->nullable()->after('description');
                });
                $reminder->update($reminderData);
            } else {
                throw $e;
            }
        }

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/reminders")
            ->with('status', 'Pengingat tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $reminderId = $request->route('id') ?: $id;
        $reminder = FinanceReminder::findOrFail($reminderId);

        if (is_array($reminder->images)) {
            foreach ($reminder->images as $img) {
                if (isset($img['url'])) {
                    $this->uploadService->deleteFile($img['url']);
                }
            }
        }

        $reminder->delete();

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/reminders")
            ->with('status', 'Pengingat tagihan berhasil dihapus.');
    }
}

