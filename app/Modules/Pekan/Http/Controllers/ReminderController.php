<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceReminder;
use App\Modules\Pekan\Models\FinanceReminderPayment;
use App\Modules\Pekan\Services\AttachmentUploadService;
use App\Modules\Pekan\Services\TransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController
{
    protected AttachmentUploadService $uploadService;
    protected TransactionService $transactionService;

    public function __construct(
        ?AttachmentUploadService $uploadService = null,
        ?TransactionService $transactionService = null
    ) {
        $this->uploadService = $uploadService ?: new AttachmentUploadService();
        $this->transactionService = $transactionService ?: new TransactionService();
    }

    protected function ensureSchema(): void
    {
        try {
            $schema = Schema::connection('pekan');
            if ($schema->hasTable('finance_reminders')) {
                if (!$schema->hasColumn('finance_reminders', 'category_id')) {
                    $schema->table('finance_reminders', function (Blueprint $table) {
                        $table->uuid('category_id')->nullable()->after('tenant_id')->index();
                    });
                }
                if (!$schema->hasColumn('finance_reminders', 'images')) {
                    $schema->table('finance_reminders', function (Blueprint $table) {
                        $table->json('images')->nullable()->after('description');
                    });
                }
            }

            if (!$schema->hasTable('finance_reminder_payments')) {
                $schema->create('finance_reminder_payments', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->uuid('tenant_id')->index();
                    $table->uuid('reminder_id')->index();
                    $table->uuid('transaction_id')->nullable()->index();
                    $table->bigInteger('amount_minor');
                    $table->char('currency', 3)->default('IDR');
                    $table->date('payment_date')->index();
                    $table->integer('tenor_number')->nullable();
                    $table->text('notes')->nullable();
                    $table->json('images')->nullable();
                    $table->uuid('created_by')->nullable();
                    $table->softDeletes();
                    $table->timestamps();
                    $table->index(['tenant_id', 'reminder_id']);
                });
            }
        } catch (\Throwable) {
            // Abaikan jika migrasi sudah berjalan atau permission terbatas
        }
    }

    public function index(Request $request): Response
    {
        $this->ensureSchema();
        $tenant = TenantContext::get();
        $user = $request->user();

        $reminders = FinanceReminder::with(['category', 'payments'])
            ->orderBy('due_date')
            ->get();

        $categories = FinanceCategory::where('is_active', true)
            ->where('category_type', 'expense')
            ->orderBy('name')
            ->get();

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
            'categories' => $categories,
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $categories = FinanceCategory::where('is_active', true)
            ->where('category_type', 'expense')
            ->orderBy('name')
            ->get();

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
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, ?string $tenantSlug = null): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'is_installment' => 'nullable|boolean',
            'category_id' => 'nullable|string',
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

        $isInstallment = $request->boolean('is_installment');

        $reminder = new FinanceReminder();
        $reminder->id = (string) Str::uuid();
        $reminder->tenant_id = $tenant->id;
        $reminder->category_id = $validated['category_id'] ?? null;
        $reminder->title = $validated['title'];
        $reminder->amount_minor = $validated['amount_minor'];
        $reminder->currency = 'Rp';
        $reminder->due_date = $validated['due_date'];
        $reminder->repeat_interval = $validated['repeat_interval'];
        $reminder->total_tenor = $isInstallment ? ($validated['total_tenor'] ?? null) : null;
        $reminder->current_tenor = $isInstallment ? ($validated['current_tenor'] ?? 1) : null;
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
            } elseif (str_contains($e->getMessage(), "Unknown column 'category_id'")) {
                Schema::connection('pekan')->table('finance_reminders', function (Blueprint $table) {
                    $table->uuid('category_id')->nullable()->after('tenant_id');
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

        $categories = FinanceCategory::where('is_active', true)
            ->where('category_type', 'expense')
            ->orderBy('name')
            ->get();

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
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $reminderId = $request->route('id') ?: $id;
        $reminder = FinanceReminder::findOrFail($reminderId);

        $validated = $request->validate([
            'is_installment' => 'nullable|boolean',
            'category_id' => 'nullable|string',
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

        $isInstallment = $request->boolean('is_installment');

        $reminderData = [
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'amount_minor' => $validated['amount_minor'],
            'due_date' => $validated['due_date'],
            'repeat_interval' => $validated['repeat_interval'],
            'total_tenor' => $isInstallment ? ($validated['total_tenor'] ?? null) : null,
            'current_tenor' => $isInstallment ? ($validated['current_tenor'] ?? 1) : null,
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
            } elseif (str_contains($e->getMessage(), "Unknown column 'category_id'")) {
                Schema::connection('pekan')->table('finance_reminders', function (Blueprint $table) {
                    $table->uuid('category_id')->nullable()->after('tenant_id');
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

    public function pay(Request $request, string $tenantSlug, string $id): JsonResponse|RedirectResponse
    {
        $this->ensureSchema();
        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        $reminderId = $request->route('id') ?: $id;
        $reminder = FinanceReminder::findOrFail($reminderId);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount_minor' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $uploadedImages = [];
        if ($request->hasFile('images')) {
            $uploadedImages = $this->uploadService->uploadMultiple($request->file('images'), 'reminders');
        }

        // 1. Buat transaksi pengeluaran di buku kas
        $txDescription = 'Pembayaran Tagihan: ' . $reminder->title;
        if (!empty($reminder->total_tenor)) {
            $txDescription .= ' (Tenor ' . ($reminder->current_tenor ?: 1) . '/' . $reminder->total_tenor . ')';
        }

        $txData = [
            'type' => 'expense',
            'amount_minor' => (int) $validated['amount_minor'],
            'transaction_date' => $validated['payment_date'],
            'category_id' => $reminder->category_id,
            'description' => $txDescription,
            'notes' => $validated['notes'] ?? ('Pembayaran tagihan ' . $reminder->title),
            'images' => $uploadedImages,
        ];

        $transaction = $this->transactionService->createTransaction($txData);

        // 2. Catat riwayat pembayaran tagihan
        $payment = FinanceReminderPayment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'reminder_id' => $reminder->id,
            'transaction_id' => $transaction->id,
            'amount_minor' => (int) $validated['amount_minor'],
            'currency' => 'Rp',
            'payment_date' => $validated['payment_date'],
            'tenor_number' => $reminder->current_tenor,
            'notes' => $validated['notes'] ?? null,
            'images' => $uploadedImages,
            'created_by' => auth()->id(),
        ]);

        // 3. Update status tagihan atau kemajuan tenor
        if (!empty($reminder->total_tenor)) {
            if ($reminder->current_tenor >= $reminder->total_tenor) {
                $reminder->status = 'paid';
            } else {
                $reminder->current_tenor = $reminder->current_tenor + 1;
                try {
                    $dueDate = \Carbon\Carbon::parse($reminder->due_date);
                    $reminder->due_date = match ($reminder->repeat_interval) {
                        'weekly' => $dueDate->addWeek()->toDateString(),
                        'yearly' => $dueDate->addYear()->toDateString(),
                        default => $dueDate->addMonth()->toDateString(),
                    };
                } catch (\Throwable) {
                    // Abaikan jika parsing tanggal terkendala
                }
            }
        } elseif ($reminder->repeat_interval === 'once') {
            $reminder->status = 'paid';
        } else {
            try {
                $dueDate = \Carbon\Carbon::parse($reminder->due_date);
                $reminder->due_date = match ($reminder->repeat_interval) {
                    'daily' => $dueDate->addDay()->toDateString(),
                    'weekly' => $dueDate->addWeek()->toDateString(),
                    'yearly' => $dueDate->addYear()->toDateString(),
                    default => $dueDate->addMonth()->toDateString(),
                };
            } catch (\Throwable) {
                // Abaikan
            }
        }

        $reminder->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran tagihan berhasil dicatat ke buku kas.',
                'payment' => $payment,
            ]);
        }

        return redirect("/t/{$slug}/apps/pekan/reminders")
            ->with('status', 'Pembayaran tagihan berhasil dicatat ke buku kas.');
    }
}
