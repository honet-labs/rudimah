<script setup lang="ts">
import { computed } from 'vue';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdAlert from '../../design-system/RdAlert.vue';
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

interface NotificationSettingsProp {
  mail: {
    enabled: boolean;
    host: string;
    is_configured: boolean;
  };
  telegram: {
    enabled: boolean;
    bot_username: string;
    is_configured: boolean;
  };
  whatsapp: {
    enabled: boolean;
    waha_api_url: string;
    is_configured: boolean;
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
  recentTenants?: AdminTenantItem[];
  recentUsers?: AdminUserItem[];
  aiSettings?: AiSettingsProp;
  notificationSettings?: NotificationSettingsProp;
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

const activeAiDetail = computed(() => {
  const provKey = props.aiSettings?.active_provider as keyof typeof props.aiSettings.providers | undefined;
  if (!provKey || !props.aiSettings?.providers) return null;
  return props.aiSettings.providers[provKey] || null;
});
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
      description="Pusat kendali operasional server, status infrastruktur, dan ringkasan platform RUDIMAH"
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

    <!-- Kartu Metrik Sistem & Kesehatan Platform -->
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

    <!-- Hub Navigasi Cepat Modul Administrasi -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <!-- Card 1: Penyedia AI Terpusat -->
      <RdCard padding class="flex flex-col justify-between">
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kecerdasan Buatan</span>
            <RdBadge :variant="aiSettings?.is_enabled ? 'success' : 'neutral'">
              {{ aiSettings?.is_enabled ? 'AI Aktif' : 'Nonaktif' }}
            </RdBadge>
          </div>
          <h4 class="text-base font-bold text-slate-900 dark:text-white">
            Penyedia AI: {{ activeAiDetail?.name || aiSettings?.active_provider?.toUpperCase() || 'Belum Diatur' }}
          </h4>
          <p class="text-xs text-slate-500 leading-relaxed">
            Model aktif: <code class="font-mono text-teal-600 dark:text-teal-400 font-medium">{{ activeAiDetail?.model || '-' }}</code>. Digunakan serentak untuk Chat Asisten Keuangan dan Pindai Struk OCR di seluruh ruang kerja.
          </p>
        </div>
        <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <a href="/admin/ai">
            <RdButton variant="outline" size="sm">
              Konfigurasi AI Platform
            </RdButton>
          </a>
        </div>
      </RdCard>

      <!-- Card 2: Kanal Notifikasi & OTP -->
      <RdCard padding class="flex flex-col justify-between">
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kanal Pengiriman</span>
            <span class="text-xs text-slate-500">Platform-wide</span>
          </div>
          <h4 class="text-base font-bold text-slate-900 dark:text-white">
            Kanal Notifikasi, OTP & Push Grup
          </h4>
          <div class="flex flex-wrap gap-1.5 pt-1">
            <RdBadge :variant="notificationSettings?.mail?.is_configured ? 'success' : 'neutral'">
              Email SMTP: {{ notificationSettings?.mail?.is_configured ? 'Siap' : 'Belum Lengkap' }}
            </RdBadge>
            <RdBadge :variant="notificationSettings?.whatsapp?.is_configured ? 'success' : 'neutral'">
              WhatsApp: {{ notificationSettings?.whatsapp?.is_configured ? 'Siap' : 'Belum Lengkap' }}
            </RdBadge>
            <RdBadge :variant="notificationSettings?.telegram?.is_configured ? 'success' : 'neutral'">
              Telegram: {{ notificationSettings?.telegram?.is_configured ? 'Siap' : 'Belum Lengkap' }}
            </RdBadge>
          </div>
        </div>
        <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <a href="/admin/notifications">
            <RdButton variant="outline" size="sm">
              Kelola Saluran Notifikasi
            </RdButton>
          </a>
        </div>
      </RdCard>

      <!-- Card 3: Direktori Semua Ruang Kerja -->
      <RdCard padding class="flex flex-col justify-between">
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Multi-Tenancy</span>
            <RdBadge variant="info">
              {{ systemStats?.totalTenants ?? 1 }} Ruang Kerja
            </RdBadge>
          </div>
          <h4 class="text-base font-bold text-slate-900 dark:text-white">
            Direktori Seluruh Ruang Kerja
          </h4>
          <p class="text-xs text-slate-500 leading-relaxed">
            Kelola seluruh tenant keluarga dan usaha, pantau anggota, dan akses instan ke seluruh ruang kerja terdaftar.
          </p>
        </div>
        <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <a href="/admin/workspaces">
            <RdButton variant="outline" size="sm">
              Buka Semua Ruang Kerja
            </RdButton>
          </a>
        </div>
      </RdCard>

      <!-- Card 4: Direktori Pengguna Global -->
      <RdCard padding class="flex flex-col justify-between">
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Identitas & Akses</span>
            <RdBadge variant="info">
              {{ systemStats?.totalUsers ?? 1 }} Akun
            </RdBadge>
          </div>
          <h4 class="text-base font-bold text-slate-900 dark:text-white">
            Direktori Pengguna & Keamanan MFA
          </h4>
          <p class="text-xs text-slate-500 leading-relaxed">
            Pantau status kepatuhan Multi-Factor Authentication (Passkey & TOTP), hak akses Super Administrator, dan membership pengguna.
          </p>
        </div>
        <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
          <a href="/admin/users">
            <RdButton variant="outline" size="sm">
              Buka Semua Pengguna
            </RdButton>
          </a>
        </div>
      </RdCard>
    </div>

    <!-- Seksi 1: Pratinjau Ruang Kerja Terbaru -->
    <div class="mb-6">
      <RdCard
        title="Ruang Kerja Terkini"
        description="Pratinjau tenant terdaftar terbaru di platform RUDIMAH"
      >
        <template #actions>
          <a href="/admin/workspaces">
            <RdButton variant="outline" size="sm">
              Lihat Semua Ruang Kerja
            </RdButton>
          </a>
        </template>
        <RdTable :columns="tenantColumns" :items="recentTenants || []">
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

    <!-- Seksi 2: Pratinjau Pengguna Global Terbaru -->
    <div class="mb-6">
      <RdCard
        title="Pengguna Global Terkini"
        description="Pratinjau pengguna yang baru mendaftar pada portal platform"
      >
        <template #actions>
          <a href="/admin/users">
            <RdButton variant="outline" size="sm">
              Lihat Semua Pengguna
            </RdButton>
          </a>
        </template>
        <RdTable :columns="userColumns" :items="recentUsers || []">
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
  </RudimahAppShell>
</template>
