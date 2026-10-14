<script setup lang="ts">
import { ref } from 'vue';
import RdTenantSwitcher from '../design-system/RdTenantSwitcher.vue';
import RdAppLauncher from '../design-system/RdAppLauncher.vue';
import type { Tenant, AppManifest, User } from '../types/core';

const props = withDefaults(
  defineProps<{
    appKey?: string;
    appName?: string;
    tenant?: Tenant;
    tenants?: Tenant[];
    apps?: AppManifest[];
    user?: User;
  }>(),
  {
    appKey: 'pekan',
    appName: 'PEKAN',
    tenant: () => ({
      id: 'default-tenant-uuid',
      name: 'Keluarga Mandiri',
      slug: 'keluarga-mandiri',
      status: 'active',
      role: 'owner',
    }),
    tenants: () => [
      { id: 'default-tenant-uuid', name: 'Keluarga Mandiri', slug: 'keluarga-mandiri', status: 'active', role: 'owner' },
      { id: 'kantor-tenant-uuid', name: 'Usaha Kreatif', slug: 'usaha-kreatif', status: 'active', role: 'admin' },
    ],
    apps: () => [
      { key: 'pekan', slug: 'pekan', name: 'PEKAN', description: 'Pengelolaan keuangan dan anggaran', icon: 'wallet', route_prefix: '/pekan', enabled: true },
      { key: 'app1', slug: 'app1', name: 'Catatan Harian', description: 'Log harian dan agenda tugas', icon: 'calendar', route_prefix: '/app1', enabled: false },
      { key: 'app2', slug: 'app2', name: 'Inventaris Rumah', description: 'Pencatatan aset dan garansi barang', icon: 'box', route_prefix: '/app2', enabled: false },
    ],
    user: () => ({
      id: 'user-default-uuid',
      name: 'Andri Susanto',
      email: 'andri@honet.web.id',
      has_mfa: true,
      mfa_type: 'TOTP',
    }),
  }
);

const isMobileNavOpen = ref(false);
const isUserMenuOpen = ref(false);

const pekanNavItems = [
  { name: 'Dasbor Keuangan', path: `/t/${props.tenant.slug}/apps/pekan/dashboard`, icon: 'dashboard' },
  { name: 'Transaksi', path: `/t/${props.tenant.slug}/apps/pekan/transactions`, icon: 'receipt' },
  { name: 'Anggaran', path: `/t/${props.tenant.slug}/apps/pekan/budgets`, icon: 'budget' },
  { name: 'Tabungan', path: `/t/${props.tenant.slug}/apps/pekan/savings`, icon: 'savings' },
  { name: 'Pengingat Tagihan', path: `/t/${props.tenant.slug}/apps/pekan/reminders`, icon: 'bell' },
  { name: 'Master Data', path: `/t/${props.tenant.slug}/apps/pekan/master-data`, icon: 'folder' },
  { name: 'Pindai Struk OCR', path: `/t/${props.tenant.slug}/apps/pekan/receipt-scan`, icon: 'camera' },
  { name: 'Asisten AI', path: `/t/${props.tenant.slug}/assistant`, icon: 'chat' },
  { name: 'Laporan', path: `/t/${props.tenant.slug}/apps/pekan/reports`, icon: 'chart' },
  { name: 'Pengaturan', path: `/t/${props.tenant.slug}/apps/pekan/settings`, icon: 'cog' },
];

function isCurrentRoute(path: string): boolean {
  if (typeof window === 'undefined') return false;
  return window.location.pathname.startsWith(path);
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col font-sans">
    <!-- Global Header -->
    <header class="sticky top-0 z-40 h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 flex items-center justify-between">
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
          :tenant-slug="tenant.slug"
        />

        <!-- Tenant Switcher -->
        <RdTenantSwitcher
          :current-tenant="tenant"
          :tenants="tenants"
        />
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-3">
        <!-- App Indicator Badge -->
        <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
          Modul: {{ appName }}
        </span>

        <!-- User Profile Dropdown -->
        <div class="relative">
          <button
            type="button"
            class="flex items-center gap-2 p-1 rounded-full text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            @click="isUserMenuOpen = !isUserMenuOpen"
            aria-label="Menu profil pengguna"
          >
            <div class="w-7 h-7 rounded-full bg-teal-700 text-white text-xs font-semibold flex items-center justify-center">
              {{ user.name.charAt(0) }}
            </div>
            <span class="text-xs font-medium hidden sm:inline-block max-w-28 truncate">
              {{ user.name }}
            </span>
          </button>

          <div
            v-if="isUserMenuOpen"
            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 py-1"
          >
            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 text-xs">
              <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ user.name }}</p>
              <p class="text-slate-500 dark:text-slate-400 text-[11px] truncate">{{ user.email }}</p>
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
            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
            <form method="POST" action="/logout">
              <button
                type="submit"
                class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer"
              >
                Keluar
              </button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <!-- Shell Body: Sidebar + Main Content -->
    <div class="flex-1 flex w-full">
      <!-- Desktop Sidebar -->
      <aside class="hidden md:flex flex-col w-56 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 min-h-[calc(100vh-3.5rem)]">
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
              :href="`/t/${tenant.slug}/apps/${appKey}`"
              class="flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium bg-slate-100 text-slate-900"
            >
              <span>Beranda Modul</span>
            </a>
          </template>
        </div>

        <!-- Footer Workspace Status -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400">
          <div>Ruang: {{ tenant.name }}</div>
          <div class="text-[10px] text-slate-400">Peran: {{ tenant.role.toUpperCase() }}</div>
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
        </div>
      </div>

      <!-- Main Content Container -->
      <main class="flex-1 min-w-0 p-4 md:p-8 max-w-7xl mx-auto w-full">
        <slot />
      </main>
    </div>
  </div>
</template>
