<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
import RdModal from '../../../design-system/RdModal.vue';
import type { User, Tenant } from '../../../types/core';
import type { Transaction } from '../../../types/pekan';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  transaction: Transaction;
}>();

const isDeleteDialogOpen = ref(false);
const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string }>({ url: '', title: '' });

const copiedId = ref<string | null>(null);
function copyId(id: string) {
  if (!id) return;
  navigator.clipboard.writeText(id);
  copiedId.value = id;
  setTimeout(() => {
    if (copiedId.value === id) copiedId.value = null;
  }, 2000);
}

function openPreview(url: string, title: string) {
  previewImage.value = { url, title };
  previewModalOpen.value = true;
}

function handleDelete() {
  router.delete(`/t/${props.tenant.slug}/apps/pekan/transactions/${props.transaction.id}`);
}

const itemColumns = [
  { key: 'item_name', label: 'Nama Barang' },
  { key: 'quantity', label: 'Jumlah', width: '80px', align: 'center' as const },
  { key: 'price_minor', label: 'Harga Satuan', align: 'right' as const },
  { key: 'discount_minor', label: 'Diskon', align: 'right' as const },
  { key: 'total_minor', label: 'Subtotal', align: 'right' as const },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      :title="`Rincian Transaksi: ${transaction.description || 'Mutasi Keuangan'}`"
      description="Informasi kuitansi, rincian barang, dan rekonsiliasi saldo"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Transaksi', href: `/t/${tenant.slug}/apps/pekan/transactions` },
        { label: 'Rincian' }
      ]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/transactions/${transaction.id}/edit`">
          <RdButton variant="outline" size="sm">
            Ubah
          </RdButton>
        </a>
        <RdButton variant="danger" size="sm" @click="isDeleteDialogOpen = true">
          Hapus
        </RdButton>
      </template>
    </RdPageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 w-full">
      <!-- Rincian Inti & Item Belanja (2 Kolom) -->
      <div class="lg:col-span-2 space-y-6">
        <RdCard title="Informasi Faktur & Pembayaran">
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
            <div class="sm:col-span-2 pb-2 mb-1 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
              <div>
                <dt class="text-slate-500 font-medium">ID Transaksi (Primary Key)</dt>
                <dd class="font-mono font-medium text-slate-800 dark:text-slate-100 select-all mt-0.5">
                  {{ transaction.id }}
                </dd>
              </div>
              <button
                type="button"
                @click="copyId(transaction.id)"
                class="text-teal-700 dark:text-teal-300 font-medium text-xs px-2.5 py-1 rounded border border-teal-200 dark:border-teal-800 hover:bg-teal-50 dark:hover:bg-teal-950/30 transition-colors cursor-pointer shrink-0"
              >
                {{ copiedId === transaction.id ? 'Tersalin' : 'Salin ID' }}
              </button>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Tanggal Transaksi</dt>
              <dd class="text-slate-800 dark:text-slate-100 font-semibold mt-0.5">
                <RdDateTime :value="transaction.transaction_date" format="date" />
              </dd>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Jenis Mutasi</dt>
              <dd class="mt-0.5">
                <RdBadge :variant="transaction.type === 'income' ? 'success' : (transaction.type === 'savings' ? 'info' : 'neutral')">
                  {{ transaction.type === 'income' ? 'Pemasukan' : (transaction.type === 'savings' ? 'Tabungan' : 'Pengeluaran') }}
                </RdBadge>
              </dd>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Kategori</dt>
              <dd class="text-slate-800 dark:text-slate-100 font-medium mt-0.5">
                {{ transaction.category_name || 'Tanpa Kategori' }}
              </dd>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Nama Toko / Merchant</dt>
              <dd class="text-slate-800 dark:text-slate-100 font-medium mt-0.5">
                {{ transaction.merchant_name || 'Tidak dicantumkan' }}
              </dd>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Nomor Kuitansi</dt>
              <dd class="text-slate-800 dark:text-slate-100 font-medium mt-0.5">
                {{ transaction.receipt_number || 'Tidak dicantumkan' }}
              </dd>
            </div>
          </dl>
        </RdCard>

        <!-- Tabel Rincian Item Barang -->
        <RdCard
          v-if="transaction.items && transaction.items.length > 0"
          title="Rincian Baris Barang"
        >
          <RdTable :columns="itemColumns" :items="transaction.items">
            <template #price_minor="{ value }">
              <RdMoney :amount-minor="value" />
            </template>
            <template #discount_minor="{ value }">
              <RdMoney :amount-minor="value" />
            </template>
            <template #total_minor="{ value }">
              <RdMoney :amount-minor="value" />
            </template>
          </RdTable>
        </RdCard>

        <!-- Lampiran Berkas Foto Struk / Bukti -->
        <RdCard
          v-if="transaction.images && transaction.images.length > 0"
          title="Lampiran Bukti / Struk Belanja"
        >
          <div class="space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <div
                v-for="(img, idx) in transaction.images"
                :key="idx"
                class="group relative cursor-pointer border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden bg-slate-50 dark:bg-slate-900 hover:border-teal-600 transition-colors"
                @click="openPreview(img.url, `Bukti Foto Struk ${idx + 1} - ${transaction.merchant_name || transaction.description || 'Transaksi'}`)"
              >
                <img
                  :src="img.url"
                  :alt="img.name || `Struk ${idx + 1}`"
                  class="w-full h-32 object-cover rounded"
                />
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-xs font-medium gap-1">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <span>Lihat Foto</span>
                </div>
              </div>
            </div>
            <p class="text-[11px] text-slate-500">
              Klik pada gambar untuk memperbesar pratinjau bukti foto struk transaksi.
            </p>
          </div>
        </RdCard>
      </div>

      <!-- Ringkasan Nominal & Lampiran (1 Kolom) -->
      <div class="space-y-6">
        <RdCard title="Ringkasan Nominal">
          <div class="space-y-2 text-xs">
            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-500">Subtotal</span>
              <span class="font-medium"><RdMoney :amount-minor="transaction.subtotal_minor || transaction.amount_minor" /></span>
            </div>
            <div v-if="transaction.tax_minor" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-500">Pajak</span>
              <span class="font-medium"><RdMoney :amount-minor="transaction.tax_minor" /></span>
            </div>
            <div v-if="transaction.service_charge_minor" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-500">Biaya Layanan</span>
              <span class="font-medium"><RdMoney :amount-minor="transaction.service_charge_minor" /></span>
            </div>
            <div v-if="transaction.receipt_discount_minor" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800 text-teal-800">
              <span>Diskon Struk</span>
              <span class="font-medium">- <RdMoney :amount-minor="transaction.receipt_discount_minor" /></span>
            </div>
            <div class="flex justify-between pt-2 text-sm font-bold border-t border-slate-200 dark:border-slate-800">
              <span>Total Akhir</span>
              <span class="text-teal-800 dark:text-teal-300">
                <RdMoney :amount-minor="transaction.amount_minor" />
              </span>
            </div>
          </div>
        </RdCard>

        <RdCard title="Catatan & Riwayat">
          <p class="text-xs text-slate-600 dark:text-slate-300 mb-2">
            {{ transaction.notes || 'Tidak ada catatan tambahan untuk transaksi ini.' }}
          </p>
          <div class="text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-2">
            Dicatat pada: <RdDateTime :value="transaction.created_at" format="datetime" />
          </div>
        </RdCard>
      </div>
    </div>

    <!-- Dialog Konfirmasi Hapus Transaksi -->
    <RdConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Hapus Transaksi Ini?"
      message="Data mutasi transaksi akan dihapus dari buku kas. Tindakan ini tidak dapat dibatalkan."
      danger
      confirm-text="Ya, Hapus Transaksi"
      @confirm="handleDelete"
    />

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
          <span>Lampiran Bukti Kuitansi</span>
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
