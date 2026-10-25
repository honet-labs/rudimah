<script setup lang="ts">
import { ref } from 'vue';
import type { AppManifest } from '../types/core';

const defaultApps: AppManifest[] = [
  { key: 'pekan', slug: 'pekan', name: 'PEKAN', description: 'Pengelolaan keuangan dan anggaran', icon: 'wallet', route_prefix: '/pekan', enabled: true },
  { key: 'app1', slug: 'app1', name: 'Catatan Harian', description: 'Log harian dan agenda tugas', icon: 'calendar', route_prefix: '/app1', enabled: false },
  { key: 'app2', slug: 'app2', name: 'Inventaris Rumah', description: 'Pencatatan aset dan garansi barang', icon: 'box', route_prefix: '/app2', enabled: false },
];

const props = withDefaults(
  defineProps<{
    apps?: AppManifest[];
    currentAppKey?: string;
    tenantSlug?: string;
  }>(),
  {
    apps: () => [
      { key: 'pekan', slug: 'pekan', name: 'PEKAN', description: 'Pengelolaan keuangan dan anggaran', icon: 'wallet', route_prefix: '/pekan', enabled: true },
      { key: 'app1', slug: 'app1', name: 'Catatan Harian', description: 'Log harian dan agenda tugas', icon: 'calendar', route_prefix: '/app1', enabled: false },
      { key: 'app2', slug: 'app2', name: 'Inventaris Rumah', description: 'Pencatatan aset dan garansi barang', icon: 'box', route_prefix: '/app2', enabled: false },
    ],
    currentAppKey: 'pekan',
    tenantSlug: '',
  }
);

const isOpen = ref(false);

function toggle() {
  isOpen.value = !isOpen.value;
}

function getAppHref(app: AppManifest): string {
  if (!app.enabled) return '#';
  if (props.tenantSlug && props.tenantSlug.trim() !== '' && props.tenantSlug !== 'default') {
    return `/t/${props.tenantSlug}/apps/${app.slug}`;
  }
  return `/apps/${app.slug}`;
}
</script>

<template>
  <div class="relative inline-block text-left">
    <!-- Backdrop untuk menutup dropdown saat klik di luar area -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40"
      @click="isOpen = false"
      aria-hidden="true"
    ></div>

    <button
      type="button"
      :class="[
        'p-1.5 rounded-md transition-all cursor-pointer border relative',
        isOpen
          ? 'z-50 bg-slate-100 dark:bg-slate-800 text-teal-600 dark:text-teal-400 border-slate-300 dark:border-slate-700 ring-2 ring-teal-500/20 shadow-xs'
          : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800'
      ]"
      @click="toggle"
      aria-label="Katalog Aplikasi RUDIMAH"
      :aria-expanded="isOpen"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="origin-top-left absolute left-0 mt-2 w-72 rounded-md shadow-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 p-2"
    >
      <div class="px-2 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1 flex items-center justify-between">
        <span>Katalog Aplikasi</span>
      </div>
      <div class="grid grid-cols-1 gap-1">
        <a
          v-for="app in (apps && apps.length > 0 ? apps : defaultApps)"
          :key="app.key"
          :href="getAppHref(app)"
          :class="[
            'p-2.5 rounded-md flex items-start gap-2.5 transition-all text-left border',
            app.key === currentAppKey
              ? 'bg-slate-50 dark:bg-slate-800/80 text-teal-700 dark:text-teal-400 font-medium border-teal-500/30 ring-1 ring-teal-500/20'
              : app.enabled
                ? 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200'
                : 'border-transparent opacity-50 cursor-not-allowed text-slate-400'
          ]"
        >
          <div
            :class="[
              'w-8 h-8 rounded-md flex items-center justify-center text-xs font-bold shrink-0',
              app.key === currentAppKey
                ? 'bg-teal-700 text-white dark:bg-teal-600'
                : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
            ]"
          >
            {{ app.name.substring(0, 2).toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold flex items-center justify-between gap-1.5">
              <span class="truncate">{{ app.name }}</span>
              <span
                v-if="app.key === currentAppKey"
                class="text-[10px] bg-teal-100 dark:bg-teal-950/80 text-teal-700 dark:text-teal-300 px-1.5 py-0.5 rounded font-medium border border-teal-200 dark:border-teal-800/80"
              >
                Aktif
              </span>
              <span
                v-else-if="!app.enabled"
                class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-500 font-normal border border-slate-200 dark:border-slate-700"
              >
                Segera
              </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight mt-0.5">
              {{ app.description }}
            </p>
          </div>
        </a>
      </div>
    </div>
  </div>
</template>
