<script setup lang="ts">
import { ref } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
import type { User, Tenant } from '../../../types/core';
import type { Transaction } from '../../../types/pekan';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  transaction: Transaction;
}>();

const isDeleteDialogOpen = ref(false);

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
            <div>
              <dt class="text-slate-500 font-medium">Tanggal Transaksi</dt>
              <dd class="text-slate-800 dark:text-slate-100 font-semibold mt-0.5">
                <RdDateTime :value="transaction.transaction_date" format="date" />
              </dd>
            </div>
            <div>
              <dt class="text-slate-500 font-medium">Jenis Mutasi</dt>
              <dd class="mt-0.5">
                <RdBadge variant="neutral">
                  {{ transaction.type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
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
      @confirm="() => {}"
    />
  </RudimahAppShell>
</template>
