<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
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

// Dialog Hapus Anggaran
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const budgetToDelete = ref<FinanceBudget | null>(null);

function confirmDelete(budget: FinanceBudget) {
  budgetToDelete.value = budget;
  isDeleteDialogOpen.value = true;
}

function handleDelete() {
  if (!budgetToDelete.value) return;
  isDeleting.value = true;
  router.delete(`/t/${props.tenant.slug}/apps/pekan/budgets/${budgetToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      isDeleteDialogOpen.value = false;
      budgetToDelete.value = null;
    },
    onFinish: () => {
      isDeleting.value = false;
    },
  });
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
              <code
                class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[10px] select-all cursor-default"
                :title="b.id"
              >
                #{{ b.id && b.id.length > 8 ? b.id.substring(0, 8) : b.id }}
              </code>
              <span class="text-xs text-slate-500">
                {{ b.category_name || 'Umum' }}
              </span>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              {{ b.name }}
            </h3>
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
          <div class="flex items-center gap-1.5">
            <span v-if="b.is_past" class="text-[11px] text-slate-400 font-medium mr-1">
              Periode Selesai
            </span>
            <span v-else-if="b.progress_percent >= b.alert_threshold_pct" class="font-medium text-amber-800 dark:text-amber-400 mr-1">
              Mendekati limit
            </span>

            <!-- Tombol Ubah Anggaran -->
            <a
              :href="`/t/${tenant.slug}/apps/pekan/budgets/${b.id}/edit`"
              title="Ubah Anggaran"
              class="p-1.5 text-slate-500 hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </a>

            <!-- Tombol Hapus Anggaran -->
            <button
              type="button"
              @click.stop="confirmDelete(b)"
              title="Hapus Anggaran"
              class="p-1.5 text-slate-500 hover:text-red-700 dark:text-slate-400 dark:hover:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
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

    <!-- Dialog Konfirmasi Hapus Anggaran -->
    <RdConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Hapus Pos Anggaran Belanja?"
      :message="`Pos anggaran '${budgetToDelete?.name || 'ini'}' akan dihapus secara permanen. Transaksi yang sebelumnya telah dialokasikan tidak akan terhapus dari buku kas.`"
      danger
      confirm-text="Ya, Hapus Anggaran"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </RudimahAppShell>
</template>
