<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantAssistantService
{
    protected AiProviderManager $providerManager;
    protected TenantFinancialContextBuilder $contextBuilder;

    public function __construct(
        AiProviderManager $providerManager,
        TenantFinancialContextBuilder $contextBuilder
    ) {
        $this->providerManager = $providerManager;
        $this->contextBuilder = $contextBuilder;
    }

    /**
     * Memulai percakapan baru untuk tenant dan user aktif.
     */
    public function startConversation(string $title = 'Percakapan Baru'): AiConversation
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        if (!$tenantId || !$userId) {
            throw new \RuntimeException('Konteks ruang kerja dan pengguna wajib aktif.');
        }

        PlatformAiService::applyRuntimeConfig();

        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        $activeProvider = $setting?->provider ?: config('rudimah.ai.default_provider', '9router');
        $activeModel = $setting?->model ?: config("rudimah.ai.{$activeProvider}_model", 'high-models-agent');

        $conversation = AiConversation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $title,
            'provider' => $activeProvider,
            'model' => $activeModel,
        ]);

        AuditLogger::log('ai_conversation_created', 'ai_conversations', $conversation->id, null, [
            'provider' => $activeProvider,
            'model' => $activeModel,
        ]);

        return $conversation;
    }

    /**
     * Mengirim pesan ke AI Assistant dan mendapatkan respon dalam isolasi tenant.
     */
    public function sendMessage(AiConversation $conversation, string $userMessageText): AiMessage
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        // Verifikasi isolasi keamanan: percakapan wajib milik tenant dan user yang sedang aktif
        if ($conversation->tenant_id !== $tenantId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Percakapan ini berada di luar ruang kerja Anda.');
        }

        if ($conversation->user_id !== $userId && !auth()->user()?->is_super_admin) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Anda tidak memiliki akses ke percakapan ini.');
        }

        // Terapkan konfigurasi AI platform runtime sebelum pemrosesan
        PlatformAiService::applyRuntimeConfig();

        // 1. Simpan pesan pengguna
        $userMessage = AiMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenantId,
            'role' => 'user',
            'content' => $userMessageText,
        ]);

        // Perbarui judul percakapan bila masih default
        if ($conversation->title === 'Percakapan Baru') {
            $conversation->update([
                'title' => mb_strimwidth($userMessageText, 0, 40, '...'),
            ]);
        }

        // 2. Ambil konfigurasi AI Ruang Kerja atau Platform Global
        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        if ($setting && !$setting->is_enabled) {
            return AiMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'tenant_id' => $tenantId,
                'role' => 'assistant',
                'content' => 'Layanan Asisten AI saat ini sedang dinonaktifkan oleh administrator ruang kerja Anda.',
                'metadata' => ['disabled' => true],
            ]);
        }

        // Tentukan provider dan model aktif
        $activeProvider = $setting?->provider ?: config('rudimah.ai.default_provider', '9router');
        $activeModel = $setting?->model ?: config("rudimah.ai.{$activeProvider}_model", 'high-models-agent');

        // Jika percakapan lama memiliki provider yang berbeda atau belum terset, sinkronkan ke provider aktif
        if ($conversation->provider !== $activeProvider || empty($conversation->model)) {
            $conversation->update([
                'provider' => $activeProvider,
                'model' => $activeModel,
            ]);
        }

        // 3. Bangun prompt sistem dengan batasan ketat tenant dan konteks data riil
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($setting?->system_instructions);

        // 4. Siapkan riwayat percakapan (maksimal 10 pesan terakhir)
        $pastMessages = AiMessage::where('conversation_id', $conversation->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($pastMessages as $pm) {
            $messagesPayload[] = [
                'role' => $pm->role,
                'content' => $pm->content,
            ];
        }

        // 5. Panggil provider AI yang sesuai
        $provider = $this->providerManager->resolve($activeProvider, $setting);
        $options = [
            'model' => $activeModel,
            'temperature' => $setting?->temperature ?? 0.7,
            'api_endpoint' => !empty($setting?->api_endpoint) ? $setting->api_endpoint : null,
        ];

        try {
            $response = $provider->chat($messagesPayload, $options);
            $replyContent = $response['content'] ?? '';
            $tokensUsed = (int) ($response['tokens_used'] ?? 0);
            $meta = array_merge($response['metadata'] ?? [], [
                'model' => $response['model'] ?? $activeModel,
                'tokens_used' => $tokensUsed,
                'tenant_verified' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Log::error("[TenantAssistantService] Gagal memproses respon AI ({$activeProvider}): " . $e->getMessage());
            $replyContent = "Terjadi kendala saat menghubungi layanan AI ({$activeProvider}): " . $e->getMessage();
            $tokensUsed = 0;
            $meta = [
                'error' => true,
                'provider' => $activeProvider,
                'model' => $activeModel,
                'error_detail' => $e->getMessage(),
            ];
        }

        // 6. Simpan respon asisten
        $assistantMessage = AiMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenantId,
            'role' => 'assistant',
            'content' => $replyContent,
            'metadata' => array_merge($meta, [
                'tokens_used' => $tokensUsed,
            ]),
        ]);

        return $assistantMessage;
    }
}

