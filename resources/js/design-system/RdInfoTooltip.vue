<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const props = withDefaults(
  defineProps<{
    text?: string;
    title?: string;
    size?: 'sm' | 'md';
    align?: 'left' | 'center' | 'right';
  }>(),
  {
    text: '',
    title: 'Informasi',
    size: 'md',
    align: 'left',
  }
);

const isOpen = ref(false);
const containerRef = ref<HTMLElement | null>(null);
let hoverTimer: ReturnType<typeof setTimeout> | null = null;

function toggle() {
  isOpen.value = !isOpen.value;
}

function handleMouseEnter() {
  if (hoverTimer) clearTimeout(hoverTimer);
  hoverTimer = setTimeout(() => {
    isOpen.value = true;
  }, 120);
}

function handleMouseLeave() {
  if (hoverTimer) clearTimeout(hoverTimer);
  hoverTimer = setTimeout(() => {
    isOpen.value = false;
  }, 200);
}

function cancelClose() {
  if (hoverTimer) {
    clearTimeout(hoverTimer);
    hoverTimer = null;
  }
}

function close() {
  isOpen.value = false;
}

function handleDocumentClick(event: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
    isOpen.value = false;
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  if (hoverTimer) clearTimeout(hoverTimer);
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div
    ref="containerRef"
    class="relative inline-flex items-center align-middle"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <button
      type="button"
      :class="[
        'rounded-full inline-flex items-center justify-center transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-teal-700/30 shrink-0 select-none',
        size === 'sm'
          ? 'w-4 h-4 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800'
          : 'w-5 h-5 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800',
        isOpen ? 'text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-950/40 ring-1 ring-teal-700/30' : ''
      ]"
      :aria-label="title ? `Informasi: ${title}` : 'Informasi'"
      :aria-expanded="isOpen"
      @click.stop="toggle"
    >
      <svg
        :class="size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="9" stroke="currentColor" />
        <path stroke-linecap="round" d="M12 16v-4M12 8h.01" />
      </svg>
    </button>

    <!-- Popover Informasi -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="isOpen"
        :class="[
          'absolute top-full mt-2 z-50 w-72 sm:w-80 max-w-[calc(100vw-2rem)] rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-3 text-left font-normal normal-case tracking-normal cursor-default',
          align === 'right' ? 'right-0' : align === 'center' ? 'left-1/2 -translate-x-1/2' : 'left-0 sm:left-auto'
        ]"
        role="tooltip"
        @mouseenter="cancelClose"
        @mouseleave="handleMouseLeave"
        @click.stop
      >
        <!-- Header Info Card -->
        <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100 dark:border-slate-800">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9" />
              <path stroke-linecap="round" d="M12 16v-4M12 8h.01" />
            </svg>
            {{ title }}
          </span>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            aria-label="Tutup informasi"
            @click.stop="close"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Konten Penjelasan / Deskripsi Informasi -->
        <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed break-words">
          <slot>{{ text }}</slot>
        </div>
      </div>
    </Transition>
  </div>
</template>
