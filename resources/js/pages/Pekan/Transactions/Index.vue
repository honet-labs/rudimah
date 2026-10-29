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
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
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
const previewImage = ref<{ url: string; title: string; images?: any[] }>({ url: '', title: '', images: [] });
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const transactionToDelete = ref<Transaction | null>(null);

const copiedId = ref<string | null>(null);
function copyId(id: string) {
  if (!id) return;
  navigator.clipboard.writeText(id);
  copiedId.value = id;
  setTimeout(() => {
    if (copiedId.value === id) copiedId.value = null;
  }, 2000);
}

function openPreview(url: string, title: string, images?: any[]) {
  previewImage.value = { url, title, images: images || [] };
  previewModalOpen.value = true;
}

function confirmDelete(tx: Transaction) {
  transactionToDelete.value = tx;
  isDeleteDialogOpen.value = true;
}

function handleDelete() {
  if (!transactionToDelete.value) return;
  isDeleting.value = true;
  router.delete(`/t/${tenantSlug.value}/apps/pekan/transactions/${transactionToDelete.value.id}`, {
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

const itemsModalOpen = ref(false);
const selectedTransactionForItems = ref<Transaction | null>(null);

function openItemsModal(tx: Transaction) {
  selectedTransactionForItems.value = tx;
  itemsModalOpen.value = true;
}

const typeOptions = [
  { label: 'Semua Jenis Mutasi', value: 'all' },
  { label: 'Pemasukan (Income)', value: 'income' },
  { label: 'Pengeluaran (Expense)', value: 'expense' },
  { label: 'Tabungan (Savings)', value: 'savings' },
];

const columns = [
  { key: 'id', label: 'ID Transaksi', width: '100px' },
  { key: 'transaction_date', label: 'Tanggal Transaksi', width: '130px' },
  { key: 'merchant_name', label: 'Nama Merchant', width: '150px' },
  { key: 'description', label: 'Nama Transaksi' },
  { key: 'category_name', label: 'Kategori', width: '120px' },
  { key: 'type', label: 'Jenis Transaksi', width: '120px' },
  { key: 'items', label: 'Item', width: '170px' },
  { key: 'quantity', label: 'Quantity', width: '80px', align: 'center' as const },
  { key: 'amount_minor', label: 'Nominal', width: '130px', align: 'right' as const },
  { key: 'images', label: 'Lampiran', width: '100px', align: 'center' as const },
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

    <!-- Konten Data Mutasi Transaksi -->
    <div v-if="transactions && transactions.length > 0">
      <!-- 1. Tampilan Layar Lebar & Desktop (Tabel Komprehensif 10 Kolom) -->
      <div class="hidden lg:block">
        <RdTable :columns="columns" :items="transactions">
          <template #id="{ item }">
            <button
              type="button"
              @click.stop="copyId(item.id)"
              class="font-mono text-[11px] text-slate-500 hover:text-teal-700 dark:hover:text-teal-400 transition-colors select-all cursor-pointer inline-flex items-center gap-1 group text-left"
              :title="`Klik untuk menyalin: ${item.id}`"
            >
              <span>#{{ item.id ? item.id.substring(0, 8) : '-' }}</span>
              <span v-if="copiedId === item.id" class="text-[10px] text-teal-600 font-sans font-semibold">Tersalin</span>
              <svg v-else class="w-3 h-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
          </template>

          <template #transaction_date="{ value }">
            <RdDateTime :value="value" format="date" />
          </template>

          <template #merchant_name="{ item }">
            <span class="text-slate-700 dark:text-slate-300 text-xs block truncate max-w-[150px]" :title="item.merchant_name || '-'">
              {{ item.merchant_name || '-' }}
            </span>
          </template>

          <template #description="{ item }">
            <span class="font-medium text-slate-800 dark:text-slate-100 text-xs block truncate max-w-[200px]" :title="item.description || '-'">
              {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : '-' }}
            </span>
          </template>

          <template #category_name="{ value }">
            <RdBadge variant="neutral">{{ value || 'Tanpa Kategori' }}</RdBadge>
          </template>

          <template #type="{ value }">
            <RdBadge :variant="value === 'income' ? 'success' : (value === 'savings' ? 'info' : 'neutral')">
              {{ value === 'income' ? 'Pemasukan' : (value === 'savings' ? 'Tabungan' : 'Pengeluaran') }}
            </RdBadge>
          </template>

          <template #items="{ item }">
            <div v-if="item.items && item.items.length > 0" class="text-xs space-y-0.5 max-w-[170px]">
              <span class="text-slate-800 dark:text-slate-200 block truncate" :title="item.items[0].item_name">
                {{ item.items[0].item_name }}
              </span>
              <button
                v-if="item.items.length > 1"
                type="button"
                @click.stop="openItemsModal(item)"
                class="text-[11px] text-teal-700 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-300 font-medium inline-flex items-center gap-1 hover:underline cursor-pointer group"
                title="Klik untuk melihat rincian seluruh item belanja"
              >
                <span>+{{ item.items.length - 1 }} item lainnya</span>
                <svg class="w-3 h-3 text-teal-600 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
            <span v-else class="text-xs text-slate-400 block truncate max-w-[170px]">
              -
            </span>
          </template>

          <template #quantity="{ item }">
            <span v-if="item.items && item.items.length > 0" class="text-xs font-mono font-medium text-slate-700 dark:text-slate-300">
              {{ item.items.reduce((acc: number, it: any) => acc + (Number(it.quantity) || 1), 0) }}
            </span>
            <span v-else class="text-xs text-slate-400 font-mono">1</span>
          </template>

          <template #amount_minor="{ item }">
            <span :class="item.type === 'income' ? 'text-teal-800 dark:text-teal-300 font-bold' : 'text-slate-800 dark:text-slate-100 font-semibold'">
              <RdMoney :amount-minor="item.amount_minor" :show-sign="item.type === 'income'" />
            </span>
          </template>

          <template #images="{ item }">
            <div v-if="item.images && item.images.length > 0" class="flex items-center justify-center">
              <button
                type="button"
                @click.stop="openPreview(item.images[0].url, `${item.merchant_name || item.description || 'Transaksi'} - Lampiran Struk`, item.images)"
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-700 dark:text-teal-300 transition-colors border border-slate-200 dark:border-slate-700 hover:border-teal-500 cursor-pointer"
                title="Klik untuk melihat berkas lampiran"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>Lihat{{ item.images.length > 1 ? ` (${item.images.length})` : '' }}</span>
              </button>
            </div>
            <span v-else class="text-xs text-slate-400">-</span>
          </template>

          <template #actions="{ item }">
            <div class="flex items-center justify-center gap-1.5 flex-nowrap">
              <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}`" title="Rincian Transaksi">
                <RdButton variant="outline" size="sm" type="button">
                  Rincian
                </RdButton>
              </a>
              <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}/edit`" title="Ubah Transaksi">
                <RdButton variant="outline" size="sm" type="button">
                  Ubah
                </RdButton>
              </a>
              <RdButton
                variant="danger"
                size="sm"
                type="button"
                title="Hapus Transaksi"
                @click.stop="confirmDelete(item)"
              >
                Hapus
              </RdButton>
            </div>
          </template>
        </RdTable>
      </div>

      <!-- 2. Tampilan Khusus Mobile & Tablet (Kartu Responsif Bebas Scroll Horizontal) -->
      <div class="lg:hidden space-y-3 w-full">
        <div
          v-for="item in transactions"
          :key="item.id"
          class="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3 w-full"
        >
          <!-- Baris 1: ID, Tanggal & Badge Jenis & Kategori -->
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 space-y-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  type="button"
                  @click.stop="copyId(item.id)"
                  class="font-mono text-[11px] text-slate-500 hover:text-teal-700 dark:hover:text-teal-400 select-all inline-flex items-center gap-1 cursor-pointer"
                  :title="item.id"
                >
                  <span>#{{ item.id ? item.id.substring(0, 8) : '-' }}</span>
                  <span v-if="copiedId === item.id" class="text-[10px] text-teal-600 font-sans font-semibold">Tersalin</span>
                </button>
                <span class="text-slate-300 dark:text-slate-700">·</span>
                <span class="text-xs text-slate-500 font-medium">
                  <RdDateTime :value="item.transaction_date" format="date" />
                </span>
              </div>
              <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">
                {{ item.description ? item.description.replace(/^Struk Belanja:\s*/i, '') : (item.merchant_name || '-') }}
              </h4>
              <div v-if="item.merchant_name && item.merchant_name !== item.description" class="text-xs text-slate-500 font-medium">
                {{ item.merchant_name }}
              </div>
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
              <RdBadge :variant="item.type === 'income' ? 'success' : (item.type === 'savings' ? 'info' : 'neutral')" class="text-[10px] py-0 px-1.5">
                {{ item.type === 'income' ? 'Pemasukan' : (item.type === 'savings' ? 'Tabungan' : 'Pengeluaran') }}
              </RdBadge>
              <RdBadge variant="neutral" class="text-[10px] py-0 px-1.5">
                {{ item.category_name || 'Tanpa Kategori' }}
              </RdBadge>
            </div>
          </div>

          <!-- Baris 2: Rincian Barang / Keterangan Belanja -->
          <div v-if="item.items && item.items.length > 0" class="p-2.5 rounded bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 text-xs space-y-1">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
              <span>Item Belanja ({{ item.items.reduce((acc: number, it: any) => acc + (Number(it.quantity) || 1), 0) }} pcs)</span>
              <button
                v-if="item.items.length > 1"
                type="button"
                @click.stop="openItemsModal(item)"
                class="text-[11px] text-teal-700 dark:text-teal-400 hover:underline font-semibold lowercase cursor-pointer inline-flex items-center gap-0.5"
              >
                <span>lihat semua ({{ item.items.length }})</span>
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
            <div
              v-for="(it, idx) in item.items.slice(0, 3)"
              :key="idx"
              class="flex justify-between items-center text-xs"
            >
              <span class="text-slate-700 dark:text-slate-300 truncate max-w-[200px]" :title="it.item_name">
                {{ it.item_name }} <span class="text-slate-400">×{{ it.quantity || 1 }}</span>
              </span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                <RdMoney :amount-minor="it.total_minor || ((it.price_minor || 0) * (it.quantity || 1))" />
              </span>
            </div>
            <div v-if="item.items.length > 3">
              <button
                type="button"
                @click.stop="openItemsModal(item)"
                class="w-full text-left text-[11px] text-teal-700 dark:text-teal-400 hover:text-teal-800 font-medium pt-1 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between cursor-pointer"
              >
                <span>+{{ item.items.length - 3 }} item lainnya</span>
                <span class="text-[10px] font-semibold">Buka Rincian →</span>
              </button>
            </div>
          </div>
          <div v-else-if="item.description && item.description !== item.merchant_name" class="text-xs text-slate-500 dark:text-slate-400">
            {{ item.description }}
          </div>

          <!-- Baris 3: Lampiran Bukti Foto & Nominal Akhir -->
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
            <div>
              <button
                v-if="item.images && item.images.length > 0"
                type="button"
                @click.stop="openPreview(item.images[0].url, `${item.merchant_name || item.description || 'Transaksi'} - Lampiran Struk`, item.images)"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-700 dark:text-teal-300 transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>Lihat Struk ({{ item.images.length }})</span>
              </button>
              <span v-else class="text-[11px] text-slate-400">Tanpa lampiran</span>
            </div>

            <div class="text-right">
              <span class="block text-[10px] text-slate-400 font-semibold uppercase">Total Mutasi</span>
              <span :class="item.type === 'income' ? 'text-teal-800 dark:text-teal-300 font-bold text-base' : 'text-slate-900 dark:text-slate-100 font-bold text-base'">
                <RdMoney :amount-minor="item.amount_minor" :show-sign="item.type === 'income'" />
              </span>
            </div>
          </div>

          <!-- Baris 4: Aksi Mobile (Rincian, Ubah, Hapus) -->
          <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}`" class="block w-full">
              <RdButton variant="outline" size="sm" type="button" class="w-full justify-center">
                Rincian
              </RdButton>
            </a>
            <a :href="`/t/${tenantSlug}/apps/pekan/transactions/${item.id}/edit`" class="block w-full">
              <RdButton variant="outline" size="sm" type="button" class="w-full justify-center">
                Ubah
              </RdButton>
            </a>
            <RdButton
              variant="danger"
              size="sm"
              type="button"
              class="w-full justify-center"
              @click.stop="confirmDelete(item)"
            >
              Hapus
            </RdButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State Jika Tidak Ada Transaksi -->
    <RdEmptyState
      v-else
      title="Belum Ada Transaksi"
      description="Tidak ditemukan riwayat mutasi transaksi sesuai dengan kriteria filter pencarian Anda."
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/transactions/create`">
          <RdButton variant="primary" size="sm">
            Tambah Transaksi Baru
          </RdButton>
        </a>
      </template>
    </RdEmptyState>

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

    <!-- Modal Rincian Item Transaksi Multi-Item -->
    <RdModal
      v-model="itemsModalOpen"
      :title="`Rincian Item Transaksi - #${selectedTransactionForItems?.id ? selectedTransactionForItems.id.substring(0, 8) : ''}`"
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

        <!-- Tabel Rincian Item -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-x-auto">
          <table class="w-full text-xs text-left min-w-[500px]">
            <thead class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold border-b border-slate-200 dark:border-slate-700">
              <tr>
                <th class="py-2 px-3 text-center w-10">#</th>
                <th class="py-2 px-3">Nama Barang / Item</th>
                <th class="py-2 px-3 text-center w-20">Qty</th>
                <th class="py-2 px-3 text-right w-28">Harga Satuan</th>
                <th class="py-2 px-3 text-right w-24">Diskon</th>
                <th class="py-2 px-3 text-right w-32">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr
                v-for="(it, idx) in selectedTransactionForItems.items || []"
                :key="idx"
                class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
              >
                <td class="py-2 px-3 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                <td class="py-2 px-3 font-medium text-slate-800 dark:text-slate-200">
                  {{ it.item_name }}
                  <span v-if="it.notes" class="block text-[11px] text-slate-400 font-normal">{{ it.notes }}</span>
                </td>
                <td class="py-2 px-3 text-center font-mono font-medium text-slate-700 dark:text-slate-300">
                  {{ it.quantity || 1 }}
                </td>
                <td class="py-2 px-3 text-right font-mono text-slate-700 dark:text-slate-300">
                  <RdMoney :amount-minor="it.price_minor || 0" />
                </td>
                <td class="py-2 px-3 text-right font-mono text-slate-500">
                  <RdMoney v-if="it.discount_minor" :amount-minor="it.discount_minor" />
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800 dark:text-slate-100">
                  <RdMoney :amount-minor="it.total_minor || ((it.price_minor || 0) * (it.quantity || 1))" />
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50 dark:bg-slate-800/70 border-t border-slate-200 dark:border-slate-700 font-semibold text-xs">
              <tr>
                <td colspan="2" class="py-2.5 px-3 text-slate-700 dark:text-slate-300">
                  Total ({{ selectedTransactionForItems.items?.length || 0 }} baris item)
                </td>
                <td class="py-2.5 px-3 text-center font-mono">
                  {{ selectedTransactionForItems.items?.reduce((acc: number, it: any) => acc + (Number(it.quantity) || 1), 0) || 0 }}
                </td>
                <td colspan="2"></td>
                <td class="py-2.5 px-3 text-right font-mono text-teal-800 dark:text-teal-300 font-bold">
                  <RdMoney :amount-minor="selectedTransactionForItems.amount_minor" />
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Tombol Tutup & Navigasi Rincian Penuh -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
          <a
            :href="`/t/${tenantSlug}/apps/pekan/transactions/${selectedTransactionForItems.id}`"
            class="text-xs text-teal-700 dark:text-teal-400 font-semibold hover:underline inline-flex items-center gap-1"
          >
            <span>Buka Rincian Lengkap & Faktur</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </a>
          <RdButton variant="secondary" size="sm" type="button" @click="itemsModalOpen = false">
            Tutup
          </RdButton>
        </div>
      </div>
    </RdModal>

    <!-- Dialog Konfirmasi Hapus Transaksi -->
    <RdConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Hapus Transaksi Ini?"
      :message="`Data mutasi transaksi '${transactionToDelete?.merchant_name || transactionToDelete?.description || 'ini'}' akan dihapus dari buku kas. Tindakan ini tidak dapat dibatalkan.`"
      danger
      confirm-text="Ya, Hapus Transaksi"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </RudimahAppShell>
</template>
