<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdSelect from '../../design-system/RdSelect.vue';
import RdCheckbox from '../../design-system/RdCheckbox.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import RdModal from '../../design-system/RdModal.vue';
import { secureFetch } from '../../utils/csrf';
import type { User, AppManifest, Tenant } from '../../types/core';

interface AdminUserItem {
  id: string;
  name: string;
  email: string;
  is_super_admin: boolean;
  has_mfa: boolean;
  workspaces_count: number;
  created_at: string;
}

interface AdminTenantItem {
  id: string;
  name: string;
  slug: string;
  status: string;
  members_count: number;
  created_at: string;
}

interface NotificationSettingsProp {
  mail: {
    enabled: boolean;
    host: string;
    port: number;
    username: string;
    password?: string;
    has_password: boolean;
    encryption: string;
    from_address: string;
    from_name: string;
    is_configured: boolean;
  };
  telegram: {
    enabled: boolean;
    bot_token?: string;
    has_bot_token: boolean;
    bot_username: string;
    default_group?: string;
    is_configured: boolean;
  };
  whatsapp: {
    enabled: boolean;
    waha_api_url: string;
    waha_api_key?: string;
    has_api_key: boolean;
    waha_session: string;
    default_group?: string;
    is_configured: boolean;
  };
}

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
  systemStats?: {
    totalTenants: number;
    totalUsers: number;
    dbEngine: string;
    redisConnected: boolean;
    appVersion: string;
  };
  aiSettings?: AiSettingsProp;
  notificationSettings?: NotificationSettingsProp;
  tenantsList?: AdminTenantItem[];
  usersList?: AdminUserItem[];
  appsList?: AppManifest[];
}>();

const tenantColumns = [
  { key: 'name', label: 'Nama Ruang Kerja' },
  { key: 'slug', label: 'Slug URL' },
  { key: 'members_count', label: 'Anggota', width: '90px', align: 'center' as const },
  { key: 'created_at', label: 'Terdaftar', width: '120px' },
  { key: 'status', label: 'Status', width: '100px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '140px', align: 'center' as const },
];

const userColumns = [
  { key: 'name', label: 'Nama Pengguna' },
  { key: 'email', label: 'Alamat Email' },
  { key: 'has_mfa', label: 'Keamanan MFA', width: '130px', align: 'center' as const },
  { key: 'is_super_admin', label: 'Hak Akses', width: '130px', align: 'center' as const },
  { key: 'workspaces_count', label: 'Ruang Kerja', width: '110px', align: 'center' as const },
];

const encryptionOptions = [
  { label: 'TLS (Direkomendasikan - Port 587)', value: 'tls' },
  { label: 'SSL (Port 465)', value: 'ssl' },
  { label: 'Tanpa Enkripsi (None / Port 25)', value: 'none' },
];

// Manajemen Form Notifikasi
const activeTab = ref<'mail' | 'whatsapp' | 'telegram'>('mail');
const isSaving = ref(false);
const isTesting = ref(false);
const testingChannel = ref<string | null>(null);

// Form Reactive State
const form = ref({
  mail: {
    enabled: props.notificationSettings?.mail?.enabled ?? false,
    host: props.notificationSettings?.mail?.host ?? '',
    port: props.notificationSettings?.mail?.port ?? 587,
    username: props.notificationSettings?.mail?.username ?? '',
    password: '',
    encryption: props.notificationSettings?.mail?.encryption ?? 'tls',
    from_address: props.notificationSettings?.mail?.from_address ?? 'noreply@rudimah.id',
    from_name: props.notificationSettings?.mail?.from_name ?? 'RUDIMAH Notifikasi',
  },
  telegram: {
    enabled: props.notificationSettings?.telegram?.enabled ?? false,
    bot_token: '',
    bot_username: props.notificationSettings?.telegram?.bot_username ?? '',
    default_group: props.notificationSettings?.telegram?.default_group ?? '',
  },
  whatsapp: {
    enabled: props.notificationSettings?.whatsapp?.enabled ?? false,
    waha_api_url: props.notificationSettings?.whatsapp?.waha_api_url ?? '',
    waha_api_key: '',
    waha_session: props.notificationSettings?.whatsapp?.waha_session ?? 'default',
    default_group: props.notificationSettings?.whatsapp?.default_group ?? '',
  },
});

// Modals untuk Pengujian
const showTestEmailModal = ref(false);
const testEmailTarget = ref(props.user?.email || '');

const showTestWaModal = ref(false);
const testWaTarget = ref(props.notificationSettings?.whatsapp?.default_group || '');

const showTestTelegramModal = ref(false);
const testTelegramTarget = ref(props.notificationSettings?.telegram?.default_group || '');

function saveNotificationSettings() {
  isSaving.value = true;
  router.post('/admin/notifications', form.value, {
    preserveScroll: true,
    onFinish: () => {
      isSaving.value = false;
    },
  });
}

function runTest(channel: 'mail' | 'whatsapp' | 'telegram', target?: string) {
  isTesting.value = true;
  testingChannel.value = channel;
  router.post('/admin/notifications/test', { channel, target }, {
    preserveScroll: true,
    onFinish: () => {
      isTesting.value = false;
      testingChannel.value = null;
      if (channel === 'mail') showTestEmailModal.value = false;
      if (channel === 'whatsapp') showTestWaModal.value = false;
      if (channel === 'telegram') showTestTelegramModal.value = false;
    },
  });
}

// Manajemen Konfigurasi AI Platform
const activeAiTab = ref<'gemini' | 'openai' | 'claude' | '9router'>(
  (props.aiSettings?.active_provider as 'gemini' | 'openai' | 'claude' | '9router') || 'gemini'
);
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
    model: props.aiSettings?.providers?.['9router']?.model || 'gpt-4o-mini',
  },
});

function saveAiSettings() {
  isAiSaving.value = true;
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
    :apps="appsList"
    app-key="admin"
    app-name="Administrasi Sistem"
  >
    <RdPageHeader
      title="Administrasi Platform RUDIMAH"
      description="Pusat kendali operasional server, manajemen seluruh ruang kerja, katalog aplikasi, direktori pengguna, dan konfigurasi saluran notifikasi"
      :breadcrumbs="[{ label: 'Beranda', href: '/workspaces' }, { label: 'Administrasi Platform' }]"
    >
      <template #actions>
        <a href="/workspaces" class="w-full sm:w-auto">
          <RdButton variant="outline" size="sm" class="w-full sm:w-auto">
            Kembali ke Pemilih Ruang Kerja
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Feedback Banner Notifikasi Sukses / Gagal -->
    <div v-if="status" class="mb-4 sm:mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-4 sm:mb-6">
      <RdAlert variant="error" title="Perhatian / Kendala Sistem">
        {{ error }}
      </RdAlert>
    </div>

    <!-- Kartu Metrik Sistem & Kesehatan Platform (Responsive 2 cols di mobile, 4 di desktop) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
      <RdCard padding>
        <span class="text-[11px] sm:text-xs text-slate-500 font-medium truncate block">Total Ruang Kerja</span>
        <div class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mt-0.5 sm:mt-1">
          {{ systemStats?.totalTenants ?? 1 }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 sm:mt-1 truncate">Tenant terisolasi aktif</p>
      </RdCard>

      <RdCard padding>
        <span class="text-[11px] sm:text-xs text-slate-500 font-medium truncate block">Total Pengguna</span>
        <div class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mt-0.5 sm:mt-1">
          {{ systemStats?.totalUsers ?? 1 }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 sm:mt-1 truncate">Akun identitas global</p>
      </RdCard>

      <RdCard padding>
        <span class="text-[11px] sm:text-xs text-slate-500 font-medium truncate block">Basis Data</span>
        <div class="text-xs sm:text-base font-semibold text-slate-900 dark:text-white mt-1 truncate">
          {{ systemStats?.dbEngine || 'MariaDB 11' }}
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 sm:mt-1 truncate">Dual Database</p>
      </RdCard>

      <RdCard padding>
        <span class="text-[11px] sm:text-xs text-slate-500 font-medium truncate block">Antrean & Cache</span>
        <div class="mt-1 flex items-center gap-1.5 sm:gap-2">
          <RdBadge variant="success">Redis Aktif</RdBadge>
          <span class="text-[11px] sm:text-xs text-slate-500 hidden sm:inline">v7.2</span>
        </div>
        <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 sm:mt-1 truncate">Horizon & Queues</p>
      </RdCard>
    </div>

    <!-- Seksi 1: Kelola Ruang Kerja (Workspaces) -->
    <div id="workspaces" class="mb-6">
      <RdCard
        title="Daftar Seluruh Ruang Kerja (Workspaces)"
        description="Kelola seluruh tenant ruang kerja keluarga maupun usaha yang terdaftar di platform RUDIMAH"
      >
        <RdTable :columns="tenantColumns" :items="tenantsList || []">
          <template #name="{ item }">
            <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
          </template>
          <template #slug="{ value }">
            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-xs whitespace-nowrap">
              /t/{{ value }}
            </code>
          </template>
          <template #members_count="{ value }">
            <span class="font-semibold whitespace-nowrap">{{ value }}</span>
          </template>
          <template #created_at="{ value }">
            <span class="text-xs text-slate-500 whitespace-nowrap">{{ value || '-' }}</span>
          </template>
          <template #status="{ value }">
            <RdBadge :variant="value === 'active' ? 'success' : 'neutral'" class="whitespace-nowrap">
              {{ value === 'active' ? 'Aktif' : 'Nonaktif' }}
            </RdBadge>
          </template>
          <template #actions="{ item }">
            <a :href="`/t/${item.slug}/apps/pekan/dashboard`" class="inline-block whitespace-nowrap">
              <RdButton variant="outline" size="sm">
                Buka Ruang Kerja
              </RdButton>
            </a>
          </template>
        </RdTable>
      </RdCard>
    </div>

    <!-- Seksi 2: Kelola Pengguna Global (Users) -->
    <div id="users" class="mb-6">
      <RdCard
        title="Daftar Pengguna Platform Global"
        description="Pantau seluruh akun pengguna terdaftar beserta kepatuhan keamanan MFA dan hak akses super admin"
      >
        <RdTable :columns="userColumns" :items="usersList || []">
          <template #name="{ item }">
            <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
          </template>
          <template #email="{ value }">
            <span class="text-xs text-slate-600 dark:text-slate-400 font-mono whitespace-nowrap">{{ value }}</span>
          </template>
          <template #has_mfa="{ value }">
            <RdBadge :variant="value ? 'success' : 'warning'" class="whitespace-nowrap">
              {{ value ? 'MFA Aktif' : 'Belum Ada MFA' }}
            </RdBadge>
          </template>
          <template #is_super_admin="{ value }">
            <RdBadge :variant="value ? 'info' : 'neutral'" class="whitespace-nowrap">
              {{ value ? 'Super Admin' : 'Pengguna Biasa' }}
            </RdBadge>
          </template>
          <template #workspaces_count="{ value }">
            <span class="text-xs font-medium whitespace-nowrap">{{ value }} Ruang Kerja</span>
          </template>
        </RdTable>
      </RdCard>
    </div>

    <!-- Seksi 3: Katalog Modul Aplikasi Terdaftar -->
    <div id="apps" class="mb-6">
      <RdCard
        title="Katalog Modul Aplikasi Platform"
        description="Daftar aplikasi web modular yang terintegrasi pada portal RUDIMAH"
      >
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 pt-1">
          <div
            v-for="app in (appsList || [
              { key: 'pekan', name: 'PEKAN', description: 'Pengelolaan Keuangan Keluarga & Catatan Mutasi Kas', status: 'available' },
              { key: 'app1', name: 'App1 (Skeleton)', description: 'Modul Ekstensi 1 terintegrasi SSO & Tenancy', status: 'available' },
              { key: 'app2', name: 'App2 (Skeleton)', description: 'Modul Ekstensi 2 terintegrasi SSO & Tenancy', status: 'available' }
            ])"
            :key="app.key"
            class="p-3.5 sm:p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2 flex flex-col justify-between"
          >
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <span class="font-bold text-sm text-slate-900 dark:text-white">{{ app.name }}</span>
                <RdBadge :variant="app.key === 'pekan' ? 'success' : 'neutral'">
                  {{ app.key === 'pekan' ? 'Aktif Penuh' : 'Skeleton' }}
                </RdBadge>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ app.description }}
              </p>
            </div>
            <div class="pt-2 text-[11px] text-slate-400 font-mono border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
              <span>Key: {{ app.key }}</span>
              <a
                :href="tenant?.slug ? `/t/${tenant.slug}/apps/${app.slug || app.key}` : `/apps/${app.slug || app.key}`"
                class="inline-block"
              >
                <RdButton variant="outline" size="sm">
                  Buka Modul
                </RdButton>
              </a>
            </div>
          </div>
        </div>
      </RdCard>
    </div>

    <!-- Seksi 4: Konfigurasi Penyedia AI Platform (Gemini, OpenAI, Claude, 9Router) -->
    <div id="ai-settings" class="mb-6">
      <RdCard
        title="Pusat Konfigurasi Penyedia AI Platform"
        description="Atur kecerdasan buatan terpusat untuk Asisten Keuangan, Ekstraksi Struk OCR, dan Analisis Cerdas di seluruh ruang kerja RUDIMAH (Google Gemini, OpenAI, Anthropic Claude, 9Router Gateway)"
      >
        <!-- Banner Hasil Pengujian AI Terkini -->
        <div v-if="aiCardAlert" class="mb-4">
          <RdAlert
            :variant="aiCardAlert.type"
            :title="aiCardAlert.type === 'success' ? 'Hasil Pengujian AI Berhasil' : 'Kendala Uji Koneksi AI'"
          >
            {{ aiCardAlert.message }}
          </RdAlert>
        </div>

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
            @click="activeAiTab = provKey as any"
          >
            <div class="flex items-center justify-between gap-2 mb-1.5">
              <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">
                {{ prov.name }}
              </span>
              <RdBadge v-if="aiForm.active_provider === provKey" variant="info" class="shrink-0 text-[10px]">
                Aktif
              </RdBadge>
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

            <div class="max-w-md pt-2">
              <RdSelect
                v-model="aiForm.active_provider"
                label="Penyedia AI Default Platform"
                :options="aiProviderOptions"
                hint="Penyedia default yang otomatis digunakan oleh asisten jika ruang kerja tidak menentukan kunci mandiri"
              />
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
              @click="activeAiTab = 'gemini'"
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
              @click="activeAiTab = 'openai'"
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
              @click="activeAiTab = 'claude'"
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
              @click="activeAiTab = '9router'"
            >
              <span>9Router AI Gateway</span>
              <span v-if="props.aiSettings?.providers?.['9router']?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            </button>
          </div>

          <!-- TAB 1: GOOGLE GEMINI -->
          <div v-show="activeAiTab === 'gemini'" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
              <div>
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan Google Gemini</h4>
                <p class="text-xs text-slate-500">Integrasi resmi model Gemini Flash & Gemini Pro dari Google AI Studio</p>
              </div>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
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
                  class="w-full sm:w-auto"
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
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan OpenAI ChatGPT</h4>
                <p class="text-xs text-slate-500">Integrasi model OpenAI GPT-4o, GPT-4o-mini, atau gateway kompatibel</p>
              </div>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
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
                  class="w-full sm:w-auto"
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
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan Anthropic Claude</h4>
                <p class="text-xs text-slate-500">Integrasi model Claude 3.5 Sonnet, Claude 3 Opus, dan Claude 3.5 Haiku</p>
              </div>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
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
                  class="w-full sm:w-auto"
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
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Pengaturan 9Router AI Gateway</h4>
                <p class="text-xs text-slate-500">Integrasi gateway terpadu multi-penyedia AI dengan protokol kompatibel OpenAI</p>
              </div>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
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
                  class="w-full sm:w-auto"
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
                  hint="Nama model tujuan di 9Router (misal: gpt-4o-mini, claude-3-5-sonnet, gemini-1.5-flash)"
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
    </div>

    <!-- Seksi 5: Panel Konfigurasi Notifikasi Seluruh Ruang Kerja -->
    <div id="notifications" class="mb-8">
      <RdCard
        title="Pusat Konfigurasi Saluran Notifikasi & Push Grup (Platform-Wide)"
        description="Konfigurasikan saluran pengiriman Email SMTP, WhatsApp (WAHA), dan Telegram untuk seluruh ruang kerja. Mendukung pengiriman ke nomor pribadi maupun Push Grup WhatsApp & Grup/Channel Telegram."
      >
        <!-- Ringkasan Status Kanal (Responsive 1 col mobile, 2 tablet, 3 desktop) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-6">
          <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-0.5 sm:space-y-1">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">Email (SMTP)</span>
              </div>
              <p class="text-[11px] sm:text-xs text-slate-500 font-mono truncate">
                {{ notificationSettings?.mail?.host || 'Belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.mail?.is_configured ? 'success' : 'neutral'" class="shrink-0">
              {{ notificationSettings?.mail?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>

          <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-0.5 sm:space-y-1">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">WhatsApp (WAHA)</span>
              </div>
              <p class="text-[11px] sm:text-xs text-slate-500 font-mono truncate">
                {{ notificationSettings?.whatsapp?.waha_api_url || 'Belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.whatsapp?.is_configured ? 'success' : 'neutral'" class="shrink-0">
              {{ notificationSettings?.whatsapp?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>

          <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between gap-3 sm:col-span-2 lg:col-span-1">
            <div class="min-w-0 flex-1 space-y-0.5 sm:space-y-1">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">Telegram Bot</span>
              </div>
              <p class="text-[11px] sm:text-xs text-slate-500 font-mono truncate">
                {{ notificationSettings?.telegram?.bot_username ? '@' + notificationSettings.telegram.bot_username : 'Token belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.telegram?.is_configured ? 'success' : 'neutral'" class="shrink-0">
              {{ notificationSettings?.telegram?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>
        </div>

        <!-- Navigasi Tab Saluran (Scrollable di Mobile) -->
        <div class="border-b border-slate-200 dark:border-slate-800 mb-6 flex gap-1 sm:gap-2 overflow-x-auto pb-1 -mb-px">
          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeTab === 'mail'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'mail'"
          >
            <span>Email (SMTP)</span>
            <span v-if="notificationSettings?.mail?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeTab === 'whatsapp'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'whatsapp'"
          >
            <span>WhatsApp (WAHA)</span>
            <span v-if="notificationSettings?.whatsapp?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 sm:py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 sm:gap-2 shrink-0 cursor-pointer',
              activeTab === 'telegram'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'telegram'"
          >
            <span>Telegram Bot</span>
            <span v-if="notificationSettings?.telegram?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>
        </div>

        <form @submit.prevent="saveNotificationSettings">
          <!-- TAB 1: EMAIL (SMTP) -->
          <div v-show="activeTab === 'mail'" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.mail.enabled"
                label="Aktifkan Notifikasi via Email"
                description="Gunakan server SMTP ini untuk mengirim email pemulihan akun, aktivasi, dan notifikasi tagihan"
              />
              <div class="flex gap-2 self-start sm:self-auto shrink-0 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
                  :disabled="isTesting || !notificationSettings?.mail?.is_configured"
                  @click="showTestEmailModal = true"
                >
                  <span v-if="testingChannel === 'mail'">Menguji...</span>
                  <span v-else>Uji Kirim Email</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
              <RdInput
                v-model="form.mail.host"
                label="Host SMTP"
                placeholder="Contoh: smtp.gmail.com atau mail.domainanda.id"
                hint="Alamat server SMTP penyedia layanan email Anda"
                required
              />

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-2">
                <RdInput
                  v-model.number="form.mail.port"
                  label="Port SMTP"
                  type="number"
                  placeholder="587"
                  hint="Port umum: 587 (TLS), 465 (SSL)"
                  required
                />

                <RdSelect
                  v-model="form.mail.encryption"
                  label="Protokol Enkripsi"
                  :options="encryptionOptions"
                  hint="Gunakan TLS untuk port 587"
                />
              </div>

              <RdInput
                v-model="form.mail.username"
                label="Username / Akun SMTP"
                placeholder="Contoh: info@domainanda.id"
                hint="Alamat email atau nama pengguna otentikasi SMTP"
              />

              <div>
                <RdInput
                  v-model="form.mail.password"
                  label="Password / App Password SMTP"
                  type="password"
                  :placeholder="notificationSettings?.mail?.has_password ? '•••••••• (Tersimpan - isi hanya jika ingin mengubah)' : 'Masukkan kata sandi SMTP'"
                  :hint="notificationSettings?.mail?.has_password ? 'Kata sandi saat ini tersimpan aman di platform_db.' : 'Gunakan App Password jika memakai Gmail/Google Workspace'"
                />
              </div>

              <RdInput
                v-model="form.mail.from_address"
                label="Alamat Email Pengirim (From Address)"
                type="email"
                placeholder="noreply@domainanda.id"
                hint="Alamat email yang tercantum sebagai pengirim pesan"
                required
              />

              <RdInput
                v-model="form.mail.from_name"
                label="Nama Pengirim (From Name)"
                placeholder="RUDIMAH Notifikasi"
                hint="Nama pengirim yang muncul di kotak masuk penerima"
                required
              />
            </div>
          </div>

          <!-- TAB 2: WHATSAPP (WAHA) -->
          <div v-show="activeTab === 'whatsapp'" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.whatsapp.enabled"
                label="Aktifkan Notifikasi via WhatsApp (WAHA)"
                description="Kirimkan kode OTP, pengingat keuangan, atau push ke Grup WhatsApp via gateway WAHA"
              />
              <div class="flex gap-2 self-start sm:self-auto shrink-0 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
                  :disabled="isTesting || !form.whatsapp.waha_api_url"
                  @click="showTestWaModal = true"
                >
                  <span v-if="testingChannel === 'whatsapp'">Menguji WAHA...</span>
                  <span v-else>Uji Kirim / Koneksi WAHA</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
              <div class="md:col-span-2">
                <RdInput
                  v-model="form.whatsapp.waha_api_url"
                  label="URL Endpoint Server WAHA"
                  placeholder="Contoh: http://waha:3000 atau https://waha.domainanda.id"
                  hint="Alamat basis server REST API WhatsApp HTTP API (WAHA)"
                  required
                />
              </div>

              <RdInput
                v-model="form.whatsapp.waha_api_key"
                label="WAHA API Key / Token Akses"
                type="password"
                :placeholder="notificationSettings?.whatsapp?.has_api_key ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Masukkan WAHA_API_KEY jika server WAHA dilindungi autentikasi'"
                :hint="notificationSettings?.whatsapp?.has_api_key ? 'Kunci API saat ini tersimpan aman di platform_db.' : 'Kosongkan jika server WAHA lokal tidak memerlukan kunci'"
              />

              <RdInput
                v-model="form.whatsapp.waha_session"
                label="Nama Sesi WAHA (Session Name)"
                placeholder="default"
                hint="Nama sesi WhatsApp yang terotentikasi di server WAHA (default: 'default')"
                required
              />

              <div class="md:col-span-2">
                <RdInput
                  v-model="form.whatsapp.default_group"
                  label="ID Grup WhatsApp Notifikasi Platform (Opsional)"
                  placeholder="Contoh: 120363024829102938@g.us"
                  hint="Masukkan ID Grup WhatsApp jika ingin pesan/alert sistem otomatis di-push ke grup WhatsApp tim"
                />
              </div>
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
              <span class="font-semibold text-slate-800 dark:text-slate-200">Dukungan Push ke Grup WhatsApp:</span>
              Sistem mendukung pengiriman langsung ke <strong>Grup WhatsApp</strong> menggunakan Chat ID berakhiran <code class="font-mono text-xs">@g.us</code>. Gunakan tombol *"Uji Kirim / Koneksi WAHA"* di atas untuk menguji pengiriman ke grup.
            </div>
          </div>

          <!-- TAB 3: TELEGRAM BOT -->
          <div v-show="activeTab === 'telegram'" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.telegram.enabled"
                label="Aktifkan Notifikasi via Telegram Bot"
                description="Kirimkan pemberitahuan sistem dan ringkasan ke obrolan pribadi atau Push ke Grup/Kanal Telegram"
              />
              <div class="flex gap-2 self-start sm:self-auto shrink-0 w-full sm:w-auto">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full sm:w-auto"
                  :disabled="isTesting || (!form.telegram.bot_token && !notificationSettings?.telegram?.has_bot_token)"
                  @click="showTestTelegramModal = true"
                >
                  <span v-if="testingChannel === 'telegram'">Menguji Telegram...</span>
                  <span v-else>Uji Kirim / Koneksi Telegram</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
              <RdInput
                v-model="form.telegram.bot_token"
                label="Token Bot Telegram"
                type="password"
                :placeholder="notificationSettings?.telegram?.has_bot_token ? '•••••••• (Tersimpan - isi hanya jika ingin mengganti)' : 'Contoh: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ'"
                :hint="notificationSettings?.telegram?.has_bot_token ? 'Token bot saat ini tersimpan aman di platform_db.' : 'Dapatkan token otentikasi dari @BotFather di Telegram'"
                required
              />

              <RdInput
                v-model="form.telegram.bot_username"
                label="Username Bot Telegram"
                placeholder="Contoh: rudimah_notification_bot"
                hint="Nama pengguna publik bot tanpa tanda @ (misal: rudimah_notif_bot)"
              />

              <div class="md:col-span-2">
                <RdInput
                  v-model="form.telegram.default_group"
                  label="Chat ID Grup / Kanal Telegram (Opsional)"
                  placeholder="Contoh: -1001234567890 atau @nama_kanal"
                  hint="ID Chat grup Telegram (biasanya diawali -100) atau username publik kanal tujuan push notifikasi"
                />
              </div>
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
              <span class="font-semibold text-slate-800 dark:text-slate-200">Dukungan Push ke Grup Telegram:</span>
              Untuk mengirim ke grup, tambahkan bot Anda ke dalam grup Telegram, berikan hak pengirim pesan, dan masukkan ID grup (misal <code class="font-mono text-xs">-100xxxxxxxxxx</code>) pada formulir di atas.
            </div>
          </div>

          <!-- Aksi Simpan Global (Responsive stacked on mobile) -->
          <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500 text-center sm:text-left">
              Pengaturan ini berlaku instan ke seluruh ruang kerja (workspaces) tanpa perlu merestart server.
            </p>
            <RdButton
              type="submit"
              variant="primary"
              :disabled="isSaving"
              class="w-full sm:w-auto shrink-0"
            >
              <span v-if="isSaving">Menyimpan Konfigurasi...</span>
              <span v-else>Simpan Konfigurasi Notifikasi</span>
            </RdButton>
          </div>
        </form>
      </RdCard>
    </div>

    <!-- MODAL: UJI KIRIM EMAIL (Responsive modal actions) -->
    <RdModal
      v-model="showTestEmailModal"
      title="Uji Kirim Notifikasi Email"
      description="Kirimkan email uji coba untuk memverifikasi fungsionalitas dan otentikasi kredensial SMTP"
      max-width="md"
    >
      <div class="space-y-3 py-2">
        <RdInput
          v-model="testEmailTarget"
          label="Alamat Email Tujuan Pengujian"
          type="email"
          placeholder="emailanda@domain.com"
          hint="Pastikan kotak masuk email ini aktif dan dapat menerima pesan"
          required
        />
        <p class="text-xs text-slate-500">
          Email uji coba akan dikirim menggunakan konfigurasi SMTP yang saat ini tersimpan di sistem.
        </p>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting"
            @click="showTestEmailModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting || !testEmailTarget"
            @click="runTest('mail', testEmailTarget)"
          >
            <span v-if="isTesting">Mengirim Email...</span>
            <span v-else>Kirim Sekarang</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: UJI KONEKSI & PUSH WHATSAPP (WAHA) -->
    <RdModal
      v-model="showTestWaModal"
      title="Uji Koneksi & Push WhatsApp (WAHA)"
      description="Uji konektivitas ke server REST API WAHA dan kirim pesan uji coba ke nomor pribadi atau Grup WhatsApp (@g.us)"
      max-width="md"
    >
      <div class="space-y-3 py-2">
        <RdInput
          v-model="testWaTarget"
          label="Nomor WhatsApp atau ID Grup WhatsApp (@g.us)"
          placeholder="Contoh: 08123456789 atau 120363024829102938@g.us"
          hint="Dapat diisi nomor HP pribadi (08xxx) atau Group ID WhatsApp (@g.us). Kosongkan jika hanya ingin cek server sesi WAHA."
        />
        <p class="text-xs text-slate-500">
          Jika nomor atau ID grup diisi, sistem akan mengirimkan satu pesan teks uji coba via sesi WAHA terpilih.
        </p>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting"
            @click="showTestWaModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting"
            @click="runTest('whatsapp', testWaTarget)"
          >
            <span v-if="isTesting">Menguji Koneksi / Mengirim...</span>
            <span v-else>Jalankan Pengujian</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: UJI KONEKSI & PUSH TELEGRAM -->
    <RdModal
      v-model="showTestTelegramModal"
      title="Uji Koneksi & Push Telegram"
      description="Uji koneksi ke bot Telegram atau kirimkan pesan uji coba langsung ke Grup / Kanal / Pengguna"
      max-width="md"
    >
      <div class="space-y-3 py-2">
        <RdInput
          v-model="testTelegramTarget"
          label="Chat ID Grup / Kanal / Pengguna Telegram (Opsional)"
          placeholder="Contoh: -1001234567890 atau @nama_kanal"
          hint="Masukkan Chat ID grup Telegram tempat bot berada. Kosongkan jika hanya ingin verifikasi token bot via getMe."
        />
        <p class="text-xs text-slate-500">
          Jika Chat ID diisi, bot akan mengirimkan pesan tes ke grup tersebut. Pastikan bot sudah diundang ke dalam grup.
        </p>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting"
            @click="showTestTelegramModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isTesting"
            @click="runTest('telegram', testTelegramTarget)"
          >
            <span v-if="isTesting">Menguji Telegram...</span>
            <span v-else>Jalankan Pengujian</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: UJI KONEKSI AI PLATFORM -->
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
