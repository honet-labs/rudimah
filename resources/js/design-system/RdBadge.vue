<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
    size?: 'sm' | 'md';
  }>(),
  {
    variant: 'neutral',
    size: 'md',
  }
);

const classes = computed(() => {
  const sizeClass = props.size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs';
  switch (props.variant) {
    case 'success':
      return `${sizeClass} bg-slate-100 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700 font-medium`;
    case 'warning':
      return `${sizeClass} bg-amber-50 text-amber-900 border border-amber-300 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 font-medium`;
    case 'danger':
      return `${sizeClass} bg-red-50 text-red-900 border border-red-300 dark:bg-red-950/40 dark:text-red-200 dark:border-red-800 font-medium`;
    case 'info':
      return `${sizeClass} bg-sky-50 text-sky-900 border border-sky-300 dark:bg-sky-950/40 dark:text-sky-200 dark:border-sky-800 font-medium`;
    case 'neutral':
    default:
      return `${sizeClass} bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-medium`;
  }
});
</script>

<template>
  <span :class="['inline-flex items-center gap-1 rounded-sm leading-none', classes]">
    <slot />
  </span>
</template>
