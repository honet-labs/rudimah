<script setup lang="ts">
interface Option {
  label: string;
  value: string | number;
}

withDefaults(
  defineProps<{
    modelValue?: string | number;
    options: Option[];
    id?: string;
    label?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
  }>(),
  {
    modelValue: '',
    id: () => `rd-select-${Math.random().toString(36).substring(2, 9)}`,
    required: false,
    disabled: false,
  }
);

defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
  <div class="flex flex-col gap-1 w-full text-left">
    <label
      v-if="label"
      :for="id"
      class="text-xs font-semibold text-slate-700 dark:text-slate-300"
    >
      {{ label }}
      <span v-if="required" class="text-red-600" aria-hidden="true">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-invalid="!!error"
      :class="[
        'w-full px-3 py-2 text-sm rounded-md border transition-colors focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700 disabled:bg-slate-100 disabled:cursor-not-allowed dark:bg-slate-800 dark:text-slate-100 cursor-pointer',
        error
          ? 'border-red-600 focus:ring-red-600 focus:border-red-600'
          : 'border-slate-300 dark:border-slate-700',
      ]"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
      >
        {{ opt.label }}
      </option>
    </select>
    <p v-if="hint && !error" class="text-xs text-slate-500 dark:text-slate-400">
      {{ hint }}
    </p>
    <p v-if="error" class="text-xs text-red-600 font-medium">
      {{ error }}
    </p>
  </div>
</template>
