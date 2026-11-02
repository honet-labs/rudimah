<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdSelect from '../../design-system/RdSelect.vue';
import RdCheckbox from '../../design-system/RdCheckbox.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import RdModal from '../../design-system/RdModal.vue';
import { secureFetch } from '../../utils/csrf';
import type { User, Tenant } from '../../types/core';

interface AiProviderDetail {
  name: string;
  api_key?: string;
  has_api_key: boolean;
  api_endpoint: string;
  model: string;
  is_configured: boolean;
}

interface AiSettingsProp {
  active_provider: string;
  is_enabled: boolean;
  providers: {
    gemini: AiProviderDetail;
    openai: AiProviderDetail;
    claude: AiProviderDetail;
    '9router': AiProviderDetail;
  };
}

const props = defineProps<{
  user: User;
  tenant?: Tenant | null;
  status?: string;
  error?: string;
  aiSettings?: AiSettingsProp;
}>();

const savedAiTab = typeof window !== 'undefined' ? localStorage.getItem('rudimah_admin_ai_tab') : null;
const activeAiTab = ref<'gemini' | 'openai' | 'claude' | '9router'>(
  (savedAiTab as any) || (props.aiSettings?.active_provider as any) || 'gemini'
);

function switchAiTab(tab: 'gemini' | 'openai' | 'claude' | '9router') {
  activeAiTab.value = tab;
  if (typeof window !== 'undefined') {
    localStorage.setItem('rudimah_admin_ai_tab', tab);
  }
}

const isAiSaving = ref(false);
const isAiTesting = ref(false);
const showTestAiModal = ref(false);
const testAiProvider = ref<'gemini' | 'openai' | 'claude' | '9router'>(activeAiTab.value);

const aiProviderOptions = [
  { label: 'Google Gemini (Direkomendasikan)', value: 'gemini' },
  { label: 'OpenAI ChatGPT (GPT-4o / GPT-4o-mini)', value: 'openai' },
  { label: 'Anthropic Claude (Claude 3.5 Sonnet / Haiku)', value: 'claude' },
  { label: '9Router AI Gateway (Multi-Model Gateway)', value: '9router' },
];

const aiForm = ref({
  active_provider: props.aiSettings?.active_provider || 'gemini',
  is_enabled: props.aiSettings?.is_enabled ?? true,
  gemini: {
    api_endpoint: props.aiSettings?.providers?.gemini?.api_endpoint || 'https://generativelanguage.googleapis.com',
    api_key: '',
    model: props.aiSettings?.providers?.gemini?.model || 'gemini-1.5-flash',
  },
  openai: {
    api_endpoint: props.aiSettings?.providers?.openai?.api_endpoint || 'https://api.openai.com/v1',
    api_key: '',
    model: props.aiSettings?.providers?.openai?.model || 'gpt-4o-mini',
  },
  claude: {
    api_endpoint: props.aiSettings?.providers?.claude?.api_endpoint || 'https://api.anthropic.com/v1',
    api_key: '',
    model: props.aiSettings?.providers?.claude?.model || 'claude-3-5-sonnet-20241022',
  },
  '9router': {
    api_endpoint: props.aiSettings?.providers?.['9router']?.api_endpoint || 'https://api.9router.com/v1',
    api_key: '',
    model: props.aiSettings?.providers?.['9router']?.model || 'high-models-agent',
  },
});

function saveAiSettings() {
  isAiSaving.value = true;
  if (typeof window !== 'undefined') {
    localStorage.setItem('rudimah_admin_ai_tab', activeAiTab.value);
  }
  router.post('/admin/ai', aiForm.value, {
    preserveScroll: true,
    onFinish: () => {
      isAiSaving.value = false;
    },
  });
}

const aiTestResult = ref<{
  loading: boolean;
  success?: boolean;
  message?: string;
  latency_ms?: number;
} | null>(null);

const aiCardAlert = ref<{ type: 'success' | 'error'; message: string } | null>(null);

function openAiTestModal(provider: 'gemini' | 'openai' | 'claude' | '9router') {
  testAiProvider.value = provider;
  aiTestResult.value = null;
  showTestAiModal.value = true;
}

const currentTestingConfig = computed(() => {
  const provider = testAiProvider.value;
  return aiForm.value[provider];
});

async function runAiTest() {
  isAiTesting.value = true;
  const target = testAiProvider.value;
  const cfg = aiForm.value[target];

  aiTestResult.value = { loading: true };

  try {
    const res = await secureFetch('/admin/ai/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        provider: target,
        api_endpoint: cfg.api_endpoint,
        api_key: cfg.api_key || undefined,
        model: cfg.model,
      }),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      aiTestResult.value = {
        loading: false,
        success: true,
        message: data.message,
        latency_ms: data.latency_ms,
      };
      aiCardAlert.value = {
        type: 'success',
        message: data.message,
      };
    } else {
      aiTestResult.value = {
        loading: false,
        success: false,
        message: data.message || 'Gagal terhubung ke penyedia AI.',
      };
      aiCardAlert.value = {
        type: 'error',
        message: data.message || 'Gagal terhubung ke penyedia AI.',
      };
    }
  } catch (err: any) {
    const errMsg = 'Gangguan jaringan saat menguji koneksi: ' + (err.message || 'Koneksi gagal');
    aiTestResult.value = {
      loading: false,
      success: false,
      message: errMsg,
    };
    aiCardAlert.value = {
      type: 'error',
      message: errMsg,
    };
  } finally {
    isAiTesting.value = false;
  }
}

interface DetectedModelItem {
  id: string;
  name: string;
  description?: string;
}

const detectingModels = ref<Record<'gemini' | 'openai' | 'claude' | '9router', boolean>>({
  gemini: false,
  openai: false,
  claude: false,
  '9router': false,
});

const detectedModels = ref<Record<'gemini' | 'openai' | 'claude' | '9router', DetectedModelItem[]>>({
  gemini: [],
  openai: [],
  claude: [],
  '9router': [],
});

const modelDetectionMessages = ref<Record<'gemini' | 'openai' | 'claude' | '9router', { success: boolean; text: string } | null>>({
  gemini: null,
  openai: null,
  claude: null,
  '9router': null,
});

async function detectModels(provider: 'gemini' | 'openai' | 'claude' | '9router') {
  detectingModels.value[provider] = true;
  modelDetectionMessages.value[provider] = null;
  const cfg = aiForm.value[provider];

  try {
    const res = await secureFetch('/admin/ai/models', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        provider,
        api_endpoint: cfg.api_endpoint || undefined,
        api_key: cfg.api_key || undefined,
      }),
    });

    const data = await res.json().catch(() => ({}));

    if (res.ok && data.success && Array.isArray(data.models)) {
      detectedModels.value[provider] = data.models;
      modelDetectionMessages.value[provider] = {
        success: true,
        text: data.message || `Ditemukan ${data.models.length} model yang tersedia.`,
      };
      if (!cfg.model && data.models.length > 0) {
        cfg.model = data.models[0].id;
      }
    } else {
      modelDetectionMessages.value[provider] = {
        success: false,
        text: data.message || 'Gagal mendeteksi model. Pastikan API key dan URL endpoint valid.',
      };
    }
  } catch (err: any) {
    modelDetectionMessages.value[provider] = {
      success: false,
      text: 'Gangguan jaringan saat mendeteksi model: ' + (err.message || 'Koneksi gagal'),
    };
  } finally {
    detectingModels.value[provider] = false;
  }
}
</script>

<template>
  <RudimahAppShell
    :user="user"
    :tenant="tenant || undefined"
    app-key="admin"
    app-name="Administrasi Sistem"
  >
    <RdPageHeader
      title="Penyedia AI Platform Terpusat"
      description="Atur kecerdasan buatan terpusat untuk Asisten Keuangan, Ekstraksi Struk OCR, dan Analisis Cerdas di seluruh ruang kerja RUDIMAH (Google Gemini, OpenAI, Anthropic Claude, 9Router Gateway)"
      :breadcrumbs="[{ label: 'Administrasi', href: '/admin' }, { label: 'Penyedia AI Platform' }]"
    >
      <template #actions>
        <a href="/workspaces" class="w-full sm:w-auto">
          <RdButton variant="outline" size="sm" class="w-full sm:w-auto">
            Kembali ke Pemilih Ruang Kerja
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <div v-if="status" class="mb-4 sm:mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-4 sm:mb-6">
      <RdAlert variant="error" title="Kendala Sistem">
        {{ error }}
      </RdAlert>
    </div>

    <!-- Banner Hasil Pengujian AI Terkini -->
    <div v-if="aiCardAlert" class="mb-4">
      <RdAlert
        :variant="aiCardAlert.type"
        :title="aiCardAlert.type === 'success' ? 'Hasil Pengujian AI Berhasil' : 'Kendala Uji Koneksi AI'"
      >
        {{ aiCardAlert.message }}
      </RdAlert>
    </div>

    <RdCard
      title="Konfigurasi Model & Kunci API"
      description="Konfigurasi di bawah ini berlaku untuk seluruh ruang kerja dan seluruh fitur kecerdasan buatan RUDIMAH."
      class="mb-6"
    >
      <!-- Ringkasan Status Penyedia AI -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div
          v-for="(prov, provKey) in props.aiSettings?.providers"
          :key="provKey"
          :class="[
            'p-3.5 sm:p-4 rounded-xl border transition-all cursor-pointer',
            aiForm.active_provider === provKey
              ? 'border-teal-600 bg-teal-50/50 dark:bg-teal-950/20 shadow-sm'
              : 'border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 hover:border-slate-300'
          ]"
          @click="aiForm.active_provider = provKey as any; switchAiTab(provKey as any)"
        >
          <div class="flex items-center justify-between gap-2 mb-1.5">
            <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">
              {{ prov.name }}
            </span>
            <RdBadge v-if="aiForm.active_provider === provKey" variant="info" class="shrink-0 text-[10px]">
              Aktif
            </RdBadge>
            <button
              v-else
              type="button"
              class="text-[10px] text-teal-600 dark:text-teal-400 hover:underline shrink-0 font-medium"
              @click.stop="aiForm.active_provider = provKey as any; switchAiTab(provKey as any)"
            >
              Aktifkan
            </button>
          </div>
          <p class="text-[11px] text-slate-500 font-mono truncate mb-2">
            {{ prov.model || '-' }}
          </p>
          <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 dark:border-slate-800">
            <span class="text-[10px] text-slate-400">Status Kunci:</span>
            <RdBadge :variant="prov.is_configured ? 'success' : 'neutral'" class="text-[10px]">
              {{ prov.is_configured ? 'Tersedia' : 'Belum Ada' }}
            </RdBadge>
          </div>
        </div>
      </div>

      <form @submit.prevent="saveAiSettings">
        <!-- Opsi Global AI Platform -->
        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 mb-6 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <RdCheckbox
              v-model="aiForm.is_enabled"
              label="Aktifkan Layanan Kecerdasan Buatan (AI) Global"
              description="Izinkan fitur asisten chat cerdas dan ekstraksi bukti transaksi otomatis di seluruh ruang kerja"
            />
            <RdButton
              type="button"
              variant="outline"
              size="sm"
              class="w-full sm:w-auto shrink-0"
              :disabled="isAiTesting"
              @click="openAiTestModal(aiForm.active_provider as any)"
            >
              <span v-if="isAiTesting">Menguji Koneksi...</span>
              <span v-else>Uji Koneksi AI Aktif</span>
            </RdButton>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-200/60 dark:border-slate-800 items-start">
            <RdSelect
              v-model="aiForm.active_provider"
              label="Penyedia AI Aktif Platform"
              :options="aiProviderOptions"
              hint="Penyedia AI terpadu yang digunakan untuk seluruh fitur sistem: Chat Asisten dan Pindai Struk (OCR)"
            />

            <div class="space-y-1.5 p-3 rounded-lg border border-slate-200/60 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-800/40">
              <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Konfigurasi AI Terpadu</span>
                <RdBadge variant="success" class="text-[10px]">
                  Sinkron Otomatis
                </RdBadge>
              </div>
              <p class="text-[11px] text-slate-500 leading-relaxed">
                Fitur <strong>Chat Asisten</strong> dan <strong>Pindai Struk (OCR)</strong> otomatis menggunakan penyedia dan model yang sama:
                <span class="font-mono font-medium text-teal-700 dark:text-teal-400">
                  {{ aiForm[aiForm.active_provider]?.model || 'Mengikuti Model Penyedia' }}
                </span>.
              </p>
            </div>
          </div>
        </div>

        <!-- Navigasi Tab Pengaturan Tiap Penyedia AI -->
        <div class="border-b border-slate-200 dark:border-slate-800 mb-6 flex gap-1 sm:gap-2 overflow-x-auto pb-1 -mb-px">
          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeAiTab === 'gemini'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="switchAiTab('gemini')"
          >
            <span>Google Gemini</span>
            <span v-if="props.aiSettings?.providers?.gemini?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeAiTab === 'openai'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="switchAiTab('openai')"
          >
            <span>OpenAI ChatGPT</span>
            <span v-if="props.aiSettings?.providers?.openai?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeAiTab === 'claude'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="switchAiTab('claude')"
          >
            <span>Anthropic Claude</span>
            <span v-if="props.aiSettings?.providers?.claude?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeAiTab === '9router'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="switchAiTab('9router')"
          >
            <span>9Router AI Gateway</span>
            <span v-if="props.aiSettings?.providers?.['9router']?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>
        </div>

        <!-- TAB 1: GOOGLE GEMINI -->
        <div v-show="activeAiTab === 'gemini'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan Google Gemini</h4>
                <RdBadge :variant="aiForm.active_provider === 'gemini' ? 'success' : 'neutral'" class="text-[10px]">
                  {{ aiForm.active_provider === 'gemini' ? 'Penyedia Utama Aktif' : 'Tidak Aktif' }}
                </RdBadge>
              </div>
              <p class="text-xs text-slate-500">Integrasi resmi model Gemini Flash & Gemini Pro dari Google AI Studio</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <RdButton
                v-if="aiForm.active_provider !== 'gemini'"
                type="button"
                variant="primary"
                size="sm"
                class="text-xs shrink-0"
                @click="aiForm.active_provider = 'gemini'"
              >
                Jadikan Penyedia Aktif
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="detectingModels['gemini'] || (!aiForm.gemini.api_key && !props.aiSettings?.providers?.gemini?.has_api_key)"
                @click="detectModels('gemini')"
              >
                <span v-if="detectingModels['gemini']">Mendeteksi Model...</span>
                <span v-else>Deteksi Model Otomatis</span>
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="isAiTesting || (!aiForm.gemini.api_key && !props.aiSettings?.providers?.gemini?.has_api_key)"
                @click="openAiTestModal('gemini')"
              >
                Uji Koneksi Gemini
              </RdButton>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
            <div class="md:col-span-2">
              <RdInput
                v-model="aiForm.gemini.api_endpoint"
                label="URL Endpoint API Google Gemini"
                placeholder="https://generativelanguage.googleapis.com"
                hint="Endpoint REST API resmi Google Gemini atau reverse proxy"
                required
              />
            </div>

            <RdInput
              v-model="aiForm.gemini.api_key"
              label="API Key Google Gemini"
              type="password"
              :placeholder="props.aiSettings?.providers?.gemini?.has_api_key ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Contoh: AIzaSy...'"
              :hint="props.aiSettings?.providers?.gemini?.has_api_key ? 'Kunci tersimpan aman dan terenkripsi di basis data platform.' : 'Dapatkan token otentikasi dari Google AI Studio (aistudio.google.com)'"
            />

            <div class="space-y-2">
              <div v-if="detectedModels['gemini'].length > 0">
                <RdSelect
                  v-model="aiForm.gemini.model"
                  label="Pilih dari Model Terdeteksi"
                  :options="[
                    { label: '-- Pilih dari ' + detectedModels['gemini'].length + ' Model Gemini Tersedia --', value: '' },
                    ...detectedModels['gemini'].map(m => ({ label: m.name, value: m.id }))
                  ]"
                  hint="Pilih untuk mengisi kolom model default secara otomatis"
                />
              </div>

              <RdInput
                v-model="aiForm.gemini.model"
                label="Model Default Gemini"
                placeholder="gemini-1.5-flash"
                hint="Nama model Gemini (contoh: gemini-1.5-flash, gemini-1.5-pro)"
                required
              />

              <p
                v-if="modelDetectionMessages['gemini']"
                :class="[
                  'text-xs mt-1',
                  modelDetectionMessages['gemini'].success
                    ? 'text-teal-600 dark:text-teal-400 font-medium'
                    : 'text-red-600 dark:text-red-400'
                ]"
              >
                {{ modelDetectionMessages['gemini'].text }}
              </p>
            </div>
          </div>
        </div>

        <!-- TAB 2: OPENAI CHATGPT -->
        <div v-show="activeAiTab === 'openai'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan OpenAI ChatGPT</h4>
                <RdBadge :variant="aiForm.active_provider === 'openai' ? 'success' : 'neutral'" class="text-[10px]">
                  {{ aiForm.active_provider === 'openai' ? 'Penyedia Utama Aktif' : 'Tidak Aktif' }}
                </RdBadge>
              </div>
              <p class="text-xs text-slate-500">Integrasi model OpenAI GPT-4o, GPT-4o-mini, atau gateway kompatibel</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <RdButton
                v-if="aiForm.active_provider !== 'openai'"
                type="button"
                variant="primary"
                size="sm"
                class="text-xs shrink-0"
                @click="aiForm.active_provider = 'openai'"
              >
                Jadikan Penyedia Aktif
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="detectingModels['openai'] || (!aiForm.openai.api_key && !props.aiSettings?.providers?.openai?.has_api_key)"
                @click="detectModels('openai')"
              >
                <span v-if="detectingModels['openai']">Mendeteksi Model...</span>
                <span v-else>Deteksi Model Otomatis</span>
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="isAiTesting || (!aiForm.openai.api_key && !props.aiSettings?.providers?.openai?.has_api_key)"
                @click="openAiTestModal('openai')"
              >
                Uji Koneksi OpenAI
              </RdButton>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
            <div class="md:col-span-2">
              <RdInput
                v-model="aiForm.openai.api_endpoint"
                label="URL Endpoint API OpenAI"
                placeholder="https://api.openai.com/v1"
                hint="Endpoint REST API OpenAI v1 atau proxy gateway yang kompatibel"
                required
              />
            </div>

            <RdInput
              v-model="aiForm.openai.api_key"
              label="API Key / Token OpenAI"
              type="password"
              :placeholder="props.aiSettings?.providers?.openai?.has_api_key ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Contoh: sk-proj-...'"
              :hint="props.aiSettings?.providers?.openai?.has_api_key ? 'Kunci tersimpan aman dan terenkripsi di basis data platform.' : 'Dapatkan API key dari platform.openai.com/api-keys'"
            />

            <div class="space-y-2">
              <div v-if="detectedModels['openai'].length > 0">
                <RdSelect
                  v-model="aiForm.openai.model"
                  label="Pilih dari Model Terdeteksi"
                  :options="[
                    { label: '-- Pilih dari ' + detectedModels['openai'].length + ' Model OpenAI Tersedia --', value: '' },
                    ...detectedModels['openai'].map(m => ({ label: m.name, value: m.id }))
                  ]"
                  hint="Pilih untuk mengisi kolom model default secara otomatis"
                />
              </div>

              <RdInput
                v-model="aiForm.openai.model"
                label="Model Default OpenAI"
                placeholder="gpt-4o-mini"
                hint="Nama model OpenAI (contoh: gpt-4o-mini, gpt-4o, gpt-3.5-turbo)"
                required
              />

              <p
                v-if="modelDetectionMessages['openai']"
                :class="[
                  'text-xs mt-1',
                  modelDetectionMessages['openai'].success
                    ? 'text-teal-600 dark:text-teal-400 font-medium'
                    : 'text-red-600 dark:text-red-400'
                ]"
              >
                {{ modelDetectionMessages['openai'].text }}
              </p>
            </div>
          </div>
        </div>

        <!-- TAB 3: ANTHROPIC CLAUDE -->
        <div v-show="activeAiTab === 'claude'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan Anthropic Claude</h4>
                <RdBadge :variant="aiForm.active_provider === 'claude' ? 'success' : 'neutral'" class="text-[10px]">
                  {{ aiForm.active_provider === 'claude' ? 'Penyedia Utama Aktif' : 'Tidak Aktif' }}
                </RdBadge>
              </div>
              <p class="text-xs text-slate-500">Integrasi model Claude 3.5 Sonnet, Claude 3 Opus, dan Claude 3.5 Haiku</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <RdButton
                v-if="aiForm.active_provider !== 'claude'"
                type="button"
                variant="primary"
                size="sm"
                class="text-xs shrink-0"
                @click="aiForm.active_provider = 'claude'"
              >
                Jadikan Penyedia Aktif
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="detectingModels['claude'] || (!aiForm.claude.api_key && !props.aiSettings?.providers?.claude?.has_api_key)"
                @click="detectModels('claude')"
              >
                <span v-if="detectingModels['claude']">Mendeteksi Model...</span>
                <span v-else>Deteksi Model Otomatis</span>
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="isAiTesting || (!aiForm.claude.api_key && !props.aiSettings?.providers?.claude?.has_api_key)"
                @click="openAiTestModal('claude')"
              >
                Uji Koneksi Claude
              </RdButton>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
            <div class="md:col-span-2">
              <RdInput
                v-model="aiForm.claude.api_endpoint"
                label="URL Endpoint API Claude"
                placeholder="https://api.anthropic.com/v1"
                hint="Endpoint REST API Anthropic Claude v1"
                required
              />
            </div>

            <RdInput
              v-model="aiForm.claude.api_key"
              label="API Key / Token Anthropic"
              type="password"
              :placeholder="props.aiSettings?.providers?.claude?.has_api_key ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Contoh: sk-ant-api03-...'"
              :hint="props.aiSettings?.providers?.claude?.has_api_key ? 'Kunci tersimpan aman dan terenkripsi di basis data platform.' : 'Dapatkan token otentikasi dari console.anthropic.com'"
            />

            <div class="space-y-2">
              <div v-if="detectedModels['claude'].length > 0">
                <RdSelect
                  v-model="aiForm.claude.model"
                  label="Pilih dari Model Terdeteksi"
                  :options="[
                    { label: '-- Pilih dari ' + detectedModels['claude'].length + ' Model Claude Tersedia --', value: '' },
                    ...detectedModels['claude'].map(m => ({ label: m.name, value: m.id }))
                  ]"
                  hint="Pilih untuk mengisi kolom model default secara otomatis"
                />
              </div>

              <RdInput
                v-model="aiForm.claude.model"
                label="Model Default Claude"
                placeholder="claude-3-5-sonnet-20241022"
                hint="Nama model Claude (contoh: claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022)"
                required
              />

              <p
                v-if="modelDetectionMessages['claude']"
                :class="[
                  'text-xs mt-1',
                  modelDetectionMessages['claude'].success
                    ? 'text-teal-600 dark:text-teal-400 font-medium'
                    : 'text-red-600 dark:text-red-400'
                ]"
              >
                {{ modelDetectionMessages['claude'].text }}
              </p>
            </div>
          </div>
        </div>

        <!-- TAB 4: 9ROUTER AI GATEWAY -->
        <div v-show="activeAiTab === '9router'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan 9Router AI Gateway</h4>
                <RdBadge :variant="aiForm.active_provider === '9router' ? 'success' : 'neutral'" class="text-[10px]">
                  {{ aiForm.active_provider === '9router' ? 'Penyedia Utama Aktif' : 'Tidak Aktif' }}
                </RdBadge>
              </div>
              <p class="text-xs text-slate-500">Integrasi gateway terpadu multi-penyedia AI dengan protokol kompatibel OpenAI</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <RdButton
                v-if="aiForm.active_provider !== '9router'"
                type="button"
                variant="primary"
                size="sm"
                class="text-xs shrink-0"
                @click="aiForm.active_provider = '9router'"
              >
                Jadikan Penyedia Aktif
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="detectingModels['9router'] || (!aiForm['9router'].api_key && !props.aiSettings?.providers?.['9router']?.has_api_key)"
                @click="detectModels('9router')"
              >
                <span v-if="detectingModels['9router']">Mendeteksi Model...</span>
                <span v-else>Deteksi Model Otomatis</span>
              </RdButton>
              <RdButton
                type="button"
                variant="outline"
                size="sm"
                class="text-xs shrink-0"
                :disabled="isAiTesting || (!aiForm['9router'].api_key && !props.aiSettings?.providers?.['9router']?.has_api_key)"
                @click="openAiTestModal('9router')"
              >
                Uji Koneksi 9Router
              </RdButton>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
            <div class="md:col-span-2">
              <RdInput
                v-model="aiForm['9router'].api_endpoint"
                label="URL Endpoint API 9Router Gateway"
                placeholder="https://api.9router.com/v1"
                hint="Endpoint REST API server 9Router Gateway (misal: https://api.9router.com/v1 atau server mandiri)"
                required
              />
            </div>

            <RdInput
              v-model="aiForm['9router'].api_key"
              label="Token Akses / API Key 9Router"
              type="password"
              :placeholder="props.aiSettings?.providers?.['9router']?.has_api_key ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Masukkan token akses 9Router'"
              :hint="props.aiSettings?.providers?.['9router']?.has_api_key ? 'Token tersimpan aman dan terenkripsi di basis data platform.' : 'Dapatkan token otentikasi dari panel 9Router'"
            />

            <div class="space-y-2">
              <div v-if="detectedModels['9router'].length > 0">
                <RdSelect
                  v-model="aiForm['9router'].model"
                  label="Pilih dari Model Terdeteksi"
                  :options="[
                    { label: '-- Pilih dari ' + detectedModels['9router'].length + ' Model 9Router Tersedia --', value: '' },
                    ...detectedModels['9router'].map(m => ({ label: m.name, value: m.id }))
                  ]"
                  hint="Pilih untuk mengisi kolom model default secara otomatis"
                />
              </div>

              <RdInput
                v-model="aiForm['9router'].model"
                label="Model Default 9Router"
                placeholder="gpt-4o-mini"
                hint="Nama model tujuan di 9Router. Untuk pemindaian struk (OCR), pastikan memilih model yang mendukung vision (contoh: gpt-4o-mini, gpt-4o, gemini-1.5-flash, claude-3-5-sonnet)"
                required
              />

              <p
                v-if="modelDetectionMessages['9router']"
                :class="[
                  'text-xs mt-1',
                  modelDetectionMessages['9router'].success
                    ? 'text-teal-600 dark:text-teal-400 font-medium'
                    : 'text-red-600 dark:text-red-400'
                ]"
              >
                {{ modelDetectionMessages['9router'].text }}
              </p>
            </div>
          </div>
        </div>

        <!-- Aksi Simpan AI Platform -->
        <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
          <p class="text-xs text-slate-500 text-center sm:text-left">
            Konfigurasi ini berlaku instan ke seluruh modul PEKAN dan asisten AI tanpa perlu restart kontainer.
          </p>
          <RdButton
            type="submit"
            variant="primary"
            :disabled="isAiSaving"
            class="w-full sm:w-auto shrink-0"
          >
            <span v-if="isAiSaving">Menyimpan Konfigurasi AI...</span>
            <span v-else>Simpan Konfigurasi AI Platform</span>
          </RdButton>
        </div>
      </form>
    </RdCard>

    <!-- Modal Uji Konektivitas AI -->
    <RdModal
      v-model="showTestAiModal"
      title="Uji Konektivitas Penyedia AI"
      description="Lakukan permintaan ping verifikasi ke endpoint dan token otentikasi penyedia AI terpilih"
      max-width="md"
    >
      <div class="space-y-3 py-2">
        <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-600 dark:text-slate-300">
          <div class="font-semibold text-slate-900 dark:text-white mb-1">
            Penyedia yang Diuji: {{ testAiProvider.toUpperCase() }}
          </div>
          <div>Endpoint: <span class="font-mono text-[11px]">{{ currentTestingConfig?.api_endpoint }}</span></div>
          <div>Model: <span class="font-mono text-[11px]">{{ currentTestingConfig?.model }}</span></div>
        </div>

        <!-- Indikator Loading Saat Pengujian Berlangsung -->
        <div v-if="aiTestResult?.loading" class="p-4 rounded-lg bg-teal-50 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800 text-xs text-teal-800 dark:text-teal-300 flex items-center gap-2">
          <svg class="animate-spin h-4 w-4 text-teal-700 dark:text-teal-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Menghubungi endpoint dan melakukan uji respon model...</span>
        </div>

        <!-- Hasil Pengujian Sukses -->
        <div v-else-if="aiTestResult?.success === true" class="space-y-2">
          <RdAlert variant="success" title="Koneksi Berhasil Diverifikasi">
            {{ aiTestResult.message }}
          </RdAlert>
        </div>

        <!-- Hasil Pengujian Gagal -->
        <div v-else-if="aiTestResult?.success === false" class="space-y-2">
          <RdAlert variant="error" title="Koneksi Gagal / Kendala Otentikasi">
            {{ aiTestResult.message }}
          </RdAlert>
        </div>

        <p v-if="!aiTestResult || aiTestResult.loading" class="text-xs text-slate-500">
          Sistem akan mengirimkan permintaan verifikasi ke endpoint AI di atas untuk memastikan otentikasi kunci API dan keterjangkauan layanan berfungsi dengan baik.
        </p>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isAiTesting"
            @click="showTestAiModal = false"
          >
            {{ aiTestResult?.success !== undefined ? 'Tutup' : 'Batal' }}
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isAiTesting"
            @click="runAiTest"
          >
            <span v-if="isAiTesting">Menguji Koneksi...</span>
            <span v-else-if="aiTestResult?.success !== undefined">Uji Ulang Koneksi</span>
            <span v-else>Jalankan Uji Koneksi</span>
          </RdButton>
        </div>
      </template>
    </RdModal>
  </RudimahAppShell>
</template>
