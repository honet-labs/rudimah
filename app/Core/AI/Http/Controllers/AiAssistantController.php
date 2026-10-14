<?php

namespace App\Core\AI\Http\Controllers;

use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\AI\Services\TenantAssistantService;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\Models\TenantMembership;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController
{
    protected TenantAssistantService $assistantService;

    public function __construct(TenantAssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    /**
     * Tampilan utama Asisten Cerdas Ruang Kerja.
     */
    public function index(Request $request, string $tenantSlug): Response
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403, 'Konteks ruang kerja belum aktif.');
        }

        $userId = auth()->id();

        $conversations = AiConversation::where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();

        $setting = TenantAiSetting::where('tenant_id', $tenant->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->first();

        $canManageSettings = auth()->user()?->is_super_admin || in_array($membership?->role, ['owner', 'admin']);

        return Inertia::render('Assistant/Index', [
            'tenant' => $tenant,
            'conversations' => $conversations,
            'settings' => [
                'provider' => $setting?->provider ?: config('rudimah.ai.default_provider', 'gemini'),
                'model' => $setting?->model ?: 'gemini-1.5-flash',
                'api_endpoint' => $setting?->api_endpoint,
                'has_custom_key' => !empty($setting?->api_key_encrypted),
                'system_instructions' => $setting?->system_instructions,
                'is_enabled' => $setting?->is_enabled ?? true,
                'temperature' => $setting?->temperature ?? 0.70,
            ],
            'can_manage_settings' => $canManageSettings,
            'available_providers' => [
                [
                    'code' => 'gemini',
                    'name' => 'Google Gemini (1.5 Flash / 2.0 Flash)',
                    'description' => 'Model multimodal vision dan teks berkecepatan tinggi.',
                ],
                [
                    'code' => 'openai',
                    'name' => 'OpenAI (GPT-4o / GPT-4o-mini)',
                    'description' => 'Model reasoning umum dan visi kuitansi canggih.',
                ],
                [
                    'code' => 'hermes',
                    'name' => 'Hermes Agent (Nous Hermes 3)',
                    'description' => 'Model reasoning berkinerja tinggi via endpoint lokal/mandiri.',
                ],
                [
                    'code' => 'openclaw',
                    'name' => 'OpenClaw Agent',
                    'description' => 'Agen eksekusi tugas dan alur kerja terotomasi.',
                ],
                [
                    'code' => 'mock',
                    'name' => 'Local Mock Engine',
                    'description' => 'Mode simulasi offline aman tanpa panggilan internet.',
                ],
            ],
        ]);
    }

    /**
     * Memulai percakapan baru.
     */
    public function store(Request $request, string $tenantSlug): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:100',
        ]);

        $conversation = $this->assistantService->startConversation($validated['title'] ?? 'Percakapan Baru');

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ], 201);
    }

    /**
     * Mendapatkan rincian percakapan beserta seluruh pesannya.
     */
    public function show(Request $request, string $tenantSlug, string $id): JsonResponse
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        $conversation = AiConversation::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Percakapan tidak ditemukan pada ruang kerja ini.'], 404);
        }

        // Keamanan: larang user lain mengakses percakapan privat
        if ($conversation->user_id !== $userId && !auth()->user()?->is_super_admin) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /**
     * Mengirim pesan baru ke asisten.
     */
    public function sendMessage(Request $request, string $tenantSlug, string $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $tenantId = TenantContext::id();
        $userId = auth()->id();

        $conversation = AiConversation::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Percakapan tidak ditemukan.'], 404);
        }

        if ($conversation->user_id !== $userId && !auth()->user()?->is_super_admin) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $assistantMessage = $this->assistantService->sendMessage($conversation, $validated['message']);

        return response()->json([
            'success' => true,
            'reply' => $assistantMessage,
        ]);
    }

    /**
     * Menghapus percakapan.
     */
    public function destroy(Request $request, string $tenantSlug, string $id): JsonResponse
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        $conversation = AiConversation::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Percakapan tidak ditemukan.'], 404);
        }

        if ($conversation->user_id !== $userId && !auth()->user()?->is_super_admin) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $conversation->delete();
        AuditLogger::log('ai_conversation_deleted', 'ai_conversations', $id);

        return response()->json(['success' => true]);
    }

    /**
     * Memperbarui pengaturan AI Ruang Kerja (Khusus Admin/Owner).
     */
    public function updateSettings(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            abort(403);
        }

        $membership = TenantMembership::where('tenant_id', $tenant->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!auth()->user()?->is_super_admin && !in_array($membership?->role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Hanya administrator yang diizinkan memperbarui pengaturan AI.'], 403);
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:gemini,openai,hermes,openclaw,mock',
            'model' => 'required|string|max:100',
            'api_endpoint' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'system_instructions' => 'nullable|string|max:2000',
            'is_enabled' => 'required|boolean',
            'temperature' => 'nullable|numeric|min:0|max:1',
        ]);

        $setting = TenantAiSetting::firstOrNew(['tenant_id' => $tenant->id]);
        $setting->provider = $validated['provider'];
        $setting->model = $validated['model'];
        $setting->api_endpoint = $validated['api_endpoint'];
        $setting->system_instructions = $validated['system_instructions'];
        $setting->is_enabled = $validated['is_enabled'];
        if (isset($validated['temperature'])) {
            $setting->temperature = $validated['temperature'];
        }

        if (!empty($validated['api_key'])) {
            $setting->api_key = $validated['api_key'];
        }

        $setting->save();

        AuditLogger::log('tenant_ai_settings_updated', 'tenant_ai_settings', $setting->id, null, [
            'provider' => $setting->provider,
            'model' => $setting->model,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan AI ruang kerja berhasil disimpan.',
        ]);
    }
}
