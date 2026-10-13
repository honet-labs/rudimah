<script setup lang="ts">
import { ref } from 'vue';
import type { Tenant } from '../types/core';

const props = defineProps<{
  currentTenant: Tenant;
  tenants: Tenant[];
}>();

const isOpen = ref(false);

function toggle() {
  isOpen.value = !isOpen.value;
}

function selectTenant(tenant: Tenant) {
  isOpen.value = false;
  if (tenant.id !== props.currentTenant.id) {
    window.location.href = `/t/${tenant.slug}/apps/pekan/dashboard`;
  }
}
</script>

<template>
  <div class="relative inline-block text-left">
    <button
      type="button"
      class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-300 dark:border-slate-700 cursor-pointer transition-colors"
      @click="toggle"
      aria-haspopup="true"
      :aria-expanded="isOpen"
    >
      <span class="w-2 h-2 rounded-full bg-teal-600" aria-hidden="true"></span>
      <span class="truncate max-w-32">{{ currentTenant.name }}</span>
      <svg class="h-3 w-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="origin-top-left absolute left-0 mt-1 w-56 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 py-1"
      role="menu"
    >
      <div class="px-3 py-1.5 border-b border-slate-100 dark:border-slate-800 text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
        Pilih Ruang Kerja
      </div>
      <button
        v-for="t in tenants"
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
        <span class="text-[10px] text-slate-400 uppercase ml-2">{{ t.role }}</span>
      </button>
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
