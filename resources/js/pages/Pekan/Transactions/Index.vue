<script setup lang="ts">
import { ref } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import type { User, Tenant } from '../../../types/core';
import type { Transaction } from '../../../types/pekan';

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    transactions?: Transaction[];
    totalCount?: number;
  }>(),
  {
    totalCount: 3,
    transactions: () => [
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
  }
);

const searchQuery = ref('');
const selectedType = ref('all');

const typeOptions = [
  { label: 'Semua Jenis Mutasi', value: 'all' },
  { label: 'Pemasukan (Income)', value: 'income' },
  { label: 'Pengeluaran (Expense)', value: 'expense' },
];

const columns = [
  { key: 'transaction_date', label: 'Tanggal', width: '130px' },
  { key: 'description', label: 'Keterangan' },
  { key: 'category_name', label: 'Kategori' },
  { key: 'type', label: 'Jenis', width: '110px' },
  { key: 'amount_minor', label: 'Nominal', align: 'right' as const },
  { key: 'actions', label: 'Aksi', width: '100px', align: 'center' as const },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Daftar Mutasi Transaksi"
      description="Pencatatan menyeluruh seluruh arus kas masuk, pengeluaran belanja, dan transfer internal"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Transaksi' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/receipt-scan`">
          <RdButton variant="outline" size="sm">
            Pindai Struk OCR
          </RdButton>
        </a>
        <a :href="`/t/${tenant.slug}/apps/pekan/transactions/create`">
          <RdButton variant="primary" size="sm">
            Tambah Transaksi
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Bilah Filter & Pencarian -->
    <RdCard padding class="mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <RdInput
          v-model="searchQuery"
          placeholder="Cari transaksi berdasarkan catatan..."
        />
        <RdSelect
          v-model="selectedType"
          :options="typeOptions"
        />
        <div class="flex items-center gap-2">
          <RdButton variant="secondary" size="md" class="w-full">
            Terapkan Filter
          </RdButton>
          <a :href="`/t/${tenant.slug}/apps/pekan/reports`">
            <RdButton variant="outline" size="md">
              Ekspor
            </RdButton>
          </a>
        </div>
      </div>
    </RdCard>

    <!-- Tabel Data Mutasi -->
    <RdTable :columns="columns" :items="transactions">
      <template #transaction_date="{ value }">
        <RdDateTime :value="value" format="date" />
      </template>
      <template #category_name="{ value }">
        <RdBadge variant="neutral">{{ value || 'Tanpa Kategori' }}</RdBadge>
      </template>
      <template #type="{ value }">
        <span class="text-xs uppercase font-medium text-slate-500">
          {{ value === 'income' ? 'Pemasukan' : value === 'expense' ? 'Pengeluaran' : 'Transfer' }}
        </span>
      </template>
      <template #amount_minor="{ item }">
        <span :class="item.type === 'income' ? 'text-teal-800 dark:text-teal-300 font-bold' : 'text-slate-800 dark:text-slate-100 font-semibold'">
          <RdMoney :amount-minor="item.amount_minor" :show-sign="item.type === 'income'" />
        </span>
      </template>
      <template #actions="{ item }">
        <a :href="`/t/${tenant.slug}/apps/pekan/transactions/${item.id}`" class="text-xs text-teal-700 hover:underline font-medium">
          Rincian
        </a>
      </template>
    </RdTable>
  </RudimahAppShell>
</template>
