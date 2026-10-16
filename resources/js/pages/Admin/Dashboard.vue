<script setup lang="ts">
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import RdButton from '../../design-system/RdButton.vue';
import type { User, Tenant, AppManifest } from '../../types/core';

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

defineProps<{
  user: User;
  systemStats?: {
    totalTenants: number;
    totalUsers: number;
    dbEngine: string;
    redisConnected: boolean;
    appVersion: string;
  };
  notificationChannels?: Record<string, {
    name: string;
    configured: boolean;
    detail: string;
  }>;
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
</script>

<template>
  <RudimahAppShell :user="user" app-key="admin" app-name="Administrasi Sistem">
    <RdPageHeader
      title="Administrasi Platform RUDIMAH"
      description="Pusat kendali operasional server, manajemen seluruh ruang kerja, katalog aplikasi, dan direktori pengguna"
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

    <!-- Seksi 4: Kanal Pengiriman Notifikasi & Pemulihan Akun -->
    <div id="notifications" class="mb-6">
      <RdCard
        title="Status Kanal Notifikasi & Pemulihan Akun"
        description="Konfigurasi saluran pengiriman email, Telegram, dan WhatsApp untuk reset password & pengingat sistem"
      >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
          <div
            v-for="(channel, key) in (notificationChannels || {
              mail: { name: 'Email (SMTP)', configured: false, detail: 'Belum diatur (MAIL_HOST)' },
              telegram: { name: 'Telegram Bot API', configured: false, detail: 'Belum diatur (TELEGRAM_BOT_TOKEN)' },
              whatsapp: { name: 'WhatsApp (WAHA)', configured: false, detail: 'Belum diatur (WAHA_API_URL)' }
            })"
            :key="key"
            class="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-2"
          >
            <div class="flex items-center justify-between">
              <span class="font-semibold text-xs text-slate-900 dark:text-white">{{ channel.name }}</span>
              <RdBadge :variant="channel.configured ? 'success' : 'neutral'">
                {{ channel.configured ? 'Terkonfigurasi' : 'Belum Disetup' }}
              </RdBadge>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
              {{ channel.detail }}
            </p>
          </div>
        </div>

        <div class="mt-4 p-3 bg-slate-100 dark:bg-slate-800/60 rounded-md text-xs text-slate-600 dark:text-slate-300">
          <span class="font-semibold text-slate-800 dark:text-slate-200">Panduan Konfigurasi:</span>
          Untuk mengaktifkan pengiriman email/telegram/whatsapp, lengkapi parameter <code class="font-mono text-teal-700 dark:text-teal-400">MAIL_HOST</code>, <code class="font-mono text-teal-700 dark:text-teal-400">TELEGRAM_BOT_TOKEN</code>, atau <code class="font-mono text-teal-700 dark:text-teal-400">WAHA_API_URL</code> pada berkas <code class="font-mono">.env</code> server lalu muat ulang kontainer.
        </div>
      </RdCard>
    </div>
  </RudimahAppShell>
</template>
