<script setup lang="ts">
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import type { User, AppManifest, Tenant } from '../../types/core';

const props = defineProps<{
  user: User;
  tenant?: Tenant | null;
  status?: string;
  error?: string;
  appsList?: AppManifest[];
}>();
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
      title="Katalog Modul Aplikasi Platform"
      description="Daftar aplikasi web modular terpadu yang terpasang pada platform RUDIMAH dengan arsitektur multi-tenant dan Single Sign-On"
      :breadcrumbs="[{ label: 'Administrasi', href: '/admin' }, { label: 'Katalog Modul Aplikasi' }]"
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

    <!-- Informasi Arsitektur Modular RUDIMAH -->
    <RdCard padding class="mb-6 bg-slate-50/50 dark:bg-slate-900/40 border-dashed">
      <div class="flex items-start gap-3">
        <div class="p-2 rounded-lg bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 shrink-0 mt-0.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <div class="space-y-1">
          <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Arsitektur Modul Terpadu RUDIMAH</h4>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            Setiap modul aplikasi yang terdaftar di platform RUDIMAH berbagi otentikasi identitas bersama (SSO), isolasi data ruang kerja (Multi-Tenant Fail-Closed), token antarmuka sistem terpadu, dan kecerdasan buatan terpusat.
          </p>
        </div>
      </div>
    </RdCard>

    <!-- Grid Modul Terpasang -->
    <RdCard
      title="Daftar Modul Terpasang"
      :description="`Tersedia ${props.appsList?.length || 3} modul platform terdaftar`"
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 pt-1">
        <div
          v-for="app in (appsList || [
            { key: 'pekan', name: 'PEKAN', description: 'Pengelolaan Keuangan Keluarga & Catatan Mutasi Kas', status: 'available' },
            { key: 'app1', name: 'Catatan Harian', description: 'Modul Ekstensi Catatan Jurnal & Dokumentasi Harian', status: 'available' },
            { key: 'app2', name: 'Inventaris Rumah', description: 'Modul Ekstensi Manajemen Barang & Aset Rumah Tangga', status: 'available' }
          ])"
          :key="app.key"
          class="p-4 sm:p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3 flex flex-col justify-between hover:border-slate-300 dark:hover:border-slate-700 transition-colors shadow-xs"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <span class="font-bold text-sm sm:text-base text-slate-900 dark:text-white">{{ app.name }}</span>
              <RdBadge :variant="app.key === 'pekan' ? 'success' : 'neutral'" class="shrink-0 text-[10px]">
                {{ app.key === 'pekan' ? 'Aktif Penuh' : 'Skeleton' }}
              </RdBadge>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              {{ app.description }}
            </p>
          </div>

          <div class="pt-3 text-[11px] text-slate-400 font-mono border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <span class="truncate">Key: {{ app.key }}</span>
            <a
              :href="tenant?.slug ? `/t/${tenant.slug}/apps/${app.slug || app.key}` : `/apps/${app.slug || app.key}`"
              class="inline-block shrink-0"
            >
              <RdButton variant="outline" size="sm">
                {{ app.key === 'pekan' ? 'Buka Dasbor Keuangan' : 'Buka Modul' }}
              </RdButton>
            </a>
          </div>
        </div>
      </div>
    </RdCard>
  </RudimahAppShell>
</template>
