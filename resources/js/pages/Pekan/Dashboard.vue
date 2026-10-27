<script setup lang="ts">
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdMoney from '../../design-system/RdMoney.vue';
import RdDateTime from '../../design-system/RdDateTime.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import RdInfoTooltip from '../../design-system/RdInfoTooltip.vue';
import type { User, Tenant } from '../../types/core';
import type { Transaction, FinanceBudget, FinanceSavings } from '../../types/pekan';

withDefaults(
  defineProps<{
    user?: User;
    tenant: Tenant;
    tenants?: Tenant[];
    apps?: any[];
    summary?: {
      incomeMinor: number;
      expenseMinor: number;
      netMinor: number;
      savingsMinor: number;
    };
    recentTransactions?: Transaction[];
    activeBudgets?: FinanceBudget[];
    activeSavings?: FinanceSavings[];
  }>(),
  {
    tenants: () => [],
    apps: () => [],
    summary: () => ({
      incomeMinor: 0,
      expenseMinor: 0,
      netMinor: 0,
      savingsMinor: 0,
    }),
    recentTransactions: () => [
      {
        id: 'tx-001',
        category_name: 'Gaji Pokok',
        type: 'income',
        amount_minor: 12000000,
        currency: 'Rp',
        transaction_date: '2026-10-10',
        description: 'Penerimaan gaji bulanan',
        created_at: '2026-10-10T08:00:00Z',
      },
      {
        id: 'tx-002',
        category_name: 'Belanja Dapur',
        type: 'expense',
        amount_minor: 450000,
        currency: 'Rp',
        transaction_date: '2026-10-12',
        description: 'Belanja bahan mingguan di supermarket',
        created_at: '2026-10-12T14:30:00Z',
      },
      {
        id: 'tx-003',
        category_name: 'Transportasi',
        type: 'expense',
        amount_minor: 85000,
        currency: 'Rp',
        transaction_date: '2026-10-13',
        description: 'Bahan bakar kendaraan',
        created_at: '2026-10-13T09:15:00Z',
      },
    ],
    activeBudgets: () => [
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
    ],
    activeSavings: () => [
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
    ],
  }
);

const txColumns = [
  { key: 'transaction_date', label: 'Tanggal', width: '130px' },
  { key: 'description', label: 'Keterangan' },
  { key: 'category_name', label: 'Kategori' },
  { key: 'amount_minor', label: 'Nominal', align: 'right' as const },
];
</script>

<template>
  <RudimahAppShell
    :user="user"
    :tenant="tenant"
    :tenants="tenants"
    :apps="apps"
    app-key="pekan"
    app-name="PEKAN"
  >
    <RdPageHeader
      title="Dasbor Keuangan"
      description="Ringkasan arus kas, anggaran belanja, dan capaian tabungan bulan berjalan"
      :breadcrumbs="[{ label: 'Ruang Kerja', href: '/workspaces' }, { label: 'PEKAN', href: `/t/${tenant?.slug || ''}/apps/pekan/dashboard` }, { label: 'Dasbor' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant?.slug || ''}/apps/pekan/receipt-scan`">
          <RdButton variant="outline" size="sm">
            Pindai Struk OCR
          </RdButton>
        </a>
        <a :href="`/t/${tenant?.slug || ''}/apps/pekan/transactions/create`">
          <RdButton variant="primary" size="sm">
            Tambah Transaksi
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Kartu Metrik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <RdCard padding>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Pemasukan Bulan Ini</span>
          <RdInfoTooltip text="Total seluruh mutasi kas masuk bulan berjalan" size="sm" title="Pemasukan" />
        </div>
        <div class="text-lg md:text-xl font-bold text-slate-900 dark:text-white mt-1">
          <RdMoney :amount-minor="summary.incomeMinor" />
        </div>
      </RdCard>

      <RdCard padding>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Pengeluaran Bulan Ini</span>
          <RdInfoTooltip text="Total mutasi belanja dan biaya bulan berjalan" size="sm" title="Pengeluaran" />
        </div>
        <div class="text-lg md:text-xl font-bold text-slate-900 dark:text-white mt-1">
          <RdMoney :amount-minor="summary.expenseMinor" />
        </div>
      </RdCard>

      <RdCard padding>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Saldo Kas Bersih</span>
          <RdInfoTooltip text="Surplus atau selisih antara pemasukan dan pengeluaran bulan berjalan" size="sm" title="Saldo Kas" />
        </div>
        <div class="text-lg md:text-xl font-bold text-slate-900 dark:text-white mt-1">
          <RdMoney :amount-minor="summary.netMinor" />
        </div>
      </RdCard>

      <RdCard padding>
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Akumulasi Tabungan</span>
          <RdInfoTooltip text="Total akumulasi saldo dari seluruh pos simpanan aktif" size="sm" title="Tabungan" />
        </div>
        <div class="text-lg md:text-xl font-bold text-slate-900 dark:text-white mt-1">
          <RdMoney :amount-minor="summary.savingsMinor" />
        </div>
      </RdCard>
    </div>

    <!-- Grid Tengah: Transaksi Terkini & Status Anggaran -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- Tabel Transaksi Terkini (2 Kolom) -->
      <div class="lg:col-span-2">
        <RdCard title="Transaksi Terkini" description="5 transaksi terbaru yang dicatat dalam ruang kerja ini">
          <template #actions>
            <a :href="`/t/${tenant?.slug || ''}/apps/pekan/transactions`" class="text-xs text-teal-700 dark:text-teal-400 font-semibold hover:underline">
              Lihat Semua
            </a>
          </template>
          <RdTable :columns="txColumns" :items="recentTransactions">
            <template #transaction_date="{ value }">
              <RdDateTime :value="value" format="date" />
            </template>
            <template #category_name="{ value }">
              <RdBadge variant="neutral">{{ value }}</RdBadge>
            </template>
            <template #amount_minor="{ item }">
              <span :class="item.type === 'income' ? 'text-teal-800 dark:text-teal-300 font-bold' : 'text-slate-800 dark:text-slate-100 font-semibold'">
                <RdMoney :amount-minor="item.amount_minor" :show-sign="item.type === 'income'" />
              </span>
            </template>
          </RdTable>
        </RdCard>
      </div>

      <!-- Pemantauan Anggaran (1 Kolom) -->
      <div>
        <RdCard title="Pemantauan Anggaran" description="Alokasi batas pengeluaran kategori aktif">
          <template #actions>
            <a :href="`/t/${tenant?.slug || ''}/apps/pekan/budgets`" class="text-xs text-teal-700 dark:text-teal-400 font-semibold hover:underline">
              Kelola
            </a>
          </template>
          <div class="space-y-4">
            <div
              v-for="b in activeBudgets"
              :key="b.id"
              class="border-b border-slate-100 dark:border-slate-800 pb-3 last:border-b-0 last:pb-0 text-xs"
            >
              <div class="flex items-center justify-between font-semibold mb-1">
                <span>{{ b.name }}</span>
                <span>{{ Math.round(b.progress_percent || 0) }}%</span>
              </div>
              <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden mb-1.5">
                <div
                  class="h-full bg-teal-700 dark:bg-teal-500"
                  :style="{ width: `${Math.min(b.progress_percent || 0, 100)}%` }"
                ></div>
              </div>
              <div class="flex items-center justify-between text-slate-500 text-[11px]">
                <span>Terpakai: <RdMoney :amount-minor="b.spent_amount_minor" /></span>
                <span>Batas: <RdMoney :amount-minor="b.amount_limit_minor" /></span>
              </div>
            </div>
          </div>
        </RdCard>
      </div>
    </div>

    <!-- Baris Bawah: Impian & Tabungan -->
    <RdCard title="Pos Tabungan Aktif" description="Kemajuan akumulasi dana target impian keluarga">
      <template #actions>
        <a :href="`/t/${tenant?.slug || ''}/apps/pekan/savings`" class="text-xs text-teal-700 dark:text-teal-400 font-semibold hover:underline">
          Lihat Rincian
        </a>
      </template>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="s in activeSavings"
          :key="s.id"
          class="p-4 border border-slate-200 dark:border-slate-800 rounded-lg bg-slate-50 dark:bg-slate-800/40"
        >
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ s.name }}</h4>
            <RdBadge variant="neutral">{{ Math.round(s.progress_percent || 0) }}%</RdBadge>
          </div>
          <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden mb-2">
            <div class="bg-teal-700 h-full" :style="{ width: `${Math.min(s.progress_percent || 0, 100)}%` }"></div>
          </div>
          <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
            <span>Terkumpul: <RdMoney :amount-minor="s.current_amount_minor" /></span>
            <span>Target: <RdMoney :amount-minor="s.target_amount_minor" /></span>
          </div>
        </div>
      </div>
    </RdCard>
  </RudimahAppShell>
</template>
