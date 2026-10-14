<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdModal from '../../design-system/RdModal.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import type { Tenant } from '../../types/core';

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

interface AiSettings {
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
  conversations: Conversation[];
  settings: AiSettings;
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

// Modal Pengaturan AI
const isSettingsModalOpen = ref(false);
const formSettings = ref({
  provider: props.settings.provider,
  model: props.settings.model,
  api_endpoint: props.settings.api_endpoint || '',
  api_key: '',
  system_instructions: props.settings.system_instructions || '',
  is_enabled: props.settings.is_enabled,
  temperature: props.settings.temperature,
});
const isSavingSettings = ref(false);
const settingsAlert = ref<{ type: 'success' | 'error'; message: string } | null>(null);

const quickPrompts = [
  'Ringkas saldo seluruh rekening saat ini',
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
    const res = await fetch(`/t/${props.tenant.slug}/assistant/conversations/${id}`, {
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
    const res = await fetch(`/t/${props.tenant.slug}/assistant/conversations`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
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
    const res = await fetch(`/t/${props.tenant.slug}/assistant/conversations/${id}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
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
    const res = await fetch(`/t/${props.tenant.slug}/assistant/conversations/${currentConvId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
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
      const err = await res.json();
      messages.value.push({
        id: 'err-' + Date.now(),
        conversation_id: currentConvId,
        role: 'assistant',
        content: err.message || 'Terjadi kesalahan saat memproses jawaban asisten.',
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
    const res = await fetch(`/t/${props.tenant.slug}/assistant/settings`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(formSettings.value),
    });

    const data = await res.json();
    if (res.ok) {
      settingsAlert.value = { type: 'success', message: 'Pengaturan asisten AI berhasil disimpan.' };
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

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
  return meta?.content || '';
}

onMounted(() => {
  if (activeConversationId.value) {
    loadConversation(activeConversationId.value);
  }
});
</script>

<template>
  <RudimahAppShell app-key="pekan" app-name="PEKAN" :tenant="tenant">
    <div class="flex-1 flex flex-col h-[calc(100vh-3.5rem)] overflow-hidden bg-slate-50 dark:bg-slate-950">
      <!-- Top Subheader -->
      <div class="h-14 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <h1 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <span>Asisten Cerdas Ruang Kerja</span>
          </h1>
          <RdBadge variant="neutral" size="sm">
            Konteks Aman: {{ tenant.name }}
          </RdBadge>
          <RdBadge variant="info" size="sm">
            Provider: {{ formSettings.provider.toUpperCase() }}
          </RdBadge>
        </div>

        <div class="flex items-center gap-2">
          <RdButton
            v-if="can_manage_settings"
            variant="outline"
            size="sm"
            @click="isSettingsModalOpen = true"
          >
            Pengaturan AI
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            @click="startNewConversation"
          >
            + Percakapan Baru
          </RdButton>
        </div>
      </div>

      <!-- Main Chat Layout -->
      <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar Riwayat Percakapan -->
        <aside class="w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col shrink-0">
          <div class="p-3 border-b border-slate-100 dark:border-slate-800 text-xs font-semibold text-slate-500 uppercase tracking-wider">
            Riwayat Percakapan
          </div>
          <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <div
              v-if="conversationsList.length === 0"
              class="p-4 text-center text-xs text-slate-400"
            >
              Belum ada percakapan aktif. Klik tombol "+ Percakapan Baru" untuk memulai.
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
              @click="loadConversation(conv.id)"
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

          <div class="p-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400">
            Terisolasi ketat pada ID: {{ tenant.id }}
          </div>
        </aside>

        <!-- Area Pesan & Dialog -->
        <main class="flex-1 flex flex-col bg-slate-50 dark:bg-slate-950 overflow-hidden">
          <!-- Banner Keamanan Tenant -->
          <div class="p-3 bg-slate-100/70 dark:bg-slate-900/70 border-b border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
            <div>
              <span class="font-semibold text-slate-800 dark:text-slate-200">Proteksi Keamanan Aktif:</span>
              Asisten ini dilarang keras dan tidak memiliki izin teknis untuk mengakses data dari ruang kerja/tenant lain.
            </div>
            <span class="text-[11px] text-slate-400">Fail-Closed Tenant Context</span>
          </div>

          <!-- Message List -->
          <div
            ref="chatContainer"
            class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4"
          >
            <!-- Tampilan Saat Percakapan Kosong -->
            <div
              v-if="messages.length === 0 && !isLoadingConversation"
              class="max-w-2xl mx-auto py-8 text-center space-y-4"
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
                  class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-400 dark:hover:border-slate-600 text-xs text-slate-700 dark:text-slate-300 text-left transition-all cursor-pointer"
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
                {{ msg.role === 'user' ? 'Anda' : 'Asisten RUDIMAH (' + formSettings.provider.toUpperCase() + ')' }}
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

          <!-- Input Bar -->
          <div class="p-3 md:p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shrink-0">
            <form
              class="max-w-4xl mx-auto flex gap-2 items-center"
              @submit.prevent="handleSendMessage()"
            >
              <input
                v-model="inputMessage"
                type="text"
                placeholder="Tanyakan hal terkait keuangan ruang kerja Anda... (Tekan Enter untuk mengirim)"
                class="flex-1 px-3.5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-500"
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
        </main>
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

          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
              Penyedia Layanan AI (Provider)
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
              Nama Model
            </label>
            <input
              v-model="formSettings.model"
              type="text"
              placeholder="cth: gemini-1.5-flash, gpt-4o-mini, hermes-3-llama-3.1-8b"
              class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs"
            />
          </div>

          <div v-if="['hermes', 'openclaw', 'openai'].includes(formSettings.provider)">
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
              Kustom Endpoint API (Opsional)
            </label>
            <input
              v-model="formSettings.api_endpoint"
              type="text"
              placeholder="cth: http://localhost:11434/v1 atau https://api.openai.com/v1"
              class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono"
            />
            <span class="text-[11px] text-slate-400 mt-0.5 block">
              Gunakan endpoint lokal jika menjalankan model Hermes atau OpenClaw secara mandiri.
            </span>
          </div>

          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
              Kunci API (Disimpan Terenkripsi)
            </label>
            <input
              v-model="formSettings.api_key"
              type="password"
              placeholder="Kosongkan bila menggunakan kunci global platform"
              class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono"
            />
          </div>

          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">
              Instruksi Sistem Khusus (Persona Ruang Kerja)
            </label>
            <textarea
              v-model="formSettings.system_instructions"
              rows="3"
              placeholder="Tambahkan arahan khusus gaya bahasa atau fokus pembukuan keluarga/bisnis Anda..."
              class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs"
            ></textarea>
          </div>

          <div class="flex items-center gap-2 pt-2">
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

          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
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
      </RdModal>
    </div>
  </RudimahAppShell>
</template>
