<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
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

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    transactions?: Transaction[];
    totalCount?: number;
    pagination?: PaginationMeta;
    filters?: {
      search?: string;
      type?: string;
    };
  }>(),
  {
    totalCount: 0,
    transactions: () => [],
  }
);

const searchQuery = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || 'all');

function applyFilter(page = 1) {
  router.get(
    `/t/${props.tenant.slug}/apps/pekan/transactions`,
    {
      search: searchQuery.value.trim() ? searchQuery.value.trim() : undefined,
      type: selectedType.value !== 'all' ? selectedType.value : undefined,
      page: page > 1 ? page : undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
    }
  );
}

function goToPage(page: number) {
  if (page < 1 || (props.pagination && page > props.pagination.last_page)) return;
  applyFilter(page);
}

const typeOptions = [
  { label: 'Semua Jenis Mutasi', value: 'all' },
  { label: 'Pemasukan (Income)', value: 'income' },
  { label: 'Pengeluaran (Expense)', value: 'expense' },
  { label: 'Tabungan (Savings)', value: 'savings' },
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
      <form @submit.prevent="applyFilter(1)" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <RdInput
          v-model="searchQuery"
          placeholder="Cari transaksi berdasarkan catatan..."
          @keyup.enter="applyFilter(1)"
        />
        <RdSelect
          v-model="selectedType"
          :options="typeOptions"
          @update:model-value="applyFilter(1)"
        />
        <div class="flex items-center gap-2">
          <RdButton type="submit" variant="secondary" size="md" class="w-full">
            Terapkan Filter
          </RdButton>
          <a :href="`/t/${tenant.slug}/apps/pekan/reports`">
            <RdButton type="button" variant="outline" size="md">
              Ekspor
            </RdButton>
          </a>
        </div>
      </form>
    </RdCard>

    <!-- Tabel Data Mutasi -->
    <RdTable :columns="columns" :items="transactions">
      <template #transaction_date="{ value }">
        <RdDateTime :value="value" format="date" />
      </template>
      <template #description="{ value }">
        <span class="text-xs text-slate-800 dark:text-slate-200">
          {{ value ? value.replace(/^Struk Belanja:\s*/i, '') : '-' }}
        </span>
      </template>
      <template #category_name="{ value }">
        <RdBadge variant="neutral">{{ value || 'Tanpa Kategori' }}</RdBadge>
      </template>
      <template #type="{ value }">
        <span class="text-xs uppercase font-medium text-slate-500">
          {{ value === 'income' ? 'Pemasukan' : value === 'expense' ? 'Pengeluaran' : value === 'savings' ? 'Tabungan' : 'Transfer' }}
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

    <!-- Kontrol Pagination Transaksi (Batas 20 per Halaman) -->
    <div
      v-if="pagination && pagination.total > 0"
      class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 px-2 py-3 border-t border-slate-200 dark:border-slate-800 text-xs"
    >
      <div class="text-slate-600 dark:text-slate-400">
        Menampilkan <span class="font-semibold text-slate-800 dark:text-slate-200">{{ pagination.from || 0 }}</span> - <span class="font-semibold text-slate-800 dark:text-slate-200">{{ pagination.to || 0 }}</span> dari <span class="font-semibold text-slate-800 dark:text-slate-200">{{ pagination.total }}</span> transaksi (Batas {{ pagination.per_page }} per halaman)
      </div>

      <div class="flex items-center gap-1.5">
        <RdButton
          variant="outline"
          size="sm"
          :disabled="pagination.current_page <= 1"
          @click="goToPage(pagination.current_page - 1)"
        >
          Sebelumnya
        </RdButton>

        <span class="px-3 py-1 font-semibold text-slate-700 dark:text-slate-300">
          Hal. {{ pagination.current_page }} dari {{ pagination.last_page || 1 }}
        </span>

        <RdButton
          variant="outline"
          size="sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="goToPage(pagination.current_page + 1)"
        >
          Selanjutnya
        </RdButton>
      </div>
    </div>
  </RudimahAppShell>
</template>
