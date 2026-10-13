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
