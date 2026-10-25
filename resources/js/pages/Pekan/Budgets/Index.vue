<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceBudget } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    budgets?: FinanceBudget[];
  }>(),
  {
    budgets: () => [
      {
        id: 'bg-1',
        name: 'Makan & Minum',
        category_name: 'Konsumsi Harian',
        amount_limit_minor: 3500000,
        spent_amount_minor: 2100000,
        progress_percent: 60,
        period: 'monthly',
        start_date: '2026-10-01',
        alert_threshold_pct: 80,
        status: 'active',
      },
      {
        id: 'bg-2',
        name: 'Tagihan Utilitas',
        category_name: 'Listrik & Air',
        amount_limit_minor: 1200000,
        spent_amount_minor: 950000,
        progress_percent: 79,
        period: 'monthly',
        start_date: '2026-10-01',
        alert_threshold_pct: 80,
        status: 'active',
      },
      {
        id: 'bg-3',
        name: 'Hiburan & Liburan',
        category_name: 'Rekreasi',
        amount_limit_minor: 1500000,
        spent_amount_minor: 1450000,
        progress_percent: 96.6,
        period: 'monthly',
        start_date: '2026-10-01',
        alert_threshold_pct: 80,
        status: 'exceeded',
      },
    ],
  }
);
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pengawasan Anggaran Belanja"
      description="Kendalikan laju pengeluaran keluarga dan pos operasional per kategori"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Anggaran' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/budgets/create`">
          <RdButton variant="primary" size="sm">
            Buat Anggaran Baru
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <RdCard
        v-for="b in budgets"
        :key="b.id"
        padding
      >
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              {{ b.name }}
            </h3>
            <span class="text-xs text-slate-500">
              Kategori: {{ b.category_name }}
            </span>
          </div>
          <RdBadge :variant="b.progress_percent >= 90 ? 'warning' : 'neutral'">
            {{ Math.round(b.progress_percent) }}%
          </RdBadge>
        </div>

        <!-- Progress Bar Netral -->
        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden mb-3">
          <div
            :class="[
              'h-full transition-all',
              b.progress_percent >= 100
                ? 'bg-red-700'
                : b.progress_percent >= 80
                  ? 'bg-amber-700'
                  : 'bg-teal-700'
            ]"
            :style="{ width: `${Math.min(b.progress_percent, 100)}%` }"
          ></div>
        </div>

        <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-4">
          <div>
            <span class="block text-slate-400 text-[11px]">Realisasi</span>
            <span class="font-semibold"><RdMoney :amount-minor="b.spent_amount_minor" /></span>
          </div>
          <div class="text-right">
            <span class="block text-slate-400 text-[11px]">Batas Plafon</span>
            <span class="font-semibold"><RdMoney :amount-minor="b.amount_limit_minor" /></span>
          </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between text-[11px] text-slate-400">
          <span>Periode: {{ b.period === 'monthly' ? 'Bulanan' : 'Mingguan' }}</span>
          <div class="flex items-center gap-2">
            <span v-if="b.progress_percent >= b.alert_threshold_pct" class="font-medium text-amber-800 dark:text-amber-400">
              Mendekati limit
            </span>
            <a :href="`/t/${tenant.slug}/apps/pekan/budgets/${b.id}/edit`" class="text-xs text-teal-700 dark:text-teal-400 hover:underline font-semibold ml-2">
              Ubah
            </a>
          </div>
        </div>
      </RdCard>
    </div>
  </RudimahAppShell>
</template>
