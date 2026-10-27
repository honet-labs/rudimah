<script setup lang="ts">
import { ref, computed } from 'vue';

export interface ExistingImage {
  id: string;
  name: string;
  url: string;
  size?: number;
}

const props = withDefaults(
  defineProps<{
    modelValue?: File[];
    existingImages?: ExistingImage[];
    label?: string;
    description?: string;
    maxFiles?: number;
    maxSizeMb?: number;
  }>(),
  {
    modelValue: () => [],
    existingImages: () => [],
    label: 'Lampiran Gambar / Bukti Foto',
    description: 'Format yang didukung: JPG, PNG, WEBP (maks. 5MB per gambar, bisa pilih beberapa)',
    maxFiles: 8,
    maxSizeMb: 5,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', files: File[]): void;
  (e: 'update:existingImages', images: ExistingImage[]): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const errorMessage = ref<string | null>(null);
const previewModalUrl = ref<string | null>(null);
const previewModalTitle = ref<string | null>(null);

interface LocalPreview {
  file: File;
  previewUrl: string;
}

const localPreviews = ref<LocalPreview[]>([]);

function syncLocalPreviews(files: File[]) {
  // Revoke old object URLs to avoid memory leak
  localPreviews.value.forEach((p) => URL.revokeObjectURL(p.previewUrl));
  localPreviews.value = files.map((file) => ({
    file,
    previewUrl: URL.createObjectURL(file),
  }));
}

function handleIncomingFiles(fileList: FileList | null) {
  if (!fileList || fileList.length === 0) return;
  errorMessage.value = null;

  const currentCount = (props.existingImages?.length || 0) + (props.modelValue?.length || 0);
  const remainingSlots = props.maxFiles - currentCount;

  if (remainingSlots <= 0) {
    errorMessage.value = `Batas maksimum gambar (${props.maxFiles}) telah tercapai.`;
    return;
  }

  const validNewFiles: File[] = [];
  const maxBytes = props.maxSizeMb * 1024 * 1024;
  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  for (let i = 0; i < fileList.length; i++) {
    if (validNewFiles.length >= remainingSlots) {
      errorMessage.value = `Hanya ${props.maxFiles} gambar yang dapat dilampirkan. Sisa berkas diabaikan.`;
      break;
    }

    const file = fileList[i];
    if (!allowedTypes.includes(file.type)) {
      errorMessage.value = `Berkas "${file.name}" bukan format gambar yang didukung (JPG, PNG, WEBP).`;
      continue;
    }

    if (file.size > maxBytes) {
      errorMessage.value = `Ukuran berkas "${file.name}" melebihi batas ${props.maxSizeMb}MB.`;
      continue;
    }

    validNewFiles.push(file);
  }

  if (validNewFiles.length > 0) {
    const updated = [...(props.modelValue || []), ...validNewFiles];
    emit('update:modelValue', updated);
    syncLocalPreviews(updated);
  }

  if (fileInput.value) {
    fileInput.value.value = '';
  }
}

function removeNewFile(index: number) {
  const updated = [...(props.modelValue || [])];
  if (updated[index]) {
    updated.splice(index, 1);
    emit('update:modelValue', updated);
    syncLocalPreviews(updated);
  }
}

function removeExistingImage(index: number) {
  const updated = [...(props.existingImages || [])];
  if (updated[index]) {
    updated.splice(index, 1);
    emit('update:existingImages', updated);
  }
}

function onDrop(e: DragEvent) {
  isDragging.value = false;
  handleIncomingFiles(e.dataTransfer?.files || null);
}

function onChange(e: Event) {
  const target = e.target as HTMLInputElement;
  handleIncomingFiles(target.files);
}

function openPreview(url: string, title?: string) {
  previewModalUrl.value = url;
  previewModalTitle.value = title || 'Pratinjau Gambar';
}

function closePreview() {
  previewModalUrl.value = null;
  previewModalTitle.value = null;
}

const totalCount = computed(() => {
  return (props.existingImages?.length || 0) + (props.modelValue?.length || 0);
});

function formatFileSize(bytes?: number): string {
  if (!bytes) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

<template>
  <div class="w-full text-left space-y-2">
    <div class="flex items-center justify-between">
      <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
        {{ label }}
      </label>
      <span class="text-[11px] text-slate-500 font-mono">
        {{ totalCount }}/{{ maxFiles }} Foto
      </span>
    </div>

    <!-- Dropzone Upload Area -->
    <div
      v-if="totalCount < maxFiles"
      :class="[
        'border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-colors',
        isDragging
          ? 'border-teal-700 bg-teal-50/50 dark:bg-teal-950/20'
          : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 bg-slate-50 dark:bg-slate-800/40'
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="fileInput?.click()"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        class="hidden"
        @change="onChange"
      />
      <div class="flex flex-col items-center justify-center py-1">
        <svg class="w-7 h-7 text-slate-400 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
          Pilih atau seret beberapa foto gambar ke sini
        </p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
          {{ description }}
        </p>
      </div>
    </div>

    <p v-if="errorMessage" class="text-xs text-red-600 font-medium">
      {{ errorMessage }}
    </p>

    <!-- Gallery Grid of Uploaded Images -->
    <div v-if="totalCount > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
      <!-- Existing Saved Images -->
      <div
        v-for="(img, idx) in existingImages"
        :key="`existing-${img.id || idx}`"
        class="group relative border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 flex flex-col"
      >
        <div class="aspect-video w-full overflow-hidden bg-slate-200 dark:bg-slate-900 relative cursor-pointer" @click="openPreview(img.url, img.name)">
          <img
            :src="img.url"
            :alt="img.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
            loading="lazy"
          />
          <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <span class="p-1 bg-white/90 text-slate-800 rounded text-[11px] font-medium shadow-sm">
              Perbesar
            </span>
          </div>
        </div>

        <div class="p-1.5 flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300">
          <span class="truncate pr-1" :title="img.name">{{ img.name }}</span>
          <button
            type="button"
            class="text-slate-400 hover:text-red-600 p-0.5 rounded transition-colors"
            title="Hapus foto ini"
            @click.stop="removeExistingImage(idx)"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Newly Selected Images -->
      <div
        v-for="(item, idx) in localPreviews"
        :key="`new-${idx}`"
        class="group relative border border-teal-200 dark:border-teal-800/60 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 flex flex-col"
      >
        <div class="aspect-video w-full overflow-hidden bg-slate-200 dark:bg-slate-900 relative cursor-pointer" @click="openPreview(item.previewUrl, item.file.name)">
          <img
            :src="item.previewUrl"
            :alt="item.file.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
          />
          <div class="absolute top-1 left-1 bg-teal-800 text-white text-[9px] px-1.5 py-0.5 rounded font-medium">
            Baru
          </div>
          <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <span class="p-1 bg-white/90 text-slate-800 rounded text-[11px] font-medium shadow-sm">
              Perbesar
            </span>
          </div>
        </div>

        <div class="p-1.5 flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300">
          <span class="truncate pr-1" :title="item.file.name">
            {{ item.file.name }} ({{ formatFileSize(item.file.size) }})
          </span>
          <button
            type="button"
            class="text-slate-400 hover:text-red-600 p-0.5 rounded transition-colors"
            title="Hapus foto ini"
            @click.stop="removeNewFile(idx)"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal for Full Image Inspection -->
    <div
      v-if="previewModalUrl"
      class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm"
      @click.self="closePreview"
    >
      <div class="relative max-w-4xl max-h-[90vh] bg-slate-900 border border-slate-700 rounded-lg overflow-hidden shadow-2xl flex flex-col">
        <div class="flex items-center justify-between px-4 py-2.5 bg-slate-800/80 border-b border-slate-700">
          <span class="text-xs font-semibold text-slate-200 truncate">
            {{ previewModalTitle }}
          </span>
          <button
            type="button"
            class="text-slate-400 hover:text-white p-1 rounded transition-colors"
            @click="closePreview"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="p-2 overflow-auto flex items-center justify-center max-h-[80vh]">
          <img
            :src="previewModalUrl"
            :alt="previewModalTitle || 'Pratinjau'"
            class="max-w-full max-h-[75vh] object-contain rounded"
          />
        </div>
      </div>
    </div>
  </div>
</template>
