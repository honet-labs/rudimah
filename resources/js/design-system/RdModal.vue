<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

const props = withDefaults(
  defineProps<{
    modelValue: boolean;
    title?: string;
    description?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  }>(),
  {
    maxWidth: 'md',
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'close'): void;
}>();

function close() {
  emit('update:modelValue', false);
  emit('close');
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.modelValue) {
    close();
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
        @click="close"
      ></div>

      <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div
          :class="[
            'w-full transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 text-left shadow-xl transition-all',
            maxWidth === 'sm' ? 'max-w-sm' : maxWidth === 'lg' ? 'max-w-lg' : maxWidth === 'xl' ? 'max-w-xl' : maxWidth === '2xl' ? 'max-w-2xl' : 'max-w-md'
          ]"
        >
          <div v-if="title || $slots.header" class="mb-4 flex items-start justify-between">
            <div>
              <h3 v-if="title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ title }}
              </h3>
              <p v-if="description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ description }}
              </p>
              <slot name="header" />
            </div>
            <button
              type="button"
              class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors p-1"
              @click="close"
              aria-label="Tutup modal"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="mt-2">
            <slot />
          </div>

          <div v-if="$slots.footer" class="mt-6 flex justify-end gap-2 border-t border-slate-200 dark:border-slate-800 pt-4">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
