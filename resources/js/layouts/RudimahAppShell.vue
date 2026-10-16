<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import RdTenantSwitcher from '../design-system/RdTenantSwitcher.vue';
import RdAppLauncher from '../design-system/RdAppLauncher.vue';
import RdThemeToggle from '../design-system/RdThemeToggle.vue';
import type { Tenant, AppManifest, User } from '../types/core';

const page = usePage();

const props = withDefaults(
  defineProps<{
    appKey?: string;
    appName?: string;
    tenant?: Tenant;
    tenants?: Tenant[];
    apps?: AppManifest[];
    user?: User;
    noPadding?: boolean;
  }>(),
  {
    appKey: 'pekan',
    appName: 'PEKAN',
    noPadding: false,
  }
);

const isMobileNavOpen = ref(false);
const isUserMenuOpen = ref(false);

const currentUser = computed(() => {
  return props.user || (page.props.auth as any)?.user || {
    id: '',
    name: 'Pengguna',
    email: '',
  };
});

const userName = computed(() => {
  return (currentUser.value as any)?.name || (currentUser.value as any)?.full_name || 'Pengguna';
});

const currentTenant = computed(() => {
  return props.tenant || {
    id: 'default-tenant-uuid',
    name: 'Ruang Kerja',
    slug: 'default',
    status: 'active',
    role: 'owner',
  };
});

const tenantSlug = computed(() => currentTenant.value?.slug || '');

const tenantRole = computed(() => {
  return (currentTenant.value?.role || 'anggota').toUpperCase();
});

const pekanNavItems = computed(() => [
  { name: 'Dasbor Keuangan', path: `/t/${tenantSlug.value}/apps/pekan/dashboard`, icon: 'dashboard' },
  { name: 'Transaksi', path: `/t/${tenantSlug.value}/apps/pekan/transactions`, icon: 'receipt' },
  { name: 'Anggaran', path: `/t/${tenantSlug.value}/apps/pekan/budgets`, icon: 'budget' },
  { name: 'Tabungan', path: `/t/${tenantSlug.value}/apps/pekan/savings`, icon: 'savings' },
  { name: 'Pengingat Tagihan', path: `/t/${tenantSlug.value}/apps/pekan/reminders`, icon: 'bell' },
  { name: 'Master Data', path: `/t/${tenantSlug.value}/apps/pekan/master-data`, icon: 'folder' },
  { name: 'Pindai Struk OCR', path: `/t/${tenantSlug.value}/apps/pekan/receipt-scan`, icon: 'camera' },
  { name: 'Asisten AI', path: `/t/${tenantSlug.value}/assistant`, icon: 'chat' },
  { name: 'Laporan', path: `/t/${tenantSlug.value}/apps/pekan/reports`, icon: 'chart' },
  { name: 'Pengaturan', path: `/t/${tenantSlug.value}/apps/pekan/settings`, icon: 'cog' },
]);

function isCurrentRoute(path: string): boolean {
  if (typeof window === 'undefined') return false;
  return window.location.pathname.startsWith(path);
}
</script>

<template>
  <div :class="['bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-sans', noPadding ? 'h-screen overflow-hidden' : 'min-h-screen']">
    <!-- Global Header -->
    <header class="sticky top-0 z-40 h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-3">
        <!-- Mobile hamburger -->
        <button
          type="button"
          class="md:hidden p-1.5 rounded-md text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
          @click="isMobileNavOpen = !isMobileNavOpen"
          aria-label="Buka menu navigasi"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <!-- Platform Brand -->
        <a href="/" class="flex items-center gap-2 font-bold tracking-tight text-slate-900 dark:text-white">
          <span class="w-6 h-6 rounded bg-teal-800 text-white flex items-center justify-center text-xs font-black">R</span>
          <span class="text-sm font-extrabold tracking-wide uppercase">RUDIMAH</span>
        </a>

        <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>

        <!-- App Launcher -->
        <RdAppLauncher
          :apps="apps"
          :current-app-key="appKey"
          :tenant-slug="tenantSlug"
        />

        <!-- Tenant Switcher -->
        <RdTenantSwitcher
          :current-tenant="currentTenant"
          :tenants="tenants && tenants.length > 0 ? tenants : [currentTenant]"
        />
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- App Indicator Badge -->
        <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
          Modul: {{ appName }}
        </span>

        <!-- Tombol Beralih Mode Terang / Gelap -->
        <RdThemeToggle />

        <!-- User Profile Dropdown -->
        <div class="relative">
          <button
            type="button"
            class="flex items-center gap-2 p-1 rounded-full text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            @click="isUserMenuOpen = !isUserMenuOpen"
            aria-label="Menu profil pengguna"
          >
            <div class="w-7 h-7 rounded-full bg-teal-700 text-white text-xs font-semibold flex items-center justify-center">
              {{ userName.charAt(0).toUpperCase() }}
            </div>
            <span class="text-xs font-medium hidden sm:inline-block max-w-28 truncate">
              {{ userName }}
            </span>
          </button>

          <!-- Backdrop penutup dropdown ketika klik di luar -->
          <div
            v-if="isUserMenuOpen"
            class="fixed inset-0 z-40"
            @click="isUserMenuOpen = false"
          ></div>

          <div
            v-if="isUserMenuOpen"
            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 py-1"
          >
            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 text-xs">
              <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ userName }}</p>
              <p class="text-slate-500 dark:text-slate-400 text-[11px] truncate">{{ currentUser.email }}</p>
            </div>
            <a
              href="/profile"
              class="block px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              Profil & Keamanan MFA
            </a>
            <a
              href="/admin"
              class="block px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              Administrasi Sistem
            </a>
            <div class="px-3 py-1.5 flex items-center justify-between text-xs text-slate-700 dark:text-slate-200 border-t border-slate-100 dark:border-slate-800">
              <span class="text-[11px]">Mode Tampilan</span>
              <RdThemeToggle size="sm" />
            </div>
            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
            <button
              type="button"
              class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer"
              @click="router.post('/logout')"
            >
              Keluar
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Shell Body: Sidebar + Main Content -->
    <div :class="['flex-1 flex w-full', noPadding ? 'min-h-0 overflow-hidden' : '']">
      <!-- Desktop Sidebar -->
      <aside :class="['hidden md:flex flex-col w-56 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0', noPadding ? 'h-full overflow-y-auto' : 'min-h-[calc(100vh-3.5rem)]']">
        <div class="p-3 flex-1 flex flex-col gap-1">
          <div class="px-2 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            Navigasi {{ appName }}
          </div>
          <template v-if="appKey === 'pekan'">
            <a
              v-for="item in pekanNavItems"
              :key="item.path"
              :href="item.path"
              :class="[
                'flex items-center gap-2.5 px-3 py-2 rounded-md text-xs font-medium transition-colors',
                isCurrentRoute(item.path)
                  ? 'bg-teal-50 dark:bg-teal-950/30 text-teal-800 dark:text-teal-400 font-semibold'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="isCurrentRoute(item.path) ? 'bg-teal-600' : 'bg-slate-300 dark:bg-slate-600'"></span>
              <span>{{ item.name }}</span>
            </a>
          </template>
          <template v-else>
            <a
              :href="`/t/${tenantSlug}/apps/${appKey}`"
              class="flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium bg-slate-100 text-slate-900"
            >
              <span>Beranda Modul</span>
            </a>
          </template>
        </div>

        <!-- Footer Workspace Status -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400">
          <div>Ruang: {{ currentTenant.name }}</div>
          <div class="text-[10px] text-slate-400">Peran: {{ tenantRole }}</div>
        </div>
      </aside>

      <!-- Mobile Navigation Drawer -->
      <div
        v-if="isMobileNavOpen"
        class="fixed inset-0 z-50 md:hidden flex"
      >
        <div class="fixed inset-0 bg-slate-900/50" @click="isMobileNavOpen = false"></div>
        <div class="relative w-64 bg-white dark:bg-slate-900 h-full p-4 flex flex-col z-10">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
              Navigasi {{ appName }}
            </span>
            <button
              type="button"
              class="p-1 text-slate-400 hover:text-slate-700"
              @click="isMobileNavOpen = false"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="flex-1 flex flex-col gap-1 overflow-y-auto">
            <a
              v-for="item in pekanNavItems"
              :key="item.path"
              :href="item.path"
              class="flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="isMobileNavOpen = false"
            >
              <span>{{ item.name }}</span>
            </a>
          </div>

          <!-- Pengaturan Tema di Drawer Mobile -->
          <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between mt-auto">
            <span class="text-xs text-slate-500 dark:text-slate-400">Mode Tampilan</span>
            <RdThemeToggle show-label size="sm" />
          </div>
        </div>
      </div>

      <!-- Main Content Container (Full Width Fluid) -->
      <main :class="['flex-1 min-w-0 w-full', noPadding ? 'h-full overflow-hidden p-0 flex flex-col' : 'p-4 sm:p-6 lg:p-8']">
        <slot />
      </main>
    </div>
  </div>
</template>
