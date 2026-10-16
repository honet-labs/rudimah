<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    value?: string | Date | null;
    format?: 'date' | 'datetime' | 'time';
  }>(),
  {
    value: null,
    format: 'date',
  }
);

const isoDatetime = computed(() => {
  if (!props.value) return undefined;
  if (typeof props.value === 'string') return props.value;
  try {
    return props.value.toISOString();
  } catch {
    return undefined;
  }
});

const formatted = computed(() => {
  if (!props.value) return '-';
  const d = new Date(props.value);
  if (isNaN(d.getTime())) return String(props.value);

  if (props.format === 'time') {
    return new Intl.DateTimeFormat('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(d);
  }

  if (props.format === 'datetime') {
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(d);
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(d);
});
</script>

<template>
  <time :datetime="isoDatetime">
    {{ formatted }}
  </time>
</template>
