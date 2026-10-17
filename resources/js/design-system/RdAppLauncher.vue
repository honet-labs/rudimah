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
    <button
      type="button"
      class="p-1.5 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800 transition-colors cursor-pointer"
      @click="toggle"
      aria-label="Katalog Aplikasi RUDIMAH"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="origin-top-left absolute left-0 mt-2 w-64 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 p-2"
    >
      <div class="px-2 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
        Katalog Aplikasi
      </div>
      <div class="grid grid-cols-1 gap-1">
        <a
          v-for="app in (apps && apps.length > 0 ? apps : defaultApps)"
          :key="app.key"
          :href="getAppHref(app)"
          :class="[
            'p-2 rounded-md flex items-start gap-2.5 transition-colors text-left',
            app.key === currentAppKey
              ? 'bg-slate-100 dark:bg-slate-800 text-teal-800 dark:text-teal-400 font-medium'
              : app.enabled
                ? 'hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200'
                : 'opacity-50 cursor-not-allowed text-slate-400'
          ]"
        >
          <div class="w-7 h-7 rounded-sm bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-700 dark:text-slate-300 text-xs font-bold shrink-0">
            {{ app.name.substring(0, 2).toUpperCase() }}
          </div>
          <div>
            <div class="text-xs font-semibold flex items-center gap-1.5">
              <span>{{ app.name }}</span>
              <span v-if="!app.enabled" class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1 rounded text-slate-500 font-normal">Segera</span>
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
