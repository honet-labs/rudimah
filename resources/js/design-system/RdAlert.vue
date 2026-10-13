<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'info' | 'success' | 'warning' | 'error';
    title?: string;
  }>(),
  {
    variant: 'info',
  }
);

const alertClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-slate-50 border-slate-300 text-slate-800 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100';
    case 'warning':
      return 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-100';
    case 'error':
      return 'bg-red-50 border-red-300 text-red-900 dark:bg-red-950/40 dark:border-red-800 dark:text-red-100';
    case 'info':
    default:
      return 'bg-slate-50 border-slate-300 text-slate-800 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100';
  }
});
</script>

<template>
  <div :class="['p-4 rounded-md border text-left text-xs md:text-sm', alertClasses]" role="alert">
    <h4 v-if="title" class="font-semibold mb-1">
      {{ title }}
    </h4>
    <div>
      <slot />
    </div>
  </div>
</template>
