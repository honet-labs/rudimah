<script setup lang="ts">
withDefaults(
  defineProps<{
    width?: string;
    height?: string;
    rounded?: string;
  }>(),
  {
    width: 'w-full',
    height: 'h-4',
    rounded: 'rounded-md',
  }
);
</script>

<template>
  <div
    :class="[
      'animate-pulse bg-slate-200 dark:bg-slate-800',
      width,
      height,
      rounded
    ]"
    aria-hidden="true"
  ></div>
</template>
