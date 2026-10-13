<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue?: string | number;
    id?: string;
    label?: string;
    type?: string;
    placeholder?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
  }>(),
  {
    modelValue: '',
    id: () => `rd-input-${Math.random().toString(36).substring(2, 9)}`,
    type: 'text',
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
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
      :class="[
        'w-full px-3 py-2 text-sm rounded-md border transition-colors focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-teal-700 disabled:bg-slate-100 disabled:cursor-not-allowed dark:bg-slate-800 dark:text-slate-100',
        error
          ? 'border-red-600 focus:ring-red-600 focus:border-red-600'
          : 'border-slate-300 dark:border-slate-700',
      ]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="hint && !error" :id="`${id}-hint`" class="text-xs text-slate-500 dark:text-slate-400">
      {{ hint }}
    </p>
    <p v-if="error" :id="`${id}-error`" class="text-xs text-red-600 font-medium">
      {{ error }}
    </p>
  </div>
</template>
