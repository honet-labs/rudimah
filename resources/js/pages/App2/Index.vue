<script setup lang="ts">
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import type { User, Tenant } from '../../types/core';

defineProps<{
  user: User;
  tenant: Tenant;
}>();
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="app2" app-name="Inventaris Rumah">
    <RdPageHeader
      title="Modul Inventaris Rumah (App2)"
      description="Modul skeleton terdaftar dalam katalog RUDIMAH"
      :breadcrumbs="[{ label: 'Beranda', href: '/' }, { label: 'Inventaris Rumah' }]"
    />

    <RdCard padding>
      <div class="text-center py-8">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
          Modul Dalam Tahap Penyusunan
        </h3>
        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
          Aplikasi ini telah terdaftar secara resmi di dalam arsitektur multi-tenant RUDIMAH dan siap menerima spesifikasi fungsional lanjutan.
        </p>
      </div>
    </RdCard>
  </RudimahAppShell>
</template>
