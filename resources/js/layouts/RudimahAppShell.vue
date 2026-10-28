<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import RdTenantSwitcher from '../design-system/RdTenantSwitcher.vue';
import RdAppLauncher from '../design-system/RdAppLauncher.vue';
import RdThemeToggle from '../design-system/RdThemeToggle.vue';
import RdNavIcon from '../design-system/RdNavIcon.vue';
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

const currentDateTime24H = ref('');
let clockTimer: number | null = null;

function updateClock() {
  const now = new Date();
  const dateStr = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(now);

  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');

  currentDateTime24H.value = `${dateStr}, ${hours}:${minutes}`;
}

onMounted(() => {
  updateClock();
  clockTimer = window.setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (clockTimer !== null) {
    clearInterval(clockTimer);
  }
});

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
    id: '',
    name: 'Ruang Kerja',
    slug: '',
    status: 'active',
    role: 'owner',
  };
});

const tenantSlug = computed(() => {
  if (props.tenant?.slug && props.tenant.slug !== 'default') {
    return props.tenant.slug;
  }
  const urlMatch = (page.url || '').match(/^\/t\/([^\/]+)/);
  if (urlMatch && urlMatch[1]) {
    return urlMatch[1];
  }
  return props.tenant?.slug || (currentTenant.value?.slug !== 'default' ? currentTenant.value?.slug : '') || '';
});

const tenantRole = computed(() => {
  return (currentTenant.value?.role || 'anggota').toUpperCase();
});

const pekanNavItems = computed(() => [
  { name: 'Dasbor Keuangan', path: `/t/${tenantSlug.value}/apps/pekan/dashboard`, icon: 'dashboard' },
  { name: 'Transaksi', path: `/t/${tenantSlug.value}/apps/pekan/transactions`, icon: 'receipt' },
  { name: 'Anggaran', path: `/t/${tenantSlug.value}/apps/pekan/budgets`, icon: 'budget' },
  { name: 'Tabungan', path: `/t/${tenantSlug.value}/apps/pekan/savings`, icon: 'savings' },
  { name: 'Pengingat Tagihan', path: `/t/${tenantSlug.value}/apps/pekan/reminders`, icon: 'bell' },
  { name: 'Pindai Struk OCR', path: `/t/${tenantSlug.value}/apps/pekan/receipt-scan`, icon: 'camera' },
  { name: 'Asisten AI', path: `/t/${tenantSlug.value}/assistant`, icon: 'chat' },
  { name: 'Laporan', path: `/t/${tenantSlug.value}/apps/pekan/reports`, icon: 'chart' },
  { name: 'Pengaturan', path: `/t/${tenantSlug.value}/apps/pekan/settings`, icon: 'cog' },
]);

const adminNavItems = computed(() => [
  { name: 'Ringkasan Sistem', path: '/admin', icon: 'server' },
  { name: 'Semua Ruang Kerja', path: '/admin#workspaces', icon: 'building' },
  { name: 'Pengguna Terdaftar', path: '/admin#users', icon: 'users' },
  { name: 'Kanal Notifikasi & OTP', path: '/admin#notifications', icon: 'bell' },
  { name: 'Katalog Modul Aplikasi', path: '/admin#apps', icon: 'apps' },
  { name: 'Pilih Ruang Kerja', path: '/workspaces', icon: 'arrow-left' },
]);

const currentNavItems = computed(() => {
  if (props.appKey === 'admin') {
    return adminNavItems.value;
  }
  if (props.appKey === 'pekan') {
    return pekanNavItems.value;
  }
  return [
    { name: 'Beranda Modul', path: `/t/${tenantSlug.value}/apps/${props.appKey}`, icon: 'home' }
  ];
});

const currentPath = computed(() => {
  const url = page.url || (typeof window !== 'undefined' ? window.location.pathname + window.location.search + window.location.hash : '');
  return url.split('?')[0];
});

function isCurrentRoute(targetPath: string): boolean {
  const cleanTarget = targetPath.split('?')[0].split('#')[0];
  const current = currentPath.value.split('#')[0];

  // Hash route matching (misal untuk /admin#workspaces)
  if (targetPath.includes('#')) {
    const hash = typeof window !== 'undefined' ? window.location.hash : '';
    return current + hash === targetPath;
  }

  // Exact match
  if (current === cleanTarget) {
    return true;
  }

  // Jika target dasbor dan URL saat ini adalah root modul (/t/:slug/apps/pekan atau /t/:slug/apps/pekan/)
  if (cleanTarget.endsWith('/dashboard')) {
    const baseAppPath = cleanTarget.replace(/\/dashboard$/, '');
    if (current === baseAppPath || current === baseAppPath + '/') {
      return true;
    }
  }

  // Sub-route match (misal /transactions/create atau /transactions/:id di bawah /transactions)
  if (cleanTarget !== '/' && current.startsWith(cleanTarget + '/')) {
    return true;
  }

  // Asisten AI route match
  if (cleanTarget.endsWith('/assistant') && current.includes('/assistant')) {
    return true;
  }

  // Pencocokan segmen rute modul (misal target dan URL aktif sama-sama mengakses /apps/pekan/settings)
  const targetModuleMatch = cleanTarget.match(/\/apps\/([^\/]+)\/([^\/]+)/);
  const currentModuleMatch = current.match(/\/apps\/([^\/]+)\/([^\/]+)/);
  if (targetModuleMatch && currentModuleMatch) {
    if (targetModuleMatch[1] === currentModuleMatch[1] && targetModuleMatch[2] === currentModuleMatch[2]) {
      return true;
    }
  }

  return false;
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
        <a href="/" class="flex items-center gap-2 font-bold tracking-tight text-slate-900 dark:text-white" title="RUDIMAH">
          <span class="w-6 h-6 rounded bg-teal-800 text-white flex items-center justify-center text-xs font-black">R</span>
          <span class="text-sm font-extrabold tracking-wide uppercase hidden sm:inline">RUDIMAH</span>
        </a>

        <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>

        <!-- App Launcher -->
        <RdAppLauncher
          :apps="apps"
          :current-app-key="appKey"
          :tenant-slug="tenantSlug"
        />

        <!-- Tenant Switcher atau Indikator Global Platform -->
        <div v-if="appKey === 'admin'" class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
          <span class="w-2 h-2 rounded-full bg-teal-600"></span>
          <span class="hidden sm:inline">Platform Global (Seluruh Ruang Kerja)</span>
          <span class="sm:hidden">Global Admin</span>
        </div>
        <RdTenantSwitcher
          v-else
          :current-tenant="currentTenant"
          :tenants="tenants && tenants.length > 0 ? tenants : [currentTenant]"
        />
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Tanggal dan Jam 24H di samping label Aplikasi -->
        <div class="hidden sm:inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono">
          <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ currentDateTime24H }}</span>
        </div>

        <!-- App Indicator Badge (Aplikasi: PEKAN) -->
        <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
          Aplikasi: {{ appName }}
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
      <aside
        :class="[
          'hidden md:flex flex-col w-60 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 sticky top-14 h-[calc(100vh-3.5rem)] self-start z-20',
          noPadding ? 'h-full' : ''
        ]"
      >
        <div class="p-3 flex-1 flex flex-col gap-1.5 overflow-y-auto">
          <div class="px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Navigasi {{ appName }}
          </div>
          <a
            v-for="item in currentNavItems"
            :key="item.path"
            :href="item.path"
            :class="[
              'group relative flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 cursor-pointer',
              isCurrentRoute(item.path)
                ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-950 dark:text-teal-100 font-semibold shadow-xs border border-teal-200/60 dark:border-teal-800/60'
                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-950 dark:hover:text-white border border-transparent'
            ]"
          >
            <!-- Indikator Bar Vertikal Menu Aktif -->
            <span
              v-if="isCurrentRoute(item.path)"
              class="absolute left-0 top-2 bottom-2 w-1 rounded-r-md bg-teal-600 dark:bg-teal-400"
            ></span>

            <!-- Ikon Navigasi -->
            <span
              :class="[
                'transition-colors shrink-0 flex items-center justify-center',
                isCurrentRoute(item.path)
                  ? 'text-teal-700 dark:text-teal-400'
                  : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200'
              ]"
            >
              <RdNavIcon :icon="item.icon" />
            </span>

            <span class="truncate">{{ item.name }}</span>
          </a>
        </div>

        <!-- Footer Workspace / Platform Status -->
        <div v-if="appKey === 'admin'" class="p-3.5 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 shrink-0">
          <div class="font-semibold text-slate-900 dark:text-slate-100">Cakupan: Seluruh Platform</div>
          <div class="text-[11px] text-teal-700 dark:text-teal-400 font-bold uppercase mt-0.5">Hak: Super Administrator</div>
        </div>
        <div v-else class="p-3.5 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 shrink-0">
          <div class="font-medium truncate">Ruang: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ currentTenant.name }}</span></div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Peran: {{ tenantRole }}</div>
        </div>
      </aside>

      <!-- Mobile Navigation Drawer -->
      <div
        v-if="isMobileNavOpen"
        class="fixed inset-0 z-50 md:hidden flex"
      >
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" @click="isMobileNavOpen = false"></div>
        <div class="relative w-72 bg-white dark:bg-slate-900 h-full p-4 flex flex-col z-10 shadow-2xl">
          <div class="flex items-center justify-between pb-3.5 border-b border-slate-200 dark:border-slate-800 mb-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              Navigasi {{ appName }}
            </span>
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
              @click="isMobileNavOpen = false"
              aria-label="Tutup menu navigasi"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="flex-1 flex flex-col gap-1.5 overflow-y-auto">
            <a
              v-for="item in currentNavItems"
              :key="item.path"
              :href="item.path"
              :class="[
                'group relative flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 cursor-pointer',
                isCurrentRoute(item.path)
                  ? 'bg-teal-50 dark:bg-teal-950/40 text-teal-950 dark:text-teal-100 font-semibold shadow-xs border border-teal-200/60 dark:border-teal-800/60'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-950 dark:hover:text-white border border-transparent'
              ]"
              @click="isMobileNavOpen = false"
            >
              <!-- Indikator Bar Vertikal Menu Aktif Mobile -->
              <span
                v-if="isCurrentRoute(item.path)"
                class="absolute left-0 top-2 bottom-2 w-1 rounded-r-md bg-teal-600 dark:bg-teal-400"
              ></span>

              <!-- Ikon Navigasi Mobile -->
              <span
                :class="[
                  'transition-colors shrink-0 flex items-center justify-center',
                  isCurrentRoute(item.path)
                    ? 'text-teal-700 dark:text-teal-400'
                    : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200'
                ]"
              >
                <RdNavIcon :icon="item.icon" />
              </span>

              <span class="truncate">{{ item.name }}</span>
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
