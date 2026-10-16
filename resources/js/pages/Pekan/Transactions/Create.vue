<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import RdFileUpload from '../../../design-system/RdFileUpload.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceAccount, FinanceCategory, TransactionItem } from '../../../types/pekan';

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    tenants?: Tenant[];
    apps?: AppManifest[];
    accounts?: FinanceAccount[];
    categories?: FinanceCategory[];
  }>(),
  {
    accounts: () => [],
    categories: () => [],
  }
);

const isSubmitting = ref(false);
const transactionType = ref<'income' | 'expense' | 'transfer'>('expense');
const accountId = ref(props.accounts[0]?.id || '');
const categoryId = ref(props.categories[0]?.id || '');
const transactionDate = ref(new Date().toISOString().substring(0, 10));
const description = ref('');
const merchantName = ref('');
const receiptNumber = ref('');
const notes = ref('');

const items = ref<TransactionItem[]>([
  { item_name: 'Item Transaksi', quantity: 1, price_minor: 50000, discount_minor: 0, total_minor: 50000 }
]);

const accountOptions = computed(() =>
  props.accounts.map(a => ({ label: a.name, value: a.id }))
);

const categoryOptions = computed(() =>
  props.categories
    .filter(c => c.category_type === transactionType.value)
    .map(c => ({ label: c.name, value: c.id }))
);

function addItem() {
  items.value.push({
    item_name: '',
    quantity: 1,
    price_minor: 0,
    discount_minor: 0,
    total_minor: 0,
  });
}

function removeItem(index: number) {
  if (items.value.length > 1) {
    items.value.splice(index, 1);
  }
}

function updateItemTotal(item: TransactionItem) {
  const line = (item.quantity * item.price_minor) - item.discount_minor;
  item.total_minor = Math.max(0, line);
}

const grandTotalMinor = computed(() => {
  return items.value.reduce((acc, it) => acc + (it.total_minor || 0), 0);
});

function handleSubmit() {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  router.post(`/t/${props.tenant.slug}/apps/pekan/transactions`, {
    type: transactionType.value,
    account_id: accountId.value || props.accounts[0]?.id || 'acc-default',
    category_id: categoryId.value || null,
    amount_minor: grandTotalMinor.value > 0 ? grandTotalMinor.value : 50000,
    transaction_date: transactionDate.value,
    description: description.value || 'Pencatatan transaksi',
    merchant_name: merchantName.value,
    receipt_number: receiptNumber.value,
    notes: notes.value,
    items: items.value,
  }, {
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}
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
      title="Tambah Transaksi Baru"
      description="Catat mutasi keuangan tunggal atau kuitansi multi-item ke dalam pembukuan"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Transaksi', href: `/t/${tenant.slug}/apps/pekan/transactions` },
        { label: 'Tambah Baru' }
      ]"
    />

    <form @submit.prevent="handleSubmit" class="space-y-6 max-w-4xl">
      <!-- Data Transaksi Utama -->
      <RdCard title="Informasi Transaksi">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <RdSelect
            v-model="transactionType"
            label="Jenis Mutasi"
            :options="[
              { label: 'Pengeluaran (Belanja)', value: 'expense' },
              { label: 'Pemasukan (Pendapatan)', value: 'income' },
              { label: 'Transfer Internal', value: 'transfer' },
            ]"
            required
          />

          <RdSelect
            v-model="accountId"
            label="Rekening Sumber / Tujuan"
            :options="accountOptions"
            required
          />

          <RdSelect
            v-model="categoryId"
            label="Kategori"
            :options="categoryOptions"
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <RdInput
            id="tx_date"
            v-model="transactionDate"
            type="date"
            label="Tanggal Transaksi"
            required
          />

          <RdInput
            id="merchant"
            v-model="merchantName"
            label="Nama Toko / Merchant"
            placeholder="Contoh: Superindo, PLN, Pertamina"
          />

          <RdInput
            id="receipt_no"
            v-model="receiptNumber"
            label="Nomor Kuitansi / Struk"
            placeholder="Contoh: STR-202610-09"
          />
        </div>

        <div class="mt-4">
          <RdInput
            id="description"
            v-model="description"
            label="Keterangan Transaksi"
            placeholder="Catatan ringkas tujuan mutasi dana"
            required
          />
        </div>
      </RdCard>

      <!-- Rincian Item Barang (Multi-item) -->
      <RdCard title="Rincian Item Belanja" description="Tambahkan rincian barang individual jika mencatat struk multi-item">
        <template #actions>
          <RdButton type="button" variant="outline" size="sm" @click="addItem">
            Tambah Baris Item
          </RdButton>
        </template>

        <div class="space-y-3">
          <div
            v-for="(it, idx) in items"
            :key="idx"
            class="p-3 border border-slate-200 dark:border-slate-800 rounded-md grid grid-cols-1 sm:grid-cols-12 gap-3 items-end text-xs"
          >
            <div class="sm:col-span-5">
              <RdInput
                :id="`item_name_${idx}`"
                v-model="it.item_name"
                label="Nama Barang / Layanan"
                placeholder="Nama produk"
                required
              />
            </div>
            <div class="sm:col-span-2">
              <RdInput
                :id="`item_qty_${idx}`"
                v-model.number="it.quantity"
                type="number"
                label="Jumlah"
                required
                @input="updateItemTotal(it)"
              />
            </div>
            <div class="sm:col-span-2">
              <RdInput
                :id="`item_price_${idx}`"
                v-model.number="it.price_minor"
                type="number"
                label="Harga Satuan (Rp)"
                required
                @input="updateItemTotal(it)"
              />
            </div>
            <div class="sm:col-span-2">
              <span class="block text-[11px] font-semibold text-slate-500 mb-1">Subtotal</span>
              <div class="p-2 border border-slate-200 dark:border-slate-800 rounded bg-slate-50 dark:bg-slate-800 font-mono font-medium text-right">
                <RdMoney :amount-minor="it.total_minor" />
              </div>
            </div>
            <div class="sm:col-span-1 text-center">
              <button
                type="button"
                class="p-2 text-slate-400 hover:text-red-600 transition-colors cursor-pointer"
                :disabled="items.length <= 1"
                @click="removeItem(idx)"
                aria-label="Hapus baris"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Total Keseluruhan -->
        <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center text-sm font-semibold">
          <span>Total Nilai Transaksi</span>
          <span class="text-base text-teal-800 dark:text-teal-300 font-bold">
            <RdMoney :amount-minor="grandTotalMinor" />
          </span>
        </div>
      </RdCard>

      <!-- Lampiran Berkas dan Catatan -->
      <RdCard title="Lampiran Bukti dan Catatan">
        <div class="space-y-4">
          <RdFileUpload />
          <RdTextarea
            id="notes"
            v-model="notes"
            label="Catatan Tambahan"
            placeholder="Catatan internal atau pengingat terkait transaksi ini"
          />
        </div>
      </RdCard>

      <div class="flex items-center justify-end gap-3 pt-2">
        <a :href="`/t/${tenant.slug}/apps/pekan/transactions`">
          <RdButton variant="outline" type="button">
            Batal
          </RdButton>
        </a>
        <RdButton type="submit" variant="primary" :loading="isSubmitting" :disabled="isSubmitting">
          Simpan Transaksi
        </RdButton>
      </div>
    </form>
  </RudimahAppShell>
</template>
