<script setup lang="ts">
import { ref } from 'vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdThemeToggle from '../../design-system/RdThemeToggle.vue';

defineProps<{
  error?: string;
}>();

const fullName = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
</script>

<template>
  <div class="relative min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="absolute top-4 right-4">
      <RdThemeToggle />
    </div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-800 text-white font-black text-lg mb-2">
        R
      </div>
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        Pendaftaran Akun RUDIMAH
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Buat akun untuk memulai manajemen keuangan dan hidup harian
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form method="POST" action="/register" class="space-y-4" @submit="loading = true">
          <div v-if="error" class="p-3 bg-red-50 text-red-800 text-xs rounded-md border border-red-300">
            {{ error }}
          </div>

          <RdInput
            id="name"
            v-model="fullName"
            label="Nama Lengkap"
            placeholder="Masukkan nama Anda"
            required
          />

          <RdInput
            id="email"
            v-model="email"
            type="email"
            label="Alamat Email"
            placeholder="nama@domain.com"
            required
          />

          <RdInput
            id="password"
            v-model="password"
            type="password"
            label="Kata Sandi"
            placeholder="Minimal 8 karakter"
            required
          />

          <RdInput
            id="password_confirmation"
            v-model="passwordConfirmation"
            type="password"
            label="Konfirmasi Kata Sandi"
            placeholder="Ketik ulang kata sandi"
            required
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="loading"
            >
              Daftar Sekarang
            </RdButton>
          </div>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          Sudah memiliki akun?
          <a href="/login" class="font-medium text-teal-700 hover:underline">
            Masuk ke akun
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
