<script setup lang="ts">
import { ref, onMounted } from 'vue';

const props = withDefaults(
  defineProps<{
    message: string;
    variant?: 'info' | 'success' | 'warning' | 'error';
    duration?: number;
  }>(),
  {
    variant: 'info',
    duration: 4000,
  }
);

const emit = defineEmits<{
  (e: 'dismiss'): void;
}>();

const visible = ref(true);

function dismiss() {
  visible.value = false;
  emit('dismiss');
}

onMounted(() => {
  if (props.duration > 0) {
    setTimeout(dismiss, props.duration);
  }
});
</script>

<template>
  <div
    v-if="visible"
    class="flex items-center justify-between gap-3 px-4 py-3 bg-slate-900 text-white rounded-md shadow-lg text-xs md:text-sm border border-slate-700 min-w-64 max-w-md transition-all"
    role="status"
    aria-live="polite"
  >
    <span>{{ message }}</span>
    <button
      type="button"
      class="text-slate-400 hover:text-white p-0.5 transition-colors"
      @click="dismiss"
      aria-label="Tutup pemberitahuan"
    >
      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
