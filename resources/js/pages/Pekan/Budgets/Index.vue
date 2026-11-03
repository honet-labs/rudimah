<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdModal from '../../../design-system/RdModal.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceBudget, Transaction } from '../../../types/pekan';
import { formatQuantity } from '../../../utils/format';

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

// Modal Rincian Daftar Transaksi Anggaran
const isTransactionsModalOpen = ref(false);
const isLoadingTransactions = ref(false);
const selectedBudgetForTransactions = ref<FinanceBudget | null>(null);
const budgetTransactions = ref<Transaction[]>([]);
const transactionsSearch = ref('');

// Lightbox Pratinjau Foto Struk Transaksi
const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string; images?: any[] }>({ url: '', title: '', images: [] });

function openPreview(url: string, title: string, images?: any[]) {
  previewImage.value = { url, title, images: images || [] };
  previewModalOpen.value = true;
}

// Modal Rincian Multi-Item Transaksi
const itemsModalOpen = ref(false);
const selectedTransactionForItems = ref<Transaction | null>(null);

function openItemsModal(tx: Transaction) {
  selectedTransactionForItems.value = tx;
  itemsModalOpen.value = true;
}

async function viewBudgetTransactions(budget: FinanceBudget) {
  selectedBudgetForTransactions.value = budget;
  isTransactionsModalOpen.value = true;
  isLoadingTransactions.value = true;
  transactionsSearch.value = '';
  budgetTransactions.value = [];

  try {
    const res = await fetch(`/t/${props.tenant.slug}/apps/pekan/budgets/${budget.id}/transactions`);
    if (res.ok) {
      const data = await res.json();
      if (data.success && Array.isArray(data.transactions)) {
        budgetTransactions.value = data.transactions;
        if (data.budget && data.budget.spent_amount_minor !== undefined) {
          budget.spent_amount_minor = data.budget.spent_amount_minor;
        }
      }
    }
  } catch (err) {
    console.error('Gagal mengambil daftar transaksi anggaran:', err);
  } finally {
    isLoadingTransactions.value = false;
  }
}

const filteredBudgetTransactions = computed(() => {
  if (!transactionsSearch.value.trim()) {
    return budgetTransactions.value;
  }
  const q = transactionsSearch.value.trim().toLowerCase();
  return budgetTransactions.value.filter((tx) => {
    return (
      (tx.description && tx.description.toLowerCase().includes(q)) ||
      (tx.merchant_name && tx.merchant_name.toLowerCase().includes(q)) ||
      (tx.category_name && tx.category_name.toLowerCase().includes(q)) ||
      (tx.account_name && tx.account_name.toLowerCase().includes(q)) ||
      (tx.payment_method && tx.payment_method.toLowerCase().includes(q)) ||
      (tx.id && tx.id.toLowerCase().includes(q))
    );
  });
});

const totalFilteredSpentMinor = computed(() => {
  return filteredBudgetTransactions.value.reduce((acc, tx) => acc + (tx.amount_minor || 0), 0);
});

// Kolom Desktop: Ramping, terpadu & proporsional agar 100% muat tanpa memicu scrollbar horizontal
const transactionColumns = [
  { key: 'transaction_date', label: 'Tanggal & ID', width: '130px' },
  { key: 'description', label: 'Transaksi & Merchant' },
  { key: 'category_name', label: 'Kategori', width: '120px' },
  { key: 'payment_method', label: 'Akun / Metode', width: '130px' },
  { key: 'amount_minor', label: 'Nominal', width: '130px', align: 'right' as const },
  { key: 'images', label: 'Struk', width: '75px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '60px', align: 'center' as const },
];
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

        <!-- Realisasi & Batas Plafon (Dapat diklik untuk melihat rincian transaksi) -->
        <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-3">
          <button
            type="button"
            @click="viewBudgetTransactions(b)"
            class="text-left group cursor-pointer focus:outline-none"
            title="Klik untuk melihat rincian transaksi"
          >
            <span class="block text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 text-[11px] transition-colors flex items-center gap-1">
              <span>Realisasi</span>
              <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </span>
            <span class="font-semibold text-slate-900 dark:text-white group-hover:text-teal-700 dark:group-hover:text-teal-300 transition-colors">
              <RdMoney :amount-minor="b.spent_amount_minor" />
            </span>
          </button>
          <div class="text-right">
            <span class="block text-slate-400 text-[11px]">Batas Plafon</span>
            <span class="font-semibold"><RdMoney :amount-minor="b.amount_limit_minor" /></span>
          </div>
        </div>

        <!-- Tombol Aksi Lihat Transaksi Cepat -->
        <div class="mb-3">
          <button
            type="button"
            @click="viewBudgetTransactions(b)"
            class="w-full py-1.5 px-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/60 dark:hover:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-teal-700 dark:hover:text-teal-400 flex items-center justify-between transition-colors cursor-pointer group"
          >
            <div class="flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-slate-500 group-hover:text-teal-600 dark:text-slate-400 dark:group-hover:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
              <span>Lihat Rincian Transaksi</span>
            </div>
            <svg class="w-3 h-3 text-slate-400 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
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
      description="Anggaran belanja yang telah diarsipkan atau telah melewati batas akhir berlaku akan tersimpan di sini untuk dievaluasi."
    />

    <!-- Modal Rincian Daftar Transaksi Anggaran (Responsif: Desktop Tabel Pas Tanpa Scroll, Mobile Kartu Vertikal) -->
    <RdModal
      v-model="isTransactionsModalOpen"
      :title="selectedBudgetForTransactions ? `Daftar Transaksi: ${selectedBudgetForTransactions.name}` : 'Daftar Transaksi Anggaran'"
      max-width="5xl"
    >
      <div v-if="selectedBudgetForTransactions" class="space-y-4">
        <!-- Panel Ringkasan Anggaran (Responsif 2 Kolom di Mobile, 4 Kolom di Desktop) -->
        <div class="p-3 sm:p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
              <span class="block text-slate-400 text-[11px] mb-0.5">Kategori Anggaran</span>
              <span class="font-semibold text-slate-800 dark:text-slate-100 truncate block">
                {{ selectedBudgetForTransactions.category_name || 'Semua Kategori' }}
              </span>
            </div>
            <div>
              <span class="block text-slate-400 text-[11px] mb-0.5">Periode Evaluasi</span>
              <span class="font-semibold text-slate-800 dark:text-slate-100 truncate block">
                {{ selectedBudgetForTransactions.period_label || 'Bulanan' }}
              </span>
            </div>
            <div>
              <span class="block text-slate-400 text-[11px] mb-0.5">Batas Plafon</span>
              <span class="font-semibold text-slate-800 dark:text-slate-100 block">
                <RdMoney :amount-minor="selectedBudgetForTransactions.amount_limit_minor" />
              </span>
            </div>
            <div>
              <span class="block text-slate-400 text-[11px] mb-0.5">Total Realisasi</span>
              <span class="font-bold text-teal-800 dark:text-teal-300 block">
                <RdMoney :amount-minor="selectedBudgetForTransactions.spent_amount_minor" />
              </span>
            </div>
          </div>

          <!-- Progress Bar & Sisa Plafon -->
          <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-slate-500">Penggunaan:</span>
              <RdBadge :variant="selectedBudgetForTransactions.progress_percent >= 100 ? 'danger' : (selectedBudgetForTransactions.progress_percent >= 80 ? 'warning' : 'neutral')">
                {{ Math.round(selectedBudgetForTransactions.progress_percent) }}%
              </RdBadge>
            </div>
            <div class="text-slate-600 dark:text-slate-300 text-xs">
              <span class="text-slate-400">Sisa Plafon: </span>
              <span
                class="font-semibold"
                :class="selectedBudgetForTransactions.spent_amount_minor > selectedBudgetForTransactions.amount_limit_minor ? 'text-red-700 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'"
              >
                <RdMoney :amount-minor="Math.max(0, selectedBudgetForTransactions.amount_limit_minor - selectedBudgetForTransactions.spent_amount_minor)" />
              </span>
            </div>
          </div>
        </div>

        <!-- Filter Pencarian Transaksi di Modal jika ada lebih dari 3 transaksi -->
        <div v-if="budgetTransactions.length > 3" class="flex items-center gap-2">
          <RdInput
            v-model="transactionsSearch"
            placeholder="Cari transaksi berdasarkan catatan, merchant, atau metode..."
            class="w-full text-xs"
          />
        </div>

        <!-- 1. Tampilan Desktop (Tabel Fit Penuh Tanpa Scrollbar Horizontal) -->
        <div class="hidden md:block overflow-hidden">
          <RdTable
            :columns="transactionColumns"
            :items="filteredBudgetTransactions"
            :loading="isLoadingTransactions"
            empty-text="Belum ada transaksi pengeluaran yang terhubung pada periode anggaran ini."
          >
            <!-- Kolom 1: Tanggal & ID Ringkas -->
            <template #transaction_date="{ item, value }">
              <div class="space-y-0.5">
                <div class="text-xs font-medium text-slate-800 dark:text-slate-200">
                  <RdDateTime :value="value" format="date" />
                </div>
                <code
                  class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono text-[10px] select-all cursor-default block w-fit"
                  :title="item.id"
                >
                  #{{ item.id && item.id.length > 8 ? item.id.substring(0, 8) : item.id }}
                </code>
              </div>
            </template>

            <!-- Kolom 2: Transaksi & Merchant Terpadu -->
            <template #description="{ item }">
              <div class="space-y-0.5 max-w-[280px]">
                <span class="font-semibold text-slate-800 dark:text-slate-100 text-xs block truncate" :title="item.description || '-'">
                  {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : '-' }}
                </span>
                <div v-if="item.merchant_name && item.merchant_name !== item.description" class="text-[11px] text-slate-500 truncate" :title="item.merchant_name">
                  {{ item.merchant_name }}
                </div>
                <div v-if="item.items && item.items.length > 0" class="text-[11px] text-teal-700 dark:text-teal-400 font-medium">
                  <button
                    type="button"
                    @click.stop="openItemsModal(item)"
                    class="inline-flex items-center gap-1 hover:underline cursor-pointer"
                    title="Klik untuk melihat rincian item belanja"
                  >
                    <span>{{ item.items.length }} item belanja</span>
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>
              </div>
            </template>

            <!-- Kolom 3: Kategori -->
            <template #category_name="{ value }">
              <span class="text-xs text-slate-700 dark:text-slate-300">
                {{ value || 'Tanpa Kategori' }}
              </span>
            </template>

            <!-- Kolom 4: Akun / Metode Pembayaran -->
            <template #payment_method="{ item }">
              <span class="text-xs font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                {{ item.account?.name || item.payment_method || 'Kas Utama' }}
              </span>
            </template>

            <!-- Kolom 5: Nominal Pengeluaran -->
            <template #amount_minor="{ item }">
              <span class="font-bold text-slate-900 dark:text-slate-100 text-xs whitespace-nowrap">
                <RdMoney :amount-minor="item.amount_minor" />
              </span>
            </template>

            <!-- Kolom 6: Lampiran Struk -->
            <template #images="{ item }">
              <div v-if="item.images && item.images.length > 0" class="flex items-center justify-center">
                <button
                  type="button"
                  @click.stop="openPreview(item.images[0].url, `${item.merchant_name || item.description || 'Transaksi'} - Lampiran Struk`, item.images)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-700 dark:text-teal-300 transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
                  title="Lihat berkas lampiran struk"
                >
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <span>{{ item.images.length }}</span>
                </button>
              </div>
              <span v-else class="text-xs text-slate-400">-</span>
            </template>

            <!-- Kolom 7: Aksi Detail -->
            <template #actions="{ item }">
              <div class="flex items-center justify-center">
                <a
                  :href="`/t/${tenant.slug}/apps/pekan/transactions/${item.id}`"
                  target="_blank"
                  title="Buka detail mutasi transaksi di tab baru"
                  class="p-1 text-slate-500 hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded transition-colors inline-flex items-center justify-center cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            </template>
          </RdTable>
        </div>

        <!-- 2. Tampilan Mobile & Tablet Kecil (Daftar Kartu Responsif Vertikal) -->
        <div class="block md:hidden">
          <!-- Loading State Mobile -->
          <div v-if="isLoadingTransactions" class="py-8 text-center text-slate-500 dark:text-slate-400 text-xs">
            <div class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-4 w-4 text-teal-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Sedang memuat data transaksi...</span>
            </div>
          </div>

          <!-- Empty State Mobile -->
          <div
            v-else-if="filteredBudgetTransactions.length === 0"
            class="py-8 px-4 text-center text-slate-500 dark:text-slate-400 text-xs border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-800/30"
          >
            Belum ada transaksi pengeluaran yang terhubung pada periode anggaran ini.
          </div>

          <!-- Daftar Kartu Transaksi Mobile -->
          <div v-else class="space-y-3 max-h-[60vh] overflow-y-auto pr-0.5">
            <div
              v-for="item in filteredBudgetTransactions"
              :key="item.id"
              class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-2.5"
            >
              <!-- Baris 1: Tanggal, Metode, Nominal -->
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap text-xs text-slate-500">
                  <RdDateTime :value="item.transaction_date" format="date" />
                  <span>·</span>
                  <span class="font-medium text-slate-700 dark:text-slate-300">
                    {{ item.account?.name || item.payment_method || 'Kas Utama' }}
                  </span>
                </div>
                <div class="text-right shrink-0">
                  <span class="text-sm font-bold text-slate-900 dark:text-slate-100">
                    <RdMoney :amount-minor="item.amount_minor" />
                  </span>
                </div>
              </div>

              <!-- Baris 2: Deskripsi & Merchant -->
              <div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 leading-snug">
                  {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : (item.merchant_name || 'Pengeluaran') }}
                </h4>
                <div v-if="item.merchant_name && item.merchant_name !== item.description" class="text-[11px] text-slate-500 mt-0.5">
                  {{ item.merchant_name }}
                </div>
              </div>

              <!-- Baris 3: ID, Kategori, & Multi-item -->
              <div class="flex items-center gap-1.5 flex-wrap">
                <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono text-[10px]">
                  #{{ item.id && item.id.length > 8 ? item.id.substring(0, 8) : item.id }}
                </code>
                <span v-if="item.category_name" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                  {{ item.category_name }}
                </span>
                <button
                  v-if="item.items && item.items.length > 0"
                  type="button"
                  @click.stop="openItemsModal(item)"
                  class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 border border-teal-200/80 dark:border-teal-800 cursor-pointer"
                >
                  {{ item.items.length }} item barang
                </button>
              </div>

              <!-- Baris 4: Footer Kartu (Struk & Link Transaksi) -->
              <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/80">
                <div>
                  <button
                    v-if="item.images && item.images.length > 0"
                    type="button"
                    @click.stop="openPreview(item.images[0].url, `${item.merchant_name || item.description || 'Transaksi'} - Lampiran Struk`, item.images)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-700 dark:text-teal-300 transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Struk ({{ item.images.length }})</span>
                  </button>
                  <span v-else class="text-[11px] text-slate-400">Tanpa lampiran</span>
                </div>

                <a
                  :href="`/t/${tenant.slug}/apps/pekan/transactions/${item.id}`"
                  target="_blank"
                  class="inline-flex items-center gap-1 text-xs font-medium text-teal-700 dark:text-teal-400 hover:underline cursor-pointer"
                >
                  <span>Detail</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="w-full flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3">
          <div class="text-xs text-slate-500">
            <span v-if="filteredBudgetTransactions.length > 0">
              Total: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ filteredBudgetTransactions.length }} transaksi</span> (<RdMoney :amount-minor="totalFilteredSpentMinor" />)
            </span>
          </div>
          <div class="flex items-center gap-2 justify-end">
            <a
              v-if="selectedBudgetForTransactions"
              :href="`/t/${tenant.slug}/apps/pekan/transactions?search=${encodeURIComponent(selectedBudgetForTransactions.category_name || selectedBudgetForTransactions.name)}`"
              class="inline-flex w-full sm:w-auto"
            >
              <RdButton variant="outline" size="sm" class="w-full sm:w-auto justify-center">
                Buka di Menu Transaksi
              </RdButton>
            </a>
            <RdButton variant="secondary" size="sm" @click="isTransactionsModalOpen = false" class="w-full sm:w-auto justify-center">
              Tutup
            </RdButton>
          </div>
        </div>
      </template>
    </RdModal>

    <!-- Modal Rincian Item Transaksi Multi-Item -->
    <RdModal
      v-model="itemsModalOpen"
      :title="`Rincian Item: ${selectedTransactionForItems?.merchant_name || selectedTransactionForItems?.description || 'Transaksi'}`"
      max-width="2xl"
    >
      <div v-if="selectedTransactionForItems" class="space-y-4">
        <!-- Header Ringkasan Transaksi -->
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
          <div>
            <span class="block text-[11px] text-slate-500 font-medium">Nama Transaksi</span>
            <span class="font-semibold text-slate-800 dark:text-slate-100 truncate block mt-0.5" :title="selectedTransactionForItems.description">
              {{ selectedTransactionForItems.description || '-' }}
            </span>
          </div>
          <div>
            <span class="block text-[11px] text-slate-500 font-medium">Nama Merchant</span>
            <span class="font-semibold text-slate-800 dark:text-slate-100 truncate block mt-0.5" :title="selectedTransactionForItems.merchant_name">
              {{ selectedTransactionForItems.merchant_name || '-' }}
            </span>
          </div>
          <div>
            <span class="block text-[11px] text-slate-500 font-medium">Tanggal Transaksi</span>
            <span class="font-semibold text-slate-800 dark:text-slate-100 block mt-0.5">
              <RdDateTime :value="selectedTransactionForItems.transaction_date" format="date" />
            </span>
          </div>
          <div>
            <span class="block text-[11px] text-slate-500 font-medium">Total Nilai</span>
            <span class="font-bold text-teal-800 dark:text-teal-300 block mt-0.5">
              <RdMoney :amount-minor="selectedTransactionForItems.amount_minor" />
            </span>
          </div>
        </div>

        <!-- Tabel Item Belanja -->
        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold uppercase">
              <tr>
                <th class="px-3 py-2">Nama Barang / Menu</th>
                <th class="px-3 py-2 text-center w-16">Qty</th>
                <th class="px-3 py-2 text-right w-24">Harga</th>
                <th class="px-3 py-2 text-right w-24">Diskon</th>
                <th class="px-3 py-2 text-right w-28">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
              <tr v-for="(it, idx) in selectedTransactionForItems.items" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                <td class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-200">{{ it.item_name }}</td>
                <td class="px-3 py-2.5 text-center font-mono">{{ formatQuantity(it.quantity) }}</td>
                <td class="px-3 py-2.5 text-right"><RdMoney :amount-minor="it.price_minor || 0" /></td>
                <td class="px-3 py-2.5 text-right text-slate-500"><RdMoney :amount-minor="it.discount_minor || 0" /></td>
                <td class="px-3 py-2.5 text-right font-bold text-slate-900 dark:text-slate-100">
                  <RdMoney :amount-minor="it.total_minor || ((it.price_minor || 0) * (Number(it.quantity) || 1))" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </RdModal>

    <!-- Modal Lightbox Pratinjau Foto Struk Transaksi -->
    <RdModal
      v-model="previewModalOpen"
      :title="previewImage.title"
      max-width="lg"
    >
      <div class="space-y-4">
        <div class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-black/5 dark:bg-black/30 flex items-center justify-center min-h-[260px]">
          <img
            :src="previewImage.url"
            :alt="previewImage.title"
            class="max-h-[70vh] w-auto max-w-full object-contain mx-auto rounded"
          />
        </div>
        <!-- Thumbnail galeri jika terdapat lebih dari 1 lampiran gambar -->
        <div v-if="previewImage.images && previewImage.images.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1">
          <button
            v-for="(subImg, sIdx) in previewImage.images"
            :key="sIdx"
            type="button"
            @click="previewImage.url = subImg.url"
            :class="[
              'w-14 h-14 rounded border-2 overflow-hidden shrink-0 transition-all cursor-pointer',
              previewImage.url === subImg.url ? 'border-teal-600 scale-105' : 'border-slate-200 dark:border-slate-700 opacity-60 hover:opacity-100'
            ]"
            :title="subImg.name || `Lampiran ${sIdx + 1}`"
          >
            <img :src="subImg.url" :alt="subImg.name || `Gambar ${sIdx + 1}`" class="w-full h-full object-cover" />
          </button>
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
          <span>Bukti Foto Struk</span>
          <a
            :href="previewImage.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 text-teal-600 hover:text-teal-700 dark:text-teal-400 font-medium hover:underline"
          >
            <span>Buka Gambar Penuh</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>
      </div>
    </RdModal>

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

