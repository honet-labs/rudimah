<script setup lang="ts">
import { ref } from 'vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';

defineProps<{
  status?: string;
  error?: string;
}>();

const email = ref('');
const loading = ref(false);
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        Pemulihan Kata Sandi
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Masukkan email akun Anda untuk menerima tautan atur ulang kata sandi
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form method="POST" action="/forgot-password" class="space-y-4" @submit="loading = true">
          <div v-if="status" class="p-3 bg-slate-50 text-slate-800 text-xs rounded-md border border-slate-300">
            {{ status }}
          </div>
          <div v-if="error" class="p-3 bg-red-50 text-red-800 text-xs rounded-md border border-red-300">
            {{ error }}
          </div>

          <RdInput
            id="email"
            v-model="email"
            type="email"
            label="Alamat Email"
            placeholder="nama@domain.com"
            required
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="loading"
            >
              Kirim Tautan Pemulihan
            </RdButton>
          </div>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          <a href="/login" class="font-medium text-teal-700 hover:underline">
            Kembali ke halaman login
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
