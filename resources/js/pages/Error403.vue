<script setup lang="ts">
import RdButton from '../design-system/RdButton.vue';

defineProps<{
  message?: string;
}>();
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center p-4 text-center">
    <div class="w-16 h-16 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-700 dark:text-slate-200 text-xl font-bold mb-4">
      403
    </div>
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">
      Akses Tidak Diizinkan
    </h1>
    <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 max-w-md mt-2 mb-6">
      {{ message || 'Anda tidak memiliki hak akses atau izin yang cukup untuk membuka sumber daya ini pada ruang kerja aktif.' }}
    </p>
    <a href="/">
      <RdButton variant="primary">
        Kembali ke Beranda
      </RdButton>
    </a>
  </div>
</template>
