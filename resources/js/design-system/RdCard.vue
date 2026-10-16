<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string;
    description?: string;
    padding?: boolean;
  }>(),
  {
    padding: true,
  }
);
</script>

<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xs overflow-hidden">
    <div
      v-if="title || $slots.header || $slots.actions"
      class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
    >
      <div>
        <h3 v-if="title" class="text-sm font-semibold text-slate-800 dark:text-slate-100">
          {{ title }}
        </h3>
        <p v-if="description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          {{ description }}
        </p>
        <slot name="header" />
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2 self-start sm:self-auto">
        <slot name="actions" />
      </div>
    </div>
    <div :class="padding ? 'p-4 sm:p-5' : ''">
      <slot />
    </div>
    <div
      v-if="$slots.footer"
      class="px-4 sm:px-5 py-3 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
