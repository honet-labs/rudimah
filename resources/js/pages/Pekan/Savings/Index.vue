<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceSavings } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    savings?: FinanceSavings[];
  }>(),
  {
    savings: () => [
      {
        id: 'sv-1',
        name: 'Dana Darurat 6 Bulan',
        target_amount_minor: 30000000,
        current_amount_minor: 18500000,
        progress_percent: 61.6,
        target_date: '2027-04-01',
        status: 'active',
      },
      {
        id: 'sv-2',
        name: 'Renovasi Rumah',
        target_amount_minor: 15000000,
        current_amount_minor: 6200000,
        progress_percent: 41.3,
        target_date: '2026-12-31',
        status: 'active',
      },
      {
        id: 'sv-3',
        name: 'Pendidikan Anak',
        target_amount_minor: 50000000,
        current_amount_minor: 12000000,
        progress_percent: 24.0,
        target_date: '2028-06-30',
        status: 'active',
      },
    ],
  }
);
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pos Tabungan & Impian Keuangan"
      description="Rencanakan akumulasi dana target untuk masa depan keluarga secara terukur"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Tabungan' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/savings/create`">
          <RdButton variant="primary" size="sm">
            Buat Pos Tabungan Baru
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <RdCard
        v-for="s in savings"
        :key="s.id"
        padding
      >
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">
            {{ s.name }}
          </h3>
          <RdBadge variant="neutral">
            {{ Math.round(s.progress_percent) }}%
          </RdBadge>
        </div>

        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden my-3">
          <div
            class="h-full bg-teal-700 transition-all"
            :style="{ width: `${s.progress_percent}%` }"
          ></div>
        </div>

        <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 mb-4">
          <div class="flex justify-between">
            <span class="text-slate-500">Terkumpul:</span>
            <span class="font-semibold"><RdMoney :amount-minor="s.current_amount_minor" /></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Target Total:</span>
            <span class="font-semibold"><RdMoney :amount-minor="s.target_amount_minor" /></span>
          </div>
          <div v-if="s.target_date" class="flex justify-between text-[11px] text-slate-400">
            <span>Target Waktu:</span>
            <span><RdDateTime :value="s.target_date" format="date" /></span>
          </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
          <span class="text-[11px] text-slate-400 uppercase font-medium">Status: Aktif</span>
          <a :href="`/t/${tenant.slug}/apps/pekan/savings/${s.id}`">
            <RdButton variant="outline" size="sm">
              Kelola Pos
            </RdButton>
          </a>
        </div>
      </RdCard>
    </div>
  </RudimahAppShell>
</template>
