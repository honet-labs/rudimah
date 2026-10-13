<script setup lang="ts">
import { ref } from 'vue';
import RdButton from '../design-system/RdButton.vue';
import RdCard from '../design-system/RdCard.vue';
import RdModal from '../design-system/RdModal.vue';
import RdInput from '../design-system/RdInput.vue';
import type { Tenant, User } from '../types/core';

defineProps<{
  user: User;
  tenants: Tenant[];
}>();

const isCreateModalOpen = ref(false);
const newWorkspaceName = ref('');
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
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
        <form method="POST" action="/workspaces" class="space-y-4">
          <RdInput
            id="workspace_name"
            v-model="newWorkspaceName"
            label="Nama Ruang Kerja"
            placeholder="Contoh: Keluarga Pratama, Toko Berkah"
            required
          />

          <template #footer>
            <RdButton variant="outline" size="sm" @click="isCreateModalOpen = false">
              Batal
            </RdButton>
            <RdButton type="submit" variant="primary" size="sm">
              Simpan Ruang Kerja
            </RdButton>
          </template>
        </form>
      </RdModal>
    </div>
  </div>
</template>
