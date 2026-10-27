<script setup lang="ts">
import RdInfoTooltip from './RdInfoTooltip.vue';

interface BreadcrumbItem {
  label: string;
  href?: string;
}

withDefaults(
  defineProps<{
    title: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
  }>(),
  {
    breadcrumbs: () => [],
  }
);
</script>

<template>
  <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <nav v-if="breadcrumbs.length > 0" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mb-1.5">
        <template v-for="(item, idx) in breadcrumbs" :key="idx">
          <span v-if="idx > 0" class="text-slate-400 dark:text-slate-600" aria-hidden="true">/</span>
          <a
            v-if="item.href"
            :href="item.href"
            class="hover:text-slate-800 dark:hover:text-slate-200 transition-colors"
          >
            {{ item.label }}
          </a>
          <span v-else class="text-slate-800 dark:text-slate-200 font-medium">
            {{ item.label }}
          </span>
        </template>
      </nav>
      <div class="flex items-center gap-2 flex-wrap">
        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
          {{ title }}
        </h1>
        <RdInfoTooltip
          v-if="description"
          :text="description"
          title="Informasi Menu"
        />
      </div>
    </div>
    <div v-if="$slots.actions" class="flex items-center gap-2 self-start md:self-auto">
      <slot name="actions" />
    </div>
  </div>
</template>
