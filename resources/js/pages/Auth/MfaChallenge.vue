<script setup lang="ts">
import { ref } from 'vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';

defineProps<{
  challengeType?: string;
  error?: string;
}>();

const code = ref('');
const loading = ref(false);
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-800 text-white font-black text-lg mb-2">
        R
      </div>
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        Verifikasi Multi-Faktor (MFA)
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Masukkan kode 6 digit dari aplikasi autentikator atau perangkat keamanan Anda
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form method="POST" action="/mfa/verify" class="space-y-4" @submit="loading = true">
          <div v-if="error" class="p-3 bg-red-50 text-red-800 text-xs rounded-md border border-red-300">
            {{ error }}
          </div>

          <RdInput
            id="code"
            v-model="code"
            label="Kode Autentikasi (6 Digit)"
            placeholder="Contoh: 123456"
            required
            class="text-center font-mono tracking-widest text-lg"
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="loading"
            >
              Verifikasi dan Lanjutkan
            </RdButton>
          </div>
        </form>

        <div class="mt-6 border-t border-slate-200 dark:border-slate-800 pt-4 text-center text-xs text-slate-500">
          Mengalami kendala autentikator?
          <a href="/mfa/recovery" class="font-medium text-teal-700 hover:underline block mt-1">
            Gunakan kode pemulihan darurat
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
