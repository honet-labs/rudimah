<script setup lang="ts">
import { ref } from 'vue';
import type { Tenant } from '../types/core';

const props = withDefaults(
  defineProps<{
    currentTenant: Tenant;
    tenants?: Tenant[];
    fullWidth?: boolean;
    dropup?: boolean;
  }>(),
  {
    tenants: () => [],
    fullWidth: false,
    dropup: false,
  }
);

const isOpen = ref(false);

function toggle() {
  isOpen.value = !isOpen.value;
}

function selectTenant(tenant: Tenant) {
  isOpen.value = false;
  if (tenant && props.currentTenant && tenant.id !== props.currentTenant.id && tenant.slug) {
    window.location.href = `/t/${tenant.slug}/apps/pekan/dashboard`;
  }
}
</script>

<template>
  <div :class="['relative text-left', fullWidth ? 'w-full block' : 'inline-block']" v-if="currentTenant">
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
        'items-center gap-2 rounded-md text-xs font-semibold cursor-pointer transition-all border relative',
        fullWidth ? 'w-full flex justify-between px-3 py-2' : 'inline-flex px-3 py-1.5',
        isOpen
          ? 'z-50 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white border-slate-400 dark:border-slate-600 ring-2 ring-teal-500/20 shadow-xs'
          : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border-slate-300 dark:border-slate-700'
      ]"
      @click="toggle"
      aria-haspopup="true"
      :aria-expanded="isOpen"
    >
      <div class="flex items-center gap-2 min-w-0">
        <span class="w-2 h-2 rounded-full bg-teal-600 shrink-0" aria-hidden="true"></span>
        <span :class="['truncate', fullWidth ? 'max-w-[190px]' : 'max-w-24 sm:max-w-36']">{{ currentTenant.name || 'Ruang Kerja' }}</span>
      </div>
      <svg class="h-3 w-3 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="dropup ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'" />
      </svg>
    </button>

    <div
      v-if="isOpen"
      :class="[
        'absolute left-0 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 py-1',
        fullWidth ? 'w-full' : 'w-56',
        dropup ? 'bottom-full mb-1.5 origin-bottom-left' : 'top-full mt-1 origin-top-left'
      ]"
      role="menu"
    >
      <div class="px-3 py-1.5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
        Pilih Ruang Kerja
      </div>
      <div class="max-h-52 overflow-y-auto">
        <button
          v-for="t in (tenants.length > 0 ? tenants : [currentTenant])"
          :key="t.id"
          type="button"
          :class="[
            'w-full text-left px-3 py-2 text-xs flex items-center justify-between cursor-pointer transition-colors',
            t.id === currentTenant.id
              ? 'bg-slate-50 dark:bg-slate-800 font-semibold text-teal-800 dark:text-teal-400'
              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'
          ]"
          @click="selectTenant(t)"
        >
          <span class="truncate">{{ t.name }}</span>
          <span v-if="t.role" class="text-[10px] text-slate-400 uppercase ml-2">{{ t.role }}</span>
        </button>
      </div>
      <div class="border-t border-slate-100 dark:border-slate-800 mt-1 pt-1">
        <a
          href="/workspaces"
          class="block px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
        >
          Kelola Semua Ruang Kerja
        </a>
      </div>
    </div>
  </div>
</template>
