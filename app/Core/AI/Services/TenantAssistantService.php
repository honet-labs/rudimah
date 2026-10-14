<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
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

        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        $provider = $setting?->provider ?: config('rudimah.ai.default_provider', 'gemini');
        $model = $setting?->model ?: 'gemini-1.5-flash';

        $conversation = AiConversation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $title,
            'provider' => $provider,
            'model' => $model,
        ]);

        AuditLogger::log('ai_conversation_created', 'ai_conversations', $conversation->id, null, [
            'provider' => $provider,
            'model' => $model,
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

        // 2. Ambil konfigurasi AI Ruang Kerja
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

        // 3. Bangun prompt sistem dengan batasan ketat tenant dan konteks data
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
        $provider = $this->providerManager->resolve($conversation->provider, $setting);
        $options = [
            'model' => $conversation->model ?: $setting?->model,
            'temperature' => $setting?->temperature ?? 0.7,
            'api_endpoint' => $setting?->api_endpoint,
        ];

        $response = $provider->chat($messagesPayload, $options);

        // 6. Simpan respon asisten
        $assistantMessage = AiMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenantId,
            'role' => 'assistant',
            'content' => $response['content'],
            'metadata' => array_merge($response['metadata'] ?? [], [
                'model' => $response['model'],
                'tokens_used' => $response['tokens_used'],
                'tenant_verified' => $tenantId,
            ]),
        ]);

        return $assistantMessage;
    }
}
