<script setup lang="ts">
import { ref, onMounted } from 'vue';

withDefaults(
  defineProps<{
    size?: 'sm' | 'md';
    showLabel?: boolean;
  }>(),
  {
    size: 'sm',
    showLabel: false,
  }
);

const isDark = ref(false);

function applyTheme(dark: boolean) {
  isDark.value = dark;
  if (typeof document !== 'undefined') {
    if (dark) {
      document.documentElement.classList.add('dark');
      document.documentElement.setAttribute('data-theme', 'dark');
      document.documentElement.style.colorScheme = 'dark';
      localStorage.setItem('rudimah_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      document.documentElement.setAttribute('data-theme', 'light');
      document.documentElement.style.colorScheme = 'light';
      localStorage.setItem('rudimah_theme', 'light');
    }
    window.dispatchEvent(new CustomEvent('rudimah-theme-changed', { detail: { isDark: dark } }));
  }
}

function toggleTheme() {
  applyTheme(!isDark.value);
}

onMounted(() => {
  if (typeof document !== 'undefined') {
    const saved = localStorage.getItem('rudimah_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    isDark.value = saved === 'dark' || (!saved && prefersDark);

    window.addEventListener('rudimah-theme-changed', (e: any) => {
      if (e.detail && typeof e.detail.isDark === 'boolean') {
        isDark.value = e.detail.isDark;
      }
    });

    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem('rudimah_theme')) {
          applyTheme(e.matches);
        }
      });
    }
  }
});
</script>

<template>
  <button
    type="button"
    :class="[
      'inline-flex items-center gap-1.5 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-teal-700 cursor-pointer',
      size === 'sm' ? 'p-1.5 text-xs' : 'p-2 text-sm'
    ]"
    :title="isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
    :aria-label="isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
    @click="toggleTheme"
  >
    <!-- Ikon Matahari (Mode Terang) saat tampilan dalam Mode Gelap -->
    <svg
      v-if="isDark"
      class="w-4 h-4 shrink-0 text-current"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
      />
    </svg>

    <!-- Ikon Bulan (Mode Gelap) saat tampilan dalam Mode Terang -->
    <svg
      v-else
      class="w-4 h-4 shrink-0 text-current"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
      />
    </svg>

    <span v-if="showLabel" class="font-medium">
      {{ isDark ? 'Mode Terang' : 'Mode Gelap' }}
    </span>
  </button>
</template>
