<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import RdButton from '../design-system/RdButton.vue';
import RdCard from '../design-system/RdCard.vue';
import RdModal from '../design-system/RdModal.vue';
import RdInput from '../design-system/RdInput.vue';
import RdThemeToggle from '../design-system/RdThemeToggle.vue';
import type { Tenant, User } from '../types/core';

const page = usePage();

const props = defineProps<{
  user?: User;
  tenants: Tenant[];
}>();

const isCreateModalOpen = ref(false);
const isUserMenuOpen = ref(false);

const currentUser = computed(() => {
  return props.user || (page.props.auth as any)?.user || {
    id: '',
    name: 'Pengguna',
    email: '',
  };
});

const userName = computed(() => {
  return currentUser.value?.name || (currentUser.value as any)?.full_name || 'Pengguna';
});

const isSuperAdmin = computed(() => {
  return Boolean((page.props.auth as any)?.user?.is_super_admin);
});

const form = useForm({
  name: '',
});

function submitWorkspace() {
  form.post('/workspaces', {
    onSuccess: () => {
      isCreateModalOpen.value = false;
      form.reset();
    },
  });
}

function logout() {
  router.post('/logout');
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col font-sans">
    <!-- Header Navigasi Portal RUDIMAH -->
    <header class="sticky top-0 z-40 h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <!-- Platform Brand -->
        <a href="/" class="flex items-center gap-2 font-bold tracking-tight text-slate-900 dark:text-white">
          <span class="w-6 h-6 rounded bg-teal-800 text-white flex items-center justify-center text-xs font-black">R</span>
          <span class="text-sm font-extrabold tracking-wide uppercase">RUDIMAH</span>
        </a>
        <span class="text-xs text-slate-400 dark:text-slate-500 hidden sm:inline-block">/ Ruang Kerja</span>
      </div>

      <div class="flex items-center gap-2 sm:gap-3">
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
            <svg class="w-3.5 h-3.5 text-slate-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Backdrop penutup dropdown ketika klik di luar -->
          <div
            v-if="isUserMenuOpen"
            class="fixed inset-0 z-40"
            @click="isUserMenuOpen = false"
          ></div>

          <div
            v-if="isUserMenuOpen"
            class="origin-top-right absolute right-0 mt-2 w-52 rounded-md shadow-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-50 py-1"
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
              v-if="isSuperAdmin"
              href="/admin"
              class="block px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              Administrasi Sistem
            </a>
            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
            <button
              type="button"
              class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer flex items-center justify-between"
              @click="logout"
            >
              <span>Keluar</span>
              <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Konten Utama Pemilih Ruang Kerja -->
    <main class="flex-1 py-12 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto w-full">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-800 text-white font-black text-lg mb-2">
          R
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
          Pilih Ruang Kerja RUDIMAH
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Pilih ruang kerja yang ingin Anda buka atau buat ruang kerja baru untuk keluarga atau tim Anda
        </p>
      </div>

      <RdCard padding class="mb-6">
        <template #header>
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
            Daftar Ruang Kerja Tersedia
          </span>
        </template>
        <template #actions>
          <RdButton size="sm" variant="outline" @click="isCreateModalOpen = true">
            Buat Ruang Kerja Baru
          </RdButton>
        </template>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
          <div
            v-for="t in tenants"
            :key="t.id"
            class="py-3 flex items-center justify-between"
          >
            <div>
              <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ t.name }}
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Peran: <span class="uppercase font-medium">{{ t.role }}</span>
              </p>
            </div>
            <a :href="`/t/${t.slug}/apps/pekan/dashboard`">
              <RdButton size="sm" variant="primary">
                Buka Aplikasi
              </RdButton>
            </a>
          </div>
        </div>
      </RdCard>

      <!-- Modal Buat Ruang Kerja -->
      <RdModal
        v-model="isCreateModalOpen"
        title="Buat Ruang Kerja Baru"
        description="Ruang kerja memisahkan data transaksi dan anggota secara aman"
      >
        <form id="create-workspace-form" @submit.prevent="submitWorkspace" class="space-y-4">
          <RdInput
            id="workspace_name"
            name="name"
            v-model="form.name"
            label="Nama Ruang Kerja"
            placeholder="Contoh: Keluarga Pratama, Toko Berkah"
            :error="form.errors.name"
            required
          />
        </form>

        <template #footer>
          <RdButton variant="outline" size="sm" @click="isCreateModalOpen = false">
            Batal
          </RdButton>
          <RdButton form="create-workspace-form" type="submit" variant="primary" size="sm" :loading="form.processing">
            Simpan Ruang Kerja
          </RdButton>
        </template>
      </RdModal>
    </main>
  </div>
</template>
