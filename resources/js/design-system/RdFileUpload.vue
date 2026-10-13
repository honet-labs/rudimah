<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(
  defineProps<{
    accept?: string;
    maxSizeMb?: number;
    label?: string;
    description?: string;
  }>(),
  {
    accept: 'image/*,.pdf',
    maxSizeMb: 10,
    label: 'Unggah Dokumen atau Kuitansi',
    description: 'Format yang didukung: JPG, PNG, atau PDF (maksimal 10MB)',
  }
);

const emit = defineEmits<{
  (e: 'file-selected', file: File): void;
}>();

const isDragging = ref(false);
const errorMessage = ref<string | null>(null);

function handleFiles(files: FileList | null) {
  if (!files || files.length === 0) return;
  const file = files[0];

  if (file.size > props.maxSizeMb * 1024 * 1024) {
    errorMessage.value = `Ukuran berkas melebihi batas maksimal ${props.maxSizeMb}MB`;
    return;
  }

  errorMessage.value = null;
  emit('file-selected', file);
}

function onDrop(e: DragEvent) {
  isDragging.value = false;
  handleFiles(e.dataTransfer?.files || null);
}

function onChange(e: Event) {
  const target = e.target as HTMLInputElement;
  handleFiles(target.files);
}
</script>

<template>
  <div class="w-full text-left">
    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
      {{ label }}
    </label>
    <div
      :class="[
        'border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors',
        isDragging
          ? 'border-teal-700 bg-teal-50/50 dark:bg-teal-950/20'
          : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 bg-slate-50 dark:bg-slate-800/40'
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="($refs.fileInput as HTMLInputElement).click()"
    >
      <input
        ref="fileInput"
        type="file"
        :accept="accept"
        class="hidden"
        @change="onChange"
      />
      <div class="flex flex-col items-center justify-center">
        <svg class="w-8 h-8 text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
          Klik untuk memilih berkas atau seret berkas ke area ini
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          {{ description }}
        </p>
      </div>
    </div>
    <p v-if="errorMessage" class="text-xs text-red-600 font-medium mt-1">
      {{ errorMessage }}
    </p>
  </div>
</template>
