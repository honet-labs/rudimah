<script setup lang="ts">
import RdModal from './RdModal.vue';
import RdButton from './RdButton.vue';

withDefaults(
  defineProps<{
    modelValue: boolean;
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
    loading?: boolean;
  }>(),
  {
    title: 'Konfirmasi Tindakan',
    confirmText: 'Ya, Lanjutkan',
    cancelText: 'Batal',
    danger: false,
    loading: false,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

function handleConfirm() {
  emit('confirm');
}

function handleCancel() {
  emit('update:modelValue', false);
  emit('cancel');
}
</script>

<template>
  <RdModal
    :model-value="modelValue"
    :title="title"
    max-width="sm"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
      {{ message }}
    </p>

    <template #footer>
      <RdButton
        variant="outline"
        size="sm"
        :disabled="loading"
        @click="handleCancel"
      >
        {{ cancelText }}
      </RdButton>
      <RdButton
        :variant="danger ? 'danger' : 'primary'"
        size="sm"
        :loading="loading"
        @click="handleConfirm"
      >
        {{ confirmText }}
      </RdButton>
    </template>
  </RdModal>
</template>
