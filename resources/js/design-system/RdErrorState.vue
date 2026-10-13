<script setup lang="ts">
import RdButton from './RdButton.vue';

withDefaults(
  defineProps<{
    title?: string;
    message?: string;
    showRetry?: boolean;
    retryText?: string;
  }>(),
  {
    title: 'Terjadi Kesalahan',
    message: 'Sistem mengalami kendala saat memproses permintaan. Silakan coba kembali.',
    showRetry: true,
    retryText: 'Coba Lagi',
  }
);

defineEmits<{
  (e: 'retry'): void;
}>();
</script>

<template>
  <div class="flex flex-col items-center justify-center p-8 md:p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg">
    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 mb-3">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
    </div>
    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
      {{ title }}
    </h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mt-1 mb-4">
      {{ message }}
    </p>
    <div v-if="showRetry || $slots.actions" class="mt-2 flex gap-2">
      <RdButton
        v-if="showRetry"
        variant="outline"
        size="sm"
        @click="$emit('retry')"
      >
        {{ retryText }}
      </RdButton>
      <slot name="actions" />
    </div>
  </div>
</template>
