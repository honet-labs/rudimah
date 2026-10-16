<script setup lang="ts">
interface Column {
  key: string;
  label: string;
  align?: 'left' | 'center' | 'right';
  width?: string;
}

withDefaults(
  defineProps<{
    columns: Column[];
    items?: Record<string, any>[];
    loading?: boolean;
    emptyText?: string;
  }>(),
  {
    items: () => [],
    loading: false,
    emptyText: 'Tidak ada data untuk ditampilkan',
  }
);
</script>

<template>
  <div class="w-full overflow-hidden border border-slate-200 dark:border-slate-800 rounded-lg bg-white dark:bg-slate-900 shadow-xs">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
        <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 text-xs uppercase font-semibold text-slate-600 dark:text-slate-400">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :style="{ width: col.width }"
              :class="[
                'px-4 py-3',
                col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
              ]"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
              <div class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4 text-teal-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Sedang memuat data...</span>
              </div>
            </td>
          </tr>
          <tr v-else-if="!items || items.length === 0">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400 text-xs">
              {{ emptyText }}
            </td>
          </tr>
          <tr
            v-for="(item, idx) in (items || [])"
            v-else
            :key="item.id || idx"
            class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              :class="[
                'px-4 py-3 text-xs md:text-sm',
                col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
              ]"
            >
              <slot :name="col.key" :item="item" :value="item[col.key]">
                {{ item[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
