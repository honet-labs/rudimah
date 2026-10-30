<script setup lang="ts">
import { ref, computed } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceBudget } from '../../../types/pekan';

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    budgets?: FinanceBudget[];
  }>(),
  {
    budgets: () => [],
  }
);

const activeTab = ref<'active' | 'history'>('active');

const activeBudgets = computed(() => {
  return (props.budgets || []).filter(b => !b.is_past);
});

const historyBudgets = computed(() => {
  return (props.budgets || []).filter(b => b.is_past);
});

const displayedBudgets = computed(() => {
  return activeTab.value === 'active' ? activeBudgets.value : historyBudgets.value;
});

const copiedId = ref<string | null>(null);
function copyId(id: string) {
  if (!id) return;
  navigator.clipboard.writeText(id);
  copiedId.value = id;
  setTimeout(() => {
    if (copiedId.value === id) copiedId.value = null;
  }, 2000);
}
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

    <!-- Bilah Tab Navigasi Anggaran (Aktif Berjalan vs Histori Periode Sebelumnya) -->
    <div class="border-b border-slate-200 dark:border-slate-800 mb-6 flex gap-2 overflow-x-auto pb-1">
      <button
        type="button"
        :class="[
          'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 shrink-0 cursor-pointer',
          activeTab === 'active'
            ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
            : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
        ]"
        @click="activeTab = 'active'"
      >
        <span>Anggaran Berjalan</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
          {{ activeBudgets.length }}
        </span>
      </button>

      <button
        type="button"
        :class="[
          'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 shrink-0 cursor-pointer',
          activeTab === 'history'
            ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
            : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
        ]"
        @click="activeTab = 'history'"
      >
        <span>Histori Anggaran</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
          {{ historyBudgets.length }}
        </span>
      </button>
    </div>

    <!-- Grid Kartu Anggaran -->
    <div v-if="displayedBudgets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <RdCard
        v-for="b in displayedBudgets"
        :key="b.id"
        padding
      >
        <div class="flex items-start justify-between mb-3">
          <div>
            <div class="flex items-center gap-1.5 mb-1">
              <button
                type="button"
                @click="copyId(b.id)"
                class="font-mono text-[11px] text-slate-500 hover:text-teal-700 dark:hover:text-teal-400 transition-colors select-all cursor-pointer inline-flex items-center gap-1 group text-left"
                :title="`Klik untuk menyalin ID Anggaran: ${b.id}`"
              >
                <span>#{{ b.id ? b.id.substring(0, 8) : '-' }}</span>
                <span v-if="copiedId === b.id" class="text-[10px] text-teal-600 font-sans font-semibold">Tersalin</span>
                <svg v-else class="w-3 h-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
              </button>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              {{ b.name }}
            </h3>
            <span class="text-xs text-slate-500">
              Kategori: {{ b.category_name || 'Umum' }}
            </span>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <RdBadge v-if="activeTab === 'history'" :variant="b.progress_percent >= 100 ? 'danger' : 'neutral'">
              {{ b.progress_percent >= 100 ? 'Over Budget' : 'Tuntas' }}
            </RdBadge>
            <RdBadge :variant="b.progress_percent >= 90 ? 'warning' : 'neutral'">
              {{ Math.round(b.progress_percent) }}%
            </RdBadge>
          </div>
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
          <span>Periode: {{ b.period_label || (b.period === 'monthly' ? 'Bulanan' : 'Mingguan') }}</span>
          <div class="flex items-center gap-2">
            <span v-if="b.progress_percent >= b.alert_threshold_pct && activeTab === 'active'" class="font-medium text-amber-800 dark:text-amber-400">
              Mendekati limit
            </span>
            <a
              v-if="activeTab === 'active'"
              :href="`/t/${tenant.slug}/apps/pekan/budgets/${b.id}/edit`"
              title="Ubah Anggaran"
              class="p-1.5 text-slate-500 hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center ml-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </a>
            <span v-else class="text-[11px] text-slate-400 font-medium">
              Periode Selesai
            </span>
          </div>
        </div>
      </RdCard>
    </div>

    <!-- Empty State -->
    <RdEmptyState
      v-else-if="activeTab === 'active'"
      title="Belum Ada Anggaran Berjalan"
      description="Buat pos anggaran baru untuk mengendalikan pengeluaran belanja pada periode bulan ini."
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/budgets/create`">
          <RdButton variant="primary" size="sm">
            + Buat Anggaran Baru
          </RdButton>
        </a>
      </template>
    </RdEmptyState>

    <RdEmptyState
      v-else
      title="Belum Ada Histori Anggaran"
      description="Anggaran belanja dari bulan-bulan atau periode sebelumnya yang telah berakhir akan otomatis terarsip di sini untuk dievaluasi."
    />
  </RudimahAppShell>
</template>
