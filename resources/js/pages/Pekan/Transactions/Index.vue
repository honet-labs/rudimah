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
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
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
const previewImage = ref<{ url: string; title: string; images?: any[] }>({ url: '', title: '' });

const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const transactionToDelete = ref<Transaction | null>(null);

function openPreview(url: string, title: string, images?: any[]) {
  previewImage.value = { url, title, images };
  previewModalOpen.value = true;
}

function confirmDelete(item: Transaction) {
  transactionToDelete.value = item;
  isDeleteDialogOpen.value = true;
}

function handleDelete() {
  if (!transactionToDelete.value) return;
  isDeleting.value = true;
  router.delete(`/t/${props.tenant.slug}/apps/pekan/transactions/${transactionToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      isDeleteDialogOpen.value = false;
      transactionToDelete.value = null;
    },
    onFinish: () => {
      isDeleting.value = false;
    },
  });
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
  { key: 'id', label: 'ID Transaksi', width: '130px' },
  { key: 'transaction_date', label: 'Tanggal Transaksi', width: '140px' },
  { key: 'merchant_name', label: 'Nama Merchant' },
  { key: 'category_name', label: 'Kategori', width: '120px' },
  { key: 'type', label: 'Jenis Transaksi', width: '130px' },
  { key: 'amount_minor', label: 'Nominal', align: 'right' as const, width: '130px' },
  { key: 'images', label: 'Lampiran', align: 'center' as const, width: '110px' },
  { key: 'actions', label: 'Aksi', width: '210px', align: 'center' as const },
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
      <template #id="{ item }">
        <span
          class="font-mono text-[11px] text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700/60 select-all inline-block max-w-[110px] truncate"
          :title="`ID Transaksi: ${item.id}`"
        >
          #{{ item.id ? item.id.substring(0, 8) : '-' }}
        </span>
      </template>

      <template #transaction_date="{ value }">
        <RdDateTime :value="value" format="date" />
      </template>

      <template #merchant_name="{ item }">
        <div class="space-y-0.5">
          <span class="text-xs font-semibold text-slate-800 dark:text-slate-100 block">
            {{ item.merchant_name || item.description || '-' }}
          </span>
          <span
            v-if="item.merchant_name && item.description && item.merchant_name !== item.description"
            class="text-[11px] text-slate-500 block truncate max-w-xs"
            :title="item.description"
          >
            {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : '' }}
          </span>
        </div>
      </template>

      <template #category_name="{ value }">
        <RdBadge variant="neutral">{{ value || 'Tanpa Kategori' }}</RdBadge>
      </template>

      <template #type="{ item }">
        <RdBadge :variant="item.type === 'income' ? 'success' : (item.type === 'savings' ? 'info' : 'neutral')">
          {{ item.type === 'income' ? 'Pemasukan' : (item.type === 'savings' ? 'Tabungan' : 'Pengeluaran') }}
        </RdBadge>
      </template>

      <template #amount_minor="{ item }">
        <span :class="item.type === 'income' ? 'text-teal-800 dark:text-teal-300 font-bold' : 'text-slate-800 dark:text-slate-100 font-semibold'">
          <RdMoney :amount-minor="item.amount_minor" :show-sign="item.type === 'income'" />
        </span>
      </template>

      <template #images="{ item }">
        <div class="flex items-center justify-center">
          <button
            v-if="item.images && item.images.length > 0"
            type="button"
            @click.stop="openPreview(item.images[0].url, `${item.merchant_name || item.description || 'Transaksi'} - Lampiran Struk`, item.images)"
            class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 hover:bg-teal-100 dark:hover:bg-teal-900/60 transition-colors"
            title="Klik untuk melihat berkas bukti/struk transaksi"
          >
            <svg class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>Lihat{{ item.images.length > 1 ? ` (${item.images.length})` : '' }}</span>
          </button>
          <span v-else class="text-xs text-slate-400">-</span>
        </div>
      </template>

      <template #actions="{ item }">
        <div class="flex items-center justify-center gap-1.5">
          <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}`">
            <RdButton variant="outline" size="sm" type="button">
              Rincian
            </RdButton>
          </a>
          <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}/edit`">
            <RdButton variant="outline" size="sm" type="button">
              Ubah
            </RdButton>
          </a>
          <RdButton variant="danger" size="sm" type="button" @click="confirmDelete(item)">
            Hapus
          </RdButton>
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
        <!-- Thumbnail galeri jika terdapat lebih dari 1 lampiran gambar -->
        <div v-if="previewImage.images && previewImage.images.length > 1" class="flex items-center gap-2 overflow-x-auto pb-1">
          <button
            v-for="(subImg, sIdx) in previewImage.images"
            :key="sIdx"
            type="button"
            @click="previewImage.url = subImg.url"
            :class="[
              'w-14 h-14 rounded border-2 overflow-hidden flex-shrink-0 transition-all',
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

    <!-- Dialog Konfirmasi Hapus Transaksi -->
    <RdConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Hapus Transaksi Ini?"
      :message="`Data mutasi transaksi '${transactionToDelete?.merchant_name || transactionToDelete?.description || 'Transaksi'}' akan dihapus dari buku kas. Tindakan ini tidak dapat dibatalkan.`"
      danger
      confirm-text="Ya, Hapus Transaksi"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </RudimahAppShell>
</template>
