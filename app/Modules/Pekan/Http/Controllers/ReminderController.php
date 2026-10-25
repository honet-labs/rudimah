<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController
{
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
        ]);

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
        $reminder->status = 'pending';
        $reminder->created_by = auth()->id();
        $reminder->save();

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/reminders")
            ->with('status', 'Pengingat tagihan berhasil dijadwalkan.');
    }
}
