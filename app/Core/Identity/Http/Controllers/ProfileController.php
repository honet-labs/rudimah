<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\Models\ExternalChatPairing;
use App\Core\Identity\Models\ExternalChatSession;
use App\Core\Identity\Models\SystemSetting;
use App\Core\Notifications\Services\ExternalChatGatewayService;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $tenant = TenantContext::get() ?? $user->memberships()->first()?->tenant;

        $userTenants = $user->memberships()
            ->with('tenant')
            ->get()
            ->filter(fn ($m) => $m->tenant && $m->status === 'active' && $m->tenant->status === 'active')
            ->map(fn ($m) => [
                'id' => $m->tenant->id,
                'name' => $m->tenant->name,
                'slug' => $m->tenant->slug,
            ])
            ->values();

        $chatSessions = ExternalChatSession::where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'channel' => $s->channel,
                'channel_user_id' => $s->channel_user_id,
                'tenant_id' => $s->tenant_id,
                'tenant_name' => $s->tenant?->name ?? 'Ruang Kerja',
                'last_interaction_at' => $s->last_interaction_at?->diffForHumans(),
                'created_at' => $s->created_at?->format('d M Y H:i'),
            ]);

        $activePairing = ExternalChatPairing::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('tenant')
            ->latest()
            ->first();

        $telegramUsername = SystemSetting::get('telegram_bot_username', env('TELEGRAM_BOT_USERNAME', ''));
        $wahaEnabled = filter_var(SystemSetting::get('waha_enabled', !empty(env('WAHA_API_URL'))), FILTER_VALIDATE_BOOLEAN);

        return Inertia::render('Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ],
            'tenant' => $tenant,
            'userTenants' => $userTenants,
            'chatSessions' => $chatSessions,
            'activePairing' => $activePairing ? [
                'code' => $activePairing->code,
                'tenant_id' => $activePairing->tenant_id,
                'tenant_name' => $activePairing->tenant?->name,
                'expires_at' => $activePairing->expires_at->toIso8601String(),
                'minutes_remaining' => max(1, (int) ceil(now()->diffInSeconds($activePairing->expires_at) / 60)),
            ] : null,
            'botInfo' => [
                'telegram_username' => $telegramUsername,
                'whatsapp_enabled' => $wahaEnabled,
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
        ]);

        $request->user()->update([
            'full_name' => $validated['name'],
        ]);

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Kata sandi saat ini yang Anda masukkan tidak cocok.',
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return back()->with('status', 'Kata sandi Anda berhasil diperbarui.');
    }

    public function generateChatPairingCode(Request $request, ExternalChatGatewayService $gateway): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'tenant_id' => 'required|string',
            'channel' => 'nullable|string|in:any,whatsapp,telegram',
        ]);

        $membership = $user->memberships()
            ->where('tenant_id', $validated['tenant_id'])
            ->where('status', 'active')
            ->first();

        if (!$membership || !$membership->tenant) {
            return back()->with('error', 'Ruang kerja yang dipilih tidak valid atau Anda bukan anggota aktif.');
        }

        $pairing = $gateway->generatePairingCode($user, $membership->tenant, $validated['channel'] ?? 'any');

        return back()->with('status', "Kode hubung berhasil dibuat: {$pairing->code}. Kirimkan kode ini ke bot dalam waktu 15 menit.");
    }

    public function disconnectChatSession(Request $request, string $sessionId): RedirectResponse
    {
        $user = $request->user();

        $session = ExternalChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if ($session) {
            $session->delete();
            return back()->with('status', 'Tautan obrolan berhasil diputuskan.');
        }

        return back()->with('error', 'Sesi obrolan tidak ditemukan.');
    }
}

