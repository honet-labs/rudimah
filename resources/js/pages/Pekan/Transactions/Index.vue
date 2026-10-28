<script setup lang="ts">
import { ref, computed } from 'vue';
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
import RdModal from '../../../design-system/RdModal.vue';
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
const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string }>({ url: '', title: '' });

function openPreview(url: string, title: string) {
  previewImage.value = { url, title };
  previewModalOpen.value = true;
}

const tenantSlug = computed(() => props.tenant?.slug || (props.tenant as any)?.code || '');

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
      <template #description="{ item }">
        <div class="space-y-1">
          <span class="text-xs text-slate-800 dark:text-slate-200 block">
            {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : '-' }}
          </span>
          <div v-if="item.images && item.images.length > 0" class="flex items-center gap-1.5 flex-wrap">
            <button
              v-for="(img, idx) in item.images"
              :key="idx"
              type="button"
              @click.stop="openPreview(img.url, `${item.description || 'Transaksi'} - Struk ${item.images.length > 1 ? idx + 1 : ''}`)"
              class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors border border-slate-200 dark:border-slate-700"
              title="Klik untuk melihat bukti foto struk"
            >
              <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>Struk{{ item.images.length > 1 ? ' ' + (idx + 1) : '' }}</span>
            </button>
          </div>
        </div>
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
        <div class="flex items-center justify-center">
          <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}`" class="inline-block">
            <RdButton variant="outline" size="sm" type="button">
              Rincian
            </RdButton>
          </a>
        </div>
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
  </RudimahAppShell>
</template>
