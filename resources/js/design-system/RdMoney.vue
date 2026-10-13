<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    amountMinor: number;
    currency?: string;
    showSign?: boolean;
    compact?: boolean;
  }>(),
  {
    currency: 'Rp',
    showSign: false,
    compact: false,
  }
);

const formatted = computed(() => {
  const value = Math.round(props.amountMinor);
  const isNegative = value < 0;
  const absValue = Math.abs(value);

  const formattedNum = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
  }).format(absValue);

  let prefix = '';
  if (isNegative) {
    prefix = '- ';
  } else if (props.showSign && value > 0) {
    prefix = '+ ';
  }

  return `${prefix}${props.currency} ${formattedNum}`;
});
</script>

<template>
  <span class="font-mono tracking-tight font-medium">
    {{ formatted }}
  </span>
</template>
