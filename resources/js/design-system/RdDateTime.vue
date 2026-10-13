<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    value: string | Date;
    format?: 'date' | 'datetime' | 'time';
  }>(),
  {
    format: 'date',
  }
);

const formatted = computed(() => {
  if (!props.value) return '';
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
  <time :datetime="typeof value === 'string' ? value : value.toISOString()">
    {{ formatted }}
  </time>
</template>
