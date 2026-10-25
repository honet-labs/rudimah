<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Cek apakah user wajib MFA
            if ($user->mfaMethods()->where('is_verified', true)->exists()) {
                $request->session()->forget('mfa_verified');
                $request->session()->put('mfa_required', true);

                $firstMethod = $user->mfaMethods()->where('is_verified', true)->first();
                $request->session()->put('mfa_challenge_type', $firstMethod?->method_type ?: 'totp');

                return redirect()->route('mfa.challenge');
            }

            $request->session()->put('mfa_verified', true);
            $request->session()->forget('mfa_required');

            return redirect()->intended('/workspaces');
        }

        return back()->with('error', 'Kombinasi email dan kata sandi tidak cocok.');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($validated['email']));
        $userId = (string) Str::uuid();
        $user = User::create([
            'id' => $userId,
            'email' => $email,
            'full_name' => $validated['name'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        // Buat tenant default personal
        $tenantId = (string) Str::uuid();
        $tenantSlug = Str::slug($validated['name'] . ' Personal');
        $tenant = Tenant::create([
            'id' => $tenantId,
            'code' => $tenantSlug,
            'name' => $validated['name'] . ' Personal',
            'slug' => $tenantSlug,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->put('mfa_verified', true);

        return redirect("/t/{$tenant->slug}/apps/pekan/dashboard");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public static function hasConfiguredNotificationChannel(): bool
    {
        $mailConfigured = !empty(config('rudimah.notifications.mail.host', env('MAIL_HOST'))) && env('MAIL_MAILER', 'smtp') !== 'log';
        $telegramConfigured = !empty(config('rudimah.notifications.telegram.bot_token', env('TELEGRAM_BOT_TOKEN')));
        $whatsappConfigured = !empty(config('rudimah.notifications.whatsapp.waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL'))));

        return $mailConfigured || $telegramConfigured || $whatsappConfigured;
    }

    public function showForgotPassword(): Response
    {
        $channelsConfigured = self::hasConfiguredNotificationChannel();

        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'error' => session('error'),
            'channelsConfigured' => $channelsConfigured,
            'unconfiguredNotice' => !$channelsConfigured
                ? 'Kanal pengiriman notifikasi (Email SMTP, Telegram Bot, atau WhatsApp WAHA) belum dikonfigurasi di Admin Control Panel. Silakan lakukan setup konfigurasi kanal terlebih dahulu di Admin Control Panel atau berkas .env.'
                : null,
        ]);
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        if (!self::hasConfiguredNotificationChannel()) {
            return back()->with('error', 'Kanal pengiriman notifikasi (Email SMTP, Telegram Bot, atau WhatsApp WAHA) belum dikonfigurasi di Admin Control Panel. Anda harus melakukan setup konfigurasi kanal pengiriman terlebih dahulu sebelum dapat mengirim tautan pemulihan kata sandi.');
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::connection('platform')->table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $resetUrl = url("/reset-password/{$token}?email=" . urlencode($email));

            if (app()->environment('local', 'testing')) {
                Log::info("[RUDIMAH] [DEV ONLY] Tautan pemulihan kata sandi untuk {$email}: {$resetUrl}");
            } else {
                Log::info("[RUDIMAH] Permintaan tautan pemulihan kata sandi dibuat untuk {$email}.");
            }

            try {
                Mail::raw(
                    "Halo {$user->full_name},\n\nAnda menerima email ini karena ada permintaan pemulihan kata sandi untuk akun RUDIMAH Anda.\n\nSilakan klik tautan di bawah ini untuk mengatur ulang kata sandi Anda:\n{$resetUrl}\n\nTautan ini hanya berlaku selama 60 menit.\n\nJika Anda tidak meminta pengaturan ulang ini, abaikan email ini.",
                    function ($message) use ($email) {
                        $message->to($email)
                            ->subject('Permintaan Pemulihan Kata Sandi RUDIMAH');
                    }
                );
            } catch (\Throwable $e) {
                Log::warning("[RUDIMAH] Gagal mengirim email reset via SMTP: " . $e->getMessage());
            }
        }

        return back()->with('status', 'Jika alamat email terdaftar, instruksi pemulihan kata sandi telah dikirim.');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($validated['email']));

        $record = DB::connection('platform')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            return back()->with('error', 'Token pemulihan tidak valid atau telah digunakan.');
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::connection('platform')
                ->table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return back()->with('error', 'Token pemulihan telah kedaluwarsa. Silakan minta tautan baru.');
        }

        if (!Hash::check($validated['token'], $record->token) && $validated['token'] !== $record->token) {
            return back()->with('error', 'Token pemulihan kata sandi tidak valid.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return back()->with('error', 'Pengguna dengan email tersebut tidak ditemukan.');
        }

        $user->password = $validated['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();

        DB::connection('platform')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        DB::connection('platform')
            ->table('auth_sessions')
            ->where('user_id', $user->id)
            ->delete();

        return redirect('/login')->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.');
    }
}
