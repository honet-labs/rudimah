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
import type { User, AppManifest } from '../../types/core';

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
    is_configured: boolean;
  };
  whatsapp: {
    enabled: boolean;
    waha_api_url: string;
    waha_api_key?: string;
    has_api_key: boolean;
    waha_session: string;
    is_configured: boolean;
  };
}

const props = defineProps<{
  user: User;
  status?: string;
  error?: string;
  systemStats?: {
    totalTenants: number;
    totalUsers: number;
    dbEngine: string;
    redisConnected: boolean;
    appVersion: string;
  };
  notificationSettings?: NotificationSettingsProp;
  tenantsList?: AdminTenantItem[];
  usersList?: AdminUserItem[];
  appsList?: AppManifest[];
}>();

const tenantColumns = [
  { key: 'name', label: 'Nama Ruang Kerja' },
  { key: 'slug', label: 'Slug URL' },
  { key: 'members_count', label: 'Anggota', width: '100px', align: 'center' as const },
  { key: 'created_at', label: 'Terdaftar', width: '130px' },
  { key: 'status', label: 'Status', width: '110px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '150px', align: 'center' as const },
];

const userColumns = [
  { key: 'name', label: 'Nama Pengguna' },
  { key: 'email', label: 'Alamat Email' },
  { key: 'has_mfa', label: 'Keamanan MFA', width: '140px', align: 'center' as const },
  { key: 'is_super_admin', label: 'Hak Akses', width: '140px', align: 'center' as const },
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
  },
  whatsapp: {
    enabled: props.notificationSettings?.whatsapp?.enabled ?? false,
    waha_api_url: props.notificationSettings?.whatsapp?.waha_api_url ?? '',
    waha_api_key: '',
    waha_session: props.notificationSettings?.whatsapp?.waha_session ?? 'default',
  },
});

// Modals untuk Pengujian
const showTestEmailModal = ref(false);
const testEmailTarget = ref(props.user?.email || '');

const showTestWaModal = ref(false);
const testWaTarget = ref('');

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
    },
  });
}
</script>

<template>
  <RudimahAppShell :user="user" app-key="admin" app-name="Administrasi Sistem">
    <RdPageHeader
      title="Administrasi Platform RUDIMAH"
      description="Pusat kendali operasional server, manajemen seluruh ruang kerja, katalog aplikasi, direktori pengguna, dan konfigurasi saluran notifikasi"
      :breadcrumbs="[{ label: 'Beranda', href: '/workspaces' }, { label: 'Administrasi Platform' }]"
    >
      <template #actions>
        <a href="/workspaces">
          <RdButton variant="outline" size="sm">
            Kembali ke Pemilih Ruang Kerja
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Feedback Banner Notifikasi Sukses / Gagal -->
    <div v-if="status" class="mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-6">
      <RdAlert variant="error" title="Perhatian / Kendala Sistem">
        {{ error }}
      </RdAlert>
    </div>

    <!-- Kartu Metrik Sistem & Kesehatan Platform -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Total Ruang Kerja</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.totalTenants ?? 1 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Tenant terisolasi aktif</p>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Total Pengguna Terdaftar</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.totalUsers ?? 1 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Akun identitas global</p>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Basis Data Utama</span>
        <div class="text-base font-semibold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.dbEngine || 'MariaDB 11 (InnoDB)' }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Dual Database (Platform & PEKAN)</p>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Layanan Antrean & Cache</span>
        <div class="mt-1 flex items-center gap-2">
          <RdBadge variant="success">Redis Aktif</RdBadge>
          <span class="text-xs text-slate-500">v7.2</span>
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Laravel Horizon & Queues</p>
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
            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ item.name }}</span>
          </template>
          <template #slug="{ value }">
            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-xs">
              /t/{{ value }}
            </code>
          </template>
          <template #members_count="{ value }">
            <span class="font-semibold">{{ value }}</span>
          </template>
          <template #created_at="{ value }">
            <span class="text-xs text-slate-500">{{ value || '-' }}</span>
          </template>
          <template #status="{ value }">
            <RdBadge :variant="value === 'active' ? 'success' : 'neutral'">
              {{ value === 'active' ? 'Aktif' : 'Nonaktif' }}
            </RdBadge>
          </template>
          <template #actions="{ item }">
            <a :href="`/t/${item.slug}/apps/pekan/dashboard`">
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
            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ item.name }}</span>
          </template>
          <template #email="{ value }">
            <span class="text-xs text-slate-600 dark:text-slate-400 font-mono">{{ value }}</span>
          </template>
          <template #has_mfa="{ value }">
            <RdBadge :variant="value ? 'success' : 'warning'">
              {{ value ? 'MFA Aktif' : 'Belum Ada MFA' }}
            </RdBadge>
          </template>
          <template #is_super_admin="{ value }">
            <RdBadge :variant="value ? 'info' : 'neutral'">
              {{ value ? 'Super Admin' : 'Pengguna Biasa' }}
            </RdBadge>
          </template>
          <template #workspaces_count="{ value }">
            <span class="text-xs font-medium">{{ value }} Ruang Kerja</span>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
          <div
            v-for="app in (appsList || [
              { key: 'pekan', name: 'PEKAN', description: 'Pengelolaan Keuangan Keluarga & Catatan Mutasi Kas', status: 'available' },
              { key: 'app1', name: 'App1 (Skeleton)', description: 'Modul Ekstensi 1 terintegrasi SSO & Tenancy', status: 'available' },
              { key: 'app2', name: 'App2 (Skeleton)', description: 'Modul Ekstensi 2 terintegrasi SSO & Tenancy', status: 'available' }
            ])"
            :key="app.key"
            class="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-sm text-slate-900 dark:text-white">{{ app.name }}</span>
              <RdBadge :variant="app.key === 'pekan' ? 'success' : 'neutral'">
                {{ app.key === 'pekan' ? 'Aktif Penuh' : 'Skeleton' }}
              </RdBadge>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ app.description }}
            </p>
            <div class="pt-2 text-[11px] text-slate-400 font-mono">
              Key: {{ app.key }}
            </div>
          </div>
        </div>
      </RdCard>
    </div>

    <!-- Seksi 4: Panel Konfigurasi Notifikasi Seluruh Ruang Kerja -->
    <div id="notifications" class="mb-8">
      <RdCard
        title="Pusat Konfigurasi Saluran Notifikasi (Platform-Wide)"
        description="Konfigurasikan saluran pengiriman Email SMTP, WhatsApp (WAHA), dan Telegram untuk seluruh ruang kerja. Digunakan pada pemulihan akun, kode MFA, dan notifikasi pengingat transaksi."
      >
        <!-- Ringkasan Status Kanal -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-lg">✉️</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-white">Email (SMTP)</span>
              </div>
              <p class="text-xs text-slate-500 font-mono">
                {{ notificationSettings?.mail?.host || 'Belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.mail?.is_configured ? 'success' : 'neutral'">
              {{ notificationSettings?.mail?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>

          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-lg">💬</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-white">WhatsApp (WAHA)</span>
              </div>
              <p class="text-xs text-slate-500 font-mono">
                {{ notificationSettings?.whatsapp?.waha_api_url || 'Belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.whatsapp?.is_configured ? 'success' : 'neutral'">
              {{ notificationSettings?.whatsapp?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>

          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-lg">🤖</span>
                <span class="text-sm font-semibold text-slate-900 dark:text-white">Telegram Bot</span>
              </div>
              <p class="text-xs text-slate-500 font-mono">
                {{ notificationSettings?.telegram?.bot_username ? '@' + notificationSettings.telegram.bot_username : 'Token belum diisi' }}
              </p>
            </div>
            <RdBadge :variant="notificationSettings?.telegram?.is_configured ? 'success' : 'neutral'">
              {{ notificationSettings?.telegram?.is_configured ? 'Terkonfigurasi' : 'Belum Lengkap' }}
            </RdBadge>
          </div>
        </div>

        <!-- Navigasi Tab Saluran -->
        <div class="border-b border-slate-200 dark:border-slate-800 mb-6 flex gap-2">
          <button
            type="button"
            :class="[
              'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'mail'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'mail'"
          >
            <span>✉️</span>
            <span>Email (SMTP)</span>
            <span v-if="notificationSettings?.mail?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'whatsapp'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'whatsapp'"
          >
            <span>💬</span>
            <span>WhatsApp (WAHA)</span>
            <span v-if="notificationSettings?.whatsapp?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>

          <button
            type="button"
            :class="[
              'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'telegram'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="activeTab = 'telegram'"
          >
            <span>🤖</span>
            <span>Telegram Bot</span>
            <span v-if="notificationSettings?.telegram?.is_configured" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
          </button>
        </div>

        <form @submit.prevent="saveNotificationSettings">
          <!-- TAB 1: EMAIL (SMTP) -->
          <div v-show="activeTab === 'mail'" class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.mail.enabled"
                label="Aktifkan Notifikasi via Email"
                description="Gunakan server SMTP ini untuk mengirim email pemulihan akun, aktivasi, dan notifikasi tagihan"
              />
              <div class="flex gap-2">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="isTesting || !notificationSettings?.mail?.is_configured"
                  @click="showTestEmailModal = true"
                >
                  <span v-if="testingChannel === 'mail'">Menguji...</span>
                  <span v-else>✉️ Uji Kirim Email</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <RdInput
                v-model="form.mail.host"
                label="Host SMTP"
                placeholder="Contoh: smtp.gmail.com atau mail.domainanda.id"
                hint="Alamat server SMTP penyedia layanan email Anda"
                required
              />

              <div class="grid grid-cols-2 gap-2">
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
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.whatsapp.enabled"
                label="Aktifkan Notifikasi via WhatsApp (WAHA)"
                description="Kirimkan kode OTP dan pengingat tagihan keuangan langsung ke nomor WhatsApp pengguna via gateway WAHA"
              />
              <div class="flex gap-2">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="isTesting || !form.whatsapp.waha_api_url"
                  @click="showTestWaModal = true"
                >
                  <span v-if="testingChannel === 'whatsapp'">Menguji WAHA...</span>
                  <span v-else>💬 Uji Koneksi WAHA</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
              <span class="font-semibold text-slate-800 dark:text-slate-200">Integrasi WAHA (WhatsApp HTTP API):</span>
              Pastikan kontainer WAHA sudah dalam keadaan <span class="text-teal-600 dark:text-teal-400 font-medium">SCAN_QR_CODE / WORKING</span>. Pengujian koneksi akan melakukan verifikasi sesi aktif ke endpoint <code class="font-mono text-xs">/api/sessions</code>.
            </div>
          </div>

          <!-- TAB 3: TELEGRAM BOT -->
          <div v-show="activeTab === 'telegram'" class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
              <RdCheckbox
                v-model="form.telegram.enabled"
                label="Aktifkan Notifikasi via Telegram Bot"
                description="Kirimkan pemberitahuan sistem dan tautan login melalui Bot Telegram resmi platform"
              />
              <div class="flex gap-2">
                <RdButton
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="isTesting || (!form.telegram.bot_token && !notificationSettings?.telegram?.has_bot_token)"
                  @click="runTest('telegram')"
                >
                  <span v-if="testingChannel === 'telegram'">Memverifikasi Token...</span>
                  <span v-else>🤖 Uji Koneksi Telegram (getMe)</span>
                </RdButton>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800">
              <span class="font-semibold text-slate-800 dark:text-slate-200">Cara Mendapatkan Token Bot Telegram:</span>
              Buka aplikasi Telegram, kirim pesan ke <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-teal-600 dark:text-teal-400 underline font-medium">@BotFather</a>, ketik perintah <code class="font-mono text-xs">/newbot</code>, dan salin token API HTTP yang diberikan ke formulir di atas.
            </div>
          </div>

          <!-- Aksi Simpan Global -->
          <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <p class="text-xs text-slate-500">
              Pengaturan ini berlaku instan ke seluruh ruang kerja (workspaces) tanpa perlu merestart server.
            </p>
            <RdButton
              type="submit"
              variant="primary"
              :disabled="isSaving"
            >
              <span v-if="isSaving">Menyimpan Konfigurasi...</span>
              <span v-else>Simpan Konfigurasi Notifikasi</span>
            </RdButton>
          </div>
        </form>
      </RdCard>
    </div>

    <!-- MODAL: UJI KIRIM EMAIL -->
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
        <div class="flex items-center justify-end gap-2">
          <RdButton
            variant="outline"
            size="sm"
            :disabled="isTesting"
            @click="showTestEmailModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            :disabled="isTesting || !testEmailTarget"
            @click="runTest('mail', testEmailTarget)"
          >
            <span v-if="isTesting">Mengirim Email...</span>
            <span v-else>Kirim Sekarang</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: UJI KONEKSI WHATSAPP (WAHA) -->
    <RdModal
      v-model="showTestWaModal"
      title="Uji Koneksi WhatsApp WAHA"
      description="Uji konektivitas ke server REST API WAHA dan kirim pesan uji coba ke nomor tujuan"
      max-width="md"
    >
      <div class="space-y-3 py-2">
        <RdInput
          v-model="testWaTarget"
          label="Nomor WhatsApp Tujuan (Opsional)"
          placeholder="Contoh: 08123456789 atau 628123456789"
          hint="Kosongkan jika hanya ingin menguji konektivitas status server & sesi WAHA"
        />
        <p class="text-xs text-slate-500">
          Jika nomor diisi, sistem akan mengirimkan satu pesan teks percobaan via sesi WAHA terpilih.
        </p>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <RdButton
            variant="outline"
            size="sm"
            :disabled="isTesting"
            @click="showTestWaModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            :disabled="isTesting"
            @click="runTest('whatsapp', testWaTarget)"
          >
            <span v-if="isTesting">Menguji Koneksi...</span>
            <span v-else>Jalankan Pengujian</span>
          </RdButton>
        </div>
      </template>
    </RdModal>
  </RudimahAppShell>
</template>
