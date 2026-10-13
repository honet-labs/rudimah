<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: boolean;
    id?: string;
    label?: string;
    description?: string;
    disabled?: boolean;
  }>(),
  {
    modelValue: false,
    id: () => `rd-checkbox-${Math.random().toString(36).substring(2, 9)}`,
    disabled: false,
  }
);

defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
}>();
</script>

<template>
  <div class="flex items-start gap-2.5 text-left">
    <div class="flex items-center h-5">
      <input
        :id="id"
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        class="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700 disabled:opacity-50 cursor-pointer"
        @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
      />
    </div>
    <div v-if="label || description" class="text-xs">
      <label
        :for="id"
        class="font-medium text-slate-700 dark:text-slate-200 cursor-pointer"
      >
        {{ label }}
      </label>
      <p v-if="description" class="text-slate-500 dark:text-slate-400">
        {{ description }}
      </p>
    </div>
  </div>
</template>
