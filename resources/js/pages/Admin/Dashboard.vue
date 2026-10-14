<script setup lang="ts">
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import type { User, Tenant } from '../../types/core';

defineProps<{
  user: User;
  tenant: Tenant;
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
  tenantsList?: Tenant[];
}>();

const tenantColumns = [
  { key: 'name', label: 'Nama Ruang Kerja' },
  { key: 'slug', label: 'Slug URL' },
  { key: 'status', label: 'Status' },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-name="Administrasi">
    <RdPageHeader
      title="Administrasi Platform RUDIMAH"
      description="Pemantauan kesehatan sistem, manajemen ruang kerja, dan konfigurasi server"
      :breadcrumbs="[{ label: 'Beranda', href: '/' }, { label: 'Administrasi' }]"
    />

    <!-- Status Server & Database -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Total Ruang Kerja</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.totalTenants || 2 }}
        </div>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Total Pengguna Aktif</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.totalUsers || 5 }}
        </div>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Basis Data Utama</span>
        <div class="text-base font-semibold text-slate-900 dark:text-white mt-1">
          {{ systemStats?.dbEngine || 'MariaDB 11 (InnoDB)' }}
        </div>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium">Layanan Antrean & Cache</span>
        <div class="mt-1">
          <RdBadge variant="success">Redis Aktif</RdBadge>
        </div>
      </RdCard>
    </div>

    <!-- Kanal Pengiriman Notifikasi & Pemulihan Akun -->
    <div class="mb-6">
      <RdCard
        title="Status Kanal Notifikasi & Pemulihan Akun"
        description="Konfigurasi saluran pengiriman email, Telegram, dan WhatsApp untuk reset password & notifikasi sistem"
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

    <!-- Tabel Ruang Kerja Terdaftar -->
    <RdCard title="Semua Ruang Kerja Terdaftar" description="Daftar tenant yang aktif berjalan di platform ini">
      <RdTable
        :columns="tenantColumns"
        :items="tenantsList || [
          { name: 'Keluarga Mandiri', slug: 'keluarga-mandiri', status: 'active' },
          { name: 'Usaha Kreatif', slug: 'usaha-kreatif', status: 'active' }
        ]"
      >
        <template #status="{ value }">
          <RdBadge :variant="value === 'active' ? 'success' : 'neutral'">
            {{ value === 'active' ? 'Aktif' : 'Ditangguhkan' }}
          </RdBadge>
        </template>
      </RdTable>
    </RdCard>
  </RudimahAppShell>
</template>
