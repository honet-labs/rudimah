<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdModal from '../../design-system/RdModal.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import RdInfoTooltip from '../../design-system/RdInfoTooltip.vue';
import { secureFetch } from '../../utils/csrf';
import type { Tenant, AppManifest, User } from '../../types/core';

interface Conversation {
  id: string;
  title: string;
  provider: string;
  model: string;
  created_at: string;
  updated_at: string;
}

interface Message {
  id: string;
  conversation_id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  metadata?: Record<string, any>;
  created_at: string;
}

interface PlatformConfig {
  provider: string;
  provider_name: string;
  model: string;
  api_endpoint: string;
  has_api_key: boolean;
  is_configured: boolean;
}

interface AiSettings {
  is_custom?: boolean;
  provider: string;
  model: string;
  api_endpoint: string | null;
  has_custom_key: boolean;
  system_instructions: string | null;
  is_enabled: boolean;
  temperature: number;
}

interface ProviderOption {
  code: string;
  name: string;
  description: string;
}

const props = defineProps<{
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  user?: User;
  conversations: Conversation[];
  settings: AiSettings;
  platform_config?: PlatformConfig;
  can_manage_settings: boolean;
  available_providers: ProviderOption[];
}>();

const conversationsList = ref<Conversation[]>([...props.conversations]);
const activeConversationId = ref<string | null>(props.conversations[0]?.id || null);
const messages = ref<Message[]>([]);
const inputMessage = ref('');
const isSending = ref(false);
const isLoadingConversation = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const isHistoryDrawerOpen = ref(false);

// Modal Pengaturan AI
const isSettingsModalOpen = ref(false);
const useCustom = ref(props.settings.is_custom || false);
const formSettings = ref({
  is_custom: props.settings.is_custom || false,
  provider: props.settings.provider || props.platform_config?.provider || '9router',
  model: props.settings.model || props.platform_config?.model || 'high-models-agent',
  api_endpoint: props.settings.api_endpoint || props.platform_config?.api_endpoint || '',
  api_key: '',
  system_instructions: props.settings.system_instructions || '',
  is_enabled: props.settings.is_enabled,
  temperature: props.settings.temperature,
});
const isSavingSettings = ref(false);
const settingsAlert = ref<{ type: 'success' | 'error'; message: string } | null>(null);

const quickPrompts = [
  'Ringkas total pemasukan dan pengeluaran bulan ini',
  'Bagaimana status keterpakaian anggaran bulan ini?',
  'Apakah ada pengingat tagihan yang mendekati jatuh tempo?',
  'Tampilkan rincian transaksi pengeluaran terbaru',
];

function scrollToBottom() {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
  });
}

async function loadConversation(id: string) {
  activeConversationId.value = id;
  isLoadingConversation.value = true;
  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/conversations/${id}`, {
      headers: {
        Accept: 'application/json',
      },
    });
    if (res.ok) {
      const data = await res.json();
      messages.value = data.messages || [];
      scrollToBottom();
    }
  } catch (err) {
    console.error('Gagal memuat percakapan', err);
  } finally {
    isLoadingConversation.value = false;
  }
}

async function startNewConversation() {
  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/conversations`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ title: 'Percakapan Baru' }),
    });

    if (res.ok) {
      const data = await res.json();
      conversationsList.value.unshift(data.conversation);
      activeConversationId.value = data.conversation.id;
      messages.value = [];
    }
  } catch (err) {
    console.error('Gagal membuat percakapan baru', err);
  }
}

async function deleteConversation(id: string) {
  if (!confirm('Hapus percakapan ini secara permanen?')) return;

  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/conversations/${id}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
      },
    });

    if (res.ok) {
      conversationsList.value = conversationsList.value.filter((c) => c.id !== id);
      if (activeConversationId.value === id) {
        activeConversationId.value = conversationsList.value[0]?.id || null;
        if (activeConversationId.value) {
          loadConversation(activeConversationId.value);
        } else {
          messages.value = [];
        }
      }
    }
  } catch (err) {
    console.error('Gagal menghapus percakapan', err);
  }
}

async function handleSendMessage(promptText?: string) {
  const text = (promptText || inputMessage.value).trim();
  if (!text || isSending.value) return;

  if (!activeConversationId.value) {
    await startNewConversation();
  }

  if (!activeConversationId.value) return;

  const currentConvId = activeConversationId.value;

  // Optimistic User Message
  const tempUserMsg: Message = {
    id: 'temp-' + Date.now(),
    conversation_id: currentConvId,
    role: 'user',
    content: text,
    created_at: new Date().toISOString(),
  };
  messages.value.push(tempUserMsg);
  inputMessage.value = '';
  isSending.value = true;
  scrollToBottom();

  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/conversations/${currentConvId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ message: text }),
    });

    if (res.ok) {
      const data = await res.json();
      messages.value.push(data.reply);
      // Perbarui judul pada daftar
      const conv = conversationsList.value.find((c) => c.id === currentConvId);
      if (conv && conv.title === 'Percakapan Baru') {
        conv.title = text.slice(0, 35) + (text.length > 35 ? '...' : '');
      }
    } else {
      const err = await res.json().catch(() => ({}));
      const errorMsg =
        err?.message ||
        err?.error ||
        err?.detail ||
        (err?.errors ? Object.values(err.errors).flat().join(', ') : '') ||
        'Terjadi kesalahan saat memproses jawaban asisten.';
      messages.value.push({
        id: 'err-' + Date.now(),
        conversation_id: currentConvId,
        role: 'assistant',
        content: errorMsg,
        created_at: new Date().toISOString(),
      });
    }
  } catch (err) {
    messages.value.push({
      id: 'err-' + Date.now(),
      conversation_id: currentConvId,
      role: 'assistant',
      content: 'Gagal terhubung ke layanan AI ruang kerja. Pastikan koneksi server tersedia.',
      created_at: new Date().toISOString(),
    });
  } finally {
    isSending.value = false;
    scrollToBottom();
  }
}

async function saveSettings() {
  isSavingSettings.value = true;
  settingsAlert.value = null;

  try {
    const payload = {
      is_custom: useCustom.value,
      provider: useCustom.value ? formSettings.value.provider : (props.platform_config?.provider || formSettings.value.provider),
      model: useCustom.value ? formSettings.value.model : (props.platform_config?.model || formSettings.value.model),
      api_endpoint: useCustom.value ? formSettings.value.api_endpoint : null,
      api_key: useCustom.value ? formSettings.value.api_key : null,
      system_instructions: formSettings.value.system_instructions,
      is_enabled: formSettings.value.is_enabled,
      temperature: formSettings.value.temperature,
    };

    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/settings`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok) {
      settingsAlert.value = { type: 'success', message: data.message || 'Pengaturan asisten AI berhasil disimpan.' };
      setTimeout(() => {
        isSettingsModalOpen.value = false;
        settingsAlert.value = null;
      }, 1200);
    } else {
      settingsAlert.value = { type: 'error', message: data.message || 'Gagal menyimpan pengaturan.' };
    }
  } catch (err) {
    settingsAlert.value = { type: 'error', message: 'Kesalahan jaringan saat menyimpan konfigurasi.' };
  } finally {
    isSavingSettings.value = false;
  }
}

async function resetToPlatformDefault() {
  if (!confirm('Kembalikan konfigurasi AI agar otomatis 100% mewarisi seluruh pengaturan dari Administrasi Sistem?')) return;

  isSavingSettings.value = true;
  settingsAlert.value = null;

  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/assistant/settings`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        reset_to_platform: true,
        is_enabled: true,
        system_instructions: formSettings.value.system_instructions,
      }),
    });

    const data = await res.json();
    if (res.ok) {
      useCustom.value = false;
      formSettings.value.is_custom = false;
      if (props.platform_config) {
        formSettings.value.provider = props.platform_config.provider;
        formSettings.value.model = props.platform_config.model;
        formSettings.value.api_endpoint = props.platform_config.api_endpoint;
      }
      formSettings.value.api_key = '';
      settingsAlert.value = { type: 'success', message: 'Berhasil dikembalikan ke konfigurasi otomatis Administrasi Sistem.' };
      setTimeout(() => {
        isSettingsModalOpen.value = false;
        settingsAlert.value = null;
      }, 1200);
    } else {
      settingsAlert.value = { type: 'error', message: data.message || 'Gagal mereset konfigurasi.' };
    }
  } catch (err) {
    settingsAlert.value = { type: 'error', message: 'Kesalahan jaringan saat mereset konfigurasi.' };
  } finally {
    isSavingSettings.value = false;
  }
}

onMounted(() => {
  if (activeConversationId.value) {
    loadConversation(activeConversationId.value);
  }
});
</script>

<template>
  <RudimahAppShell
    app-key="pekan"
    app-name="PEKAN"
    :tenant="tenant"
    :tenants="tenants"
    :apps="apps"
    :user="user"
    :no-padding="true"
  >
    <div class="flex-1 min-h-0 flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-950">
      <!-- Top Subheader -->
      <div class="min-h-12 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 sm:px-4 py-1.5 flex items-center justify-between gap-2 shrink-0">
        <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0">
          <!-- Mobile toggle riwayat percakapan -->
          <button
            type="button"
            class="md:hidden p-1.5 rounded-md text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0"
            @click="isHistoryDrawerOpen = !isHistoryDrawerOpen"
            title="Buka Riwayat Percakapan"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
            </svg>
          </button>

          <h1 class="text-xs sm:text-sm md:text-base font-bold text-slate-900 dark:text-slate-100 truncate flex items-center gap-1.5">
            <span class="sm:hidden">Asisten AI</span>
            <span class="hidden sm:inline">Asisten Cerdas Ruang Kerja</span>
            <RdInfoTooltip
              text="Konsultasi finansial interaktif, ringkasan arus kas, dan analisis keuangan cerdas terisolasi khusus ruang kerja ini"
              size="sm"
              title="Informasi Asisten AI"
            />
          </h1>

          <RdBadge variant="neutral" size="sm" class="hidden lg:inline-flex shrink-0">
            Konteks: {{ tenant.name }}
          </RdBadge>
          <RdBadge :variant="settings.is_custom ? 'warning' : 'neutral'" size="sm" class="hidden xl:inline-flex shrink-0">
            {{ settings.is_custom ? 'Konfigurasi: Mandiri Ruang Kerja' : 'Konfigurasi: Administrasi Sistem' }}
          </RdBadge>
        </div>

        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          <RdButton
            v-if="can_manage_settings"
            variant="outline"
            size="sm"
            @click="isSettingsModalOpen = true"
            class="text-xs"
            title="Pengaturan Integrasi AI"
          >
            <svg class="w-3.5 h-3.5 sm:mr-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="hidden sm:inline">Pengaturan AI</span>
          </RdButton>

          <RdButton
            variant="primary"
            size="sm"
            @click="startNewConversation"
            class="text-xs"
          >
            <span>+ Baru</span>
            <span class="hidden sm:inline">&nbsp;Percakapan</span>
          </RdButton>
        </div>
      </div>

      <!-- Main Chat Layout -->
      <div class="flex-1 min-h-0 flex overflow-hidden relative">
        <!-- Backdrop Mobile Riwayat -->
        <div
          v-if="isHistoryDrawerOpen"
          class="fixed inset-0 bg-slate-900/50 z-20 md:hidden"
          @click="isHistoryDrawerOpen = false"
        ></div>

        <!-- Sidebar Riwayat Percakapan -->
        <aside
          :class="[
            'w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex-col shrink-0 h-full transition-all duration-200',
            isHistoryDrawerOpen
              ? 'flex fixed inset-y-0 left-0 z-30 pt-14 md:pt-0 shadow-2xl md:shadow-none'
              : 'hidden md:flex'
          ]"
        >
          <div class="p-3 border-b border-slate-100 dark:border-slate-800 text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center justify-between shrink-0">
            <span>Riwayat Percakapan</span>
            <button
              v-if="isHistoryDrawerOpen"
              type="button"
              class="md:hidden text-slate-400 hover:text-slate-600 p-1"
              @click="isHistoryDrawerOpen = false"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="flex-1 min-h-0 overflow-y-auto p-2 space-y-1">
            <div
              v-if="conversationsList.length === 0"
              class="p-4 text-center text-xs text-slate-400"
            >
              Belum ada percakapan aktif. Klik tombol "+ Baru" untuk memulai.
            </div>

            <div
              v-for="conv in conversationsList"
              :key="conv.id"
              :class="[
                'group flex items-center justify-between px-3 py-2 rounded-md text-xs cursor-pointer transition-colors',
                activeConversationId === conv.id
                  ? 'bg-slate-100 dark:bg-slate-800 font-semibold text-slate-900 dark:text-slate-100'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50'
              ]"
              @click="loadConversation(conv.id); isHistoryDrawerOpen = false"
            >
              <span class="truncate flex-1 pr-2">{{ conv.title }}</span>
              <button
                type="button"
                class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-600 p-0.5 text-[11px]"
                title="Hapus percakapan"
                @click.stop="deleteConversation(conv.id)"
              >
                x
              </button>
            </div>
          </div>

          <div class="p-2.5 px-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 shrink-0">
            Terisolasi ketat pada ID: {{ tenant.id }}
          </div>
        </aside>

        <!-- Area Pesan & Dialog -->
        <section aria-label="Percakapan Asisten AI" class="flex-1 min-w-0 flex flex-col bg-slate-50 dark:bg-slate-950 h-full overflow-hidden">
          <!-- Banner Keamanan Tenant (Compact & Responsive) -->
          <div class="py-2 px-3 sm:px-4 bg-slate-100/70 dark:bg-slate-900/70 border-b border-slate-200 dark:border-slate-800 text-[11px] sm:text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between shrink-0">
            <div class="leading-normal">
              <span class="font-semibold text-slate-800 dark:text-slate-200">Proteksi Keamanan Aktif:</span>
              Asisten dibatasi ketat pada konteks ruang kerja ini.
            </div>
            <span class="text-[10px] text-slate-400 shrink-0 hidden md:inline ml-2">Fail-Closed Context</span>
          </div>

          <!-- Message List -->
          <div
            ref="chatContainer"
            class="flex-1 min-h-0 overflow-y-auto p-4 md:p-6 space-y-4"
          >
            <!-- Tampilan Saat Percakapan Kosong -->
            <div
              v-if="messages.length === 0 && !isLoadingConversation"
              class="max-w-2xl mx-auto py-4 md:py-6 text-center space-y-3"
            >
              <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                Selamat Datang di Asisten Finansial Ruang Kerja {{ tenant.name }}
              </div>
              <p class="text-xs text-slate-500 max-w-md mx-auto">
                Ajukan pertanyaan seputar pembukuan, transaksi, alokasi anggaran, target tabungan, atau jadwal tagihan jatuh tempo.
              </p>

              <!-- Saran Prompt Cepat -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-left pt-2">
                <button
                  v-for="prompt in quickPrompts"
                  :key="prompt"
                  type="button"
                  class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-400 dark:hover:border-slate-600 text-xs text-slate-700 dark:text-slate-300 text-left transition-all cursor-pointer shadow-sm hover:shadow"
                  @click="handleSendMessage(prompt)"
                >
                  {{ prompt }}
                </button>
              </div>
            </div>

            <!-- Pesan Percakapan -->
            <div
              v-for="msg in messages"
              :key="msg.id"
              :class="[
                'flex flex-col max-w-2xl',
                msg.role === 'user' ? 'ml-auto items-end' : 'mr-auto items-start'
              ]"
            >
              <div class="text-[11px] font-medium text-slate-400 mb-1 px-1">
                {{ msg.role === 'user' ? 'Anda' : 'Asisten RUDIMAH' }}
              </div>
              <div
                :class="[
                  'p-3.5 rounded-xl text-xs leading-relaxed whitespace-pre-wrap break-words shadow-sm',
                  msg.role === 'user'
                    ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 rounded-br-none'
                    : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 rounded-bl-none'
                ]"
              >
                {{ msg.content }}
              </div>

              <!-- Kartu Status Transaksi Berhasil Dicatat / Diperbarui (Jika Ada) -->
              <div
                v-if="msg.metadata && msg.metadata.recorded_transactions && msg.metadata.recorded_transactions.length > 0"
                class="mt-2 p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2 w-full text-xs shadow-sm"
              >
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                  <div class="flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-200">
                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>
                      {{
                        msg.metadata.recorded_transactions.some((t: any) => t.action === 'updated')
                          ? `${msg.metadata.recorded_transactions.length} Transaksi Berhasil Diperbarui di Sistem`
                          : msg.metadata.recorded_transactions.some((t: any) => t.action === 'deleted')
                          ? `${msg.metadata.recorded_transactions.length} Transaksi Berhasil Dihapus dari Sistem`
                          : `${msg.metadata.recorded_transactions.length} Transaksi Berhasil Dicatat ke Sistem`
                      }}
                    </span>
                  </div>
                  <a
                    :href="`/t/${tenant.slug}/apps/pekan/transactions`"
                    class="text-teal-700 dark:text-teal-400 hover:underline font-medium flex items-center gap-1 text-[11px] shrink-0"
                  >
                    <span>Buka Menu Transaksi</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </a>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800/60">
                  <div
                    v-for="(tx, idx) in msg.metadata.recorded_transactions"
                    :key="tx.id || idx"
                    class="py-1.5 flex items-center justify-between text-[11px]"
                  >
                    <div class="flex items-center gap-2 truncate pr-2">
                      <span
                        v-if="tx.action === 'updated'"
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300"
                      >
                        Diperbarui
                      </span>
                      <span
                        v-else-if="tx.action === 'deleted'"
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300"
                      >
                        Dihapus
                      </span>
                      <span
                        v-else
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                      >
                        {{ tx.type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                      </span>
                      <span class="text-slate-800 dark:text-slate-200 font-medium truncate">{{ tx.description }}</span>
                      <span v-if="tx.merchant_name && !tx.description.toLowerCase().includes(tx.merchant_name.toLowerCase())" class="text-[10px] text-slate-500 font-normal shrink-0">({{ tx.merchant_name }})</span>
                      <span v-if="tx.date" class="text-[10px] text-slate-400 shrink-0 font-mono">({{ tx.date }})</span>
                    </div>
                    <div class="font-semibold text-slate-900 dark:text-slate-100 shrink-0">
                      Rp {{ Number(tx.amount_minor || 0).toLocaleString('id-ID') }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Typing indicator saat loading -->
            <div
              v-if="isSending"
              class="mr-auto items-start flex flex-col"
            >
              <div class="text-[11px] font-medium text-slate-400 mb-1 px-1">
                Asisten RUDIMAH sedang berpikir...
              </div>
              <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 italic">
                Memproses konteks data ruang kerja dan menghasilkan respon terisolasi...
              </div>
            </div>
          </div>

          <!-- Input Bar (Pinned at Bottom) -->
          <div class="p-3 md:p-3.5 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shrink-0">
            <form
              class="max-w-4xl mx-auto flex gap-2 items-center"
              @submit.prevent="handleSendMessage()"
            >
              <input
                v-model="inputMessage"
                type="text"
                placeholder="Tanyakan hal terkait keuangan ruang kerja Anda... (Tekan Enter untuk mengirim)"
                class="flex-1 px-3.5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-600"
                :disabled="isSending"
              />
              <RdButton
                type="submit"
                variant="primary"
                size="md"
                :loading="isSending"
                :disabled="!inputMessage.trim() || isSending"
              >
                Kirim
              </RdButton>
            </form>
          </div>
        </section>
      </div>

      <!-- Modal Pengaturan AI Ruang Kerja -->
      <RdModal
        v-model="isSettingsModalOpen"
        title="Pengaturan Integrasi Asisten AI"
        @close="isSettingsModalOpen = false"
      >
        <div class="space-y-4 text-xs">
          <RdAlert
            v-if="settingsAlert"
            :variant="settingsAlert.type === 'success' ? 'success' : 'error'"
          >
            {{ settingsAlert.message }}
          </RdAlert>

          <!-- Status Konfigurasi Otomatis dari Administrasi Sistem -->
          <div class="p-3.5 rounded-lg border border-teal-200 dark:border-teal-800/60 bg-teal-50/70 dark:bg-teal-950/30 text-xs space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-teal-950 dark:text-teal-200 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-teal-600 dark:text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Konfigurasi Administrasi Sistem
              </span>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-teal-100 dark:bg-teal-900 text-teal-800 dark:text-teal-200">
                Otomatis Aktif
              </span>
            </div>
            <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11px]">
              Ruang kerja Anda secara otomatis mewarisi kredensial AI terpusat dari Administrasi Sistem platform. Anda tidak perlu memasukkan kunci API atau URL endpoint lagi di sini.
            </p>
            <div class="grid grid-cols-2 gap-2 pt-1 text-[11px] border-t border-teal-200/60 dark:border-teal-900/50 text-slate-700 dark:text-slate-300">
              <div>
                <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Penyedia Default:</span>
                <span class="font-semibold font-mono">{{ platform_config?.provider_name || '9Router AI Gateway' }}</span>
              </div>
              <div>
                <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Model Platform:</span>
                <span class="font-semibold font-mono">{{ platform_config?.model || 'high-models-agent' }}</span>
              </div>
              <div class="col-span-2">
                <span class="text-slate-500 dark:text-slate-400 block text-[10px]">Kunci API & Endpoint:</span>
                <span class="font-medium text-teal-700 dark:text-teal-300 text-[11px]">Dikelola Terpusat di Administrasi Sistem</span>
              </div>
            </div>
          </div>

          <!-- Opsi Kustomisasi Khusus Ruang Kerja (BYOK) -->
          <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/40 space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <label for="use_custom_toggle" class="font-semibold text-slate-800 dark:text-slate-200 block text-xs cursor-pointer">
                  Kustomisasi Akun / Kunci API Sendiri (Opsional)
                </label>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">
                  Centang opsi ini hanya jika ruang kerja Anda ingin menggunakan akun/kunci API terpisah di luar Administrasi Sistem.
                </span>
              </div>
              <input
                id="use_custom_toggle"
                v-model="useCustom"
                type="checkbox"
                class="rounded border-slate-300 dark:border-slate-700 text-teal-600 focus:ring-teal-500 h-4 w-4 mt-0.5 shrink-0"
              />
            </div>

            <!-- Form Kustom (Hanya Ditampilkan jika Kustomisasi Diaktifkan) -->
            <div v-if="useCustom" class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Penyedia Layanan AI Kustom
                </label>
                <select
                  v-model="formSettings.provider"
                  class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-200"
                >
                  <option
                    v-for="prov in available_providers"
                    :key="prov.code"
                    :value="prov.code"
                  >
                    {{ prov.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Nama Model Kustom
                </label>
                <input
                  v-model="formSettings.model"
                  type="text"
                  placeholder="cth: gemini-1.5-flash, gpt-4o-mini, hermes-3-llama-3.1-8b"
                  class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Kustom Endpoint API
                </label>
                <input
                  v-model="formSettings.api_endpoint"
                  type="text"
                  placeholder="cth: https://api.9router.com/v1 atau http://localhost:11434/v1"
                  class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Kunci API Pribadi Ruang Kerja
                </label>
                <input
                  v-model="formSettings.api_key"
                  type="password"
                  placeholder="Masukkan kunci API khusus ruang kerja Anda..."
                  class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono"
                />
              </div>
            </div>
          </div>

          <!-- Persona Ruang Kerja (Selalu Tersedia) -->
          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
              Instruksi Sistem Khusus (Persona Ruang Kerja)
            </label>
            <textarea
              v-model="formSettings.system_instructions"
              rows="3"
              placeholder="Opsional: Tambahkan arahan khusus gaya bahasa atau fokus pembukuan keluarga/bisnis ruang kerja ini..."
              class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs"
            ></textarea>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input
              id="ai_enabled"
              v-model="formSettings.is_enabled"
              type="checkbox"
              class="rounded border-slate-300 dark:border-slate-700"
            />
            <label for="ai_enabled" class="text-xs text-slate-700 dark:text-slate-300">
              Aktifkan asisten AI untuk anggota ruang kerja ini
            </label>
          </div>

          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <div>
              <button
                v-if="settings.is_custom"
                type="button"
                class="text-[11px] text-red-600 hover:text-red-700 dark:text-red-400 hover:underline"
                @click="resetToPlatformDefault"
              >
                Kembalikan ke Default Sistem
              </button>
            </div>
            <div class="flex items-center gap-2">
              <RdButton
                variant="outline"
                size="sm"
                @click="isSettingsModalOpen = false"
              >
                Batal
              </RdButton>
              <RdButton
                variant="primary"
                size="sm"
                :loading="isSavingSettings"
                @click="saveSettings"
              >
                Simpan Pengaturan
              </RdButton>
            </div>
          </div>
        </div>
      </RdModal>
    </div>
  </RudimahAppShell>
</template>
