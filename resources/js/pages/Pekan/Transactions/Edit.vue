<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceCategory, Transaction, TransactionItem } from '../../../types/pekan';

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    tenants?: Tenant[];
    apps?: AppManifest[];
    categories?: FinanceCategory[];
    accounts?: Array<{ id: string; name: string; account_type: string }>;
    transaction: Transaction;
  }>(),
  {
    categories: () => [],
    accounts: () => [],
  }
);

const isSubmitting = ref(false);

const transactionType = ref<'income' | 'expense' | 'savings'>(
  props.transaction.type === 'income' ? 'income' : (props.transaction.type === 'savings' ? 'savings' : 'expense')
);
const categoryId = ref(props.transaction.category_id || (props.categories[0]?.id || ''));
const accountId = ref(props.transaction.account_id || props.accounts?.[0]?.id || '');
const transactionDate = ref(props.transaction.transaction_date ? props.transaction.transaction_date.substring(0, 10) : new Date().toISOString().substring(0, 10));
const description = ref(props.transaction.description || '');
const merchantName = ref(props.transaction.merchant_name || '');
const receiptNumber = ref(props.transaction.receipt_number || '');
const notes = ref(props.transaction.notes || '');

const accountOptions = computed(() =>
  (props.accounts || []).map(a => {
    const typeLabel = a.account_type === 'cash' ? 'Tunai' : (a.account_type === 'bank' ? 'Bank' : (a.account_type === 'ewallet' ? 'E-Wallet' : (a.account_type === 'credit_card' ? 'Kartu Kredit' : 'Lainnya')));
    return {
      label: `${a.name} (${typeLabel})`,
      value: a.id,
    };
  })
);

const initialItems: TransactionItem[] = (props.transaction.items && props.transaction.items.length > 0)
  ? props.transaction.items.map(it => ({
      item_name: it.item_name,
      quantity: Number(it.quantity) || 1,
      price_minor: it.price_minor || 0,
      discount_minor: it.discount_minor || 0,
      total_minor: it.total_minor || 0,
    }))
  : [
      {
        item_name: props.transaction.description || 'Item Transaksi',
        quantity: 1,
        price_minor: props.transaction.amount_minor || 50000,
        discount_minor: 0,
        total_minor: props.transaction.amount_minor || 50000,
      }
    ];

const items = ref<TransactionItem[]>(initialItems);

const categoryOptions = computed(() =>
  props.categories
    .filter(c => c.category_type === transactionType.value)
    .map(c => ({ label: c.name, value: c.id }))
);

watch(transactionType, (newType) => {
  const currentCategory = props.categories.find(c => c.id === categoryId.value);
  if (!currentCategory || currentCategory.category_type !== newType) {
    const match = props.categories.find(c => c.category_type === newType);
    categoryId.value = match ? match.id : '';
  }
});

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

  router.put(`/t/${props.tenant.slug}/apps/pekan/transactions/${props.transaction.id}`, {
    account_id: accountId.value || null,
    type: transactionType.value,
    category_id: categoryId.value || null,
    amount_minor: grandTotalMinor.value > 0 ? grandTotalMinor.value : props.transaction.amount_minor,
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
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Ubah Rincian Transaksi"
      description="Perbarui informasi mutasi kas, kategori, kuitansi, atau rincian belanja"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Transaksi', href: `/t/${tenant.slug}/apps/pekan/transactions` },
        { label: 'Ubah' }
      ]"
    />

    <form @submit.prevent="handleSubmit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Kolom Kiri: Formulir Transaksi & Item Barang (2 Kolom) -->
      <div class="lg:col-span-2 space-y-6">
        <RdCard title="Informasi Transaksi">
          <div class="space-y-4">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2 flex items-center gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Jenis Mutasi:</label>
              <div class="flex gap-2">
                <button
                  type="button"
                  :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors',
                    transactionType === 'expense'
                      ? 'bg-slate-900 text-white border-slate-900 dark:bg-slate-100 dark:text-slate-900'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400'
                  ]"
                  @click="transactionType = 'expense'"
                >
                  Pengeluaran
                </button>
                <button
                  type="button"
                  :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors cursor-pointer',
                    transactionType === 'income'
                      ? 'bg-teal-700 text-white border-teal-700'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400'
                  ]"
                  @click="transactionType = 'income'"
                >
                  Pemasukan
                </button>
                <button
                  type="button"
                  :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors cursor-pointer',
                    transactionType === 'savings'
                      ? 'bg-sky-700 text-white border-sky-700'
                      : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400'
                  ]"
                  @click="transactionType = 'savings'"
                >
                  Tabungan
                </button>
              </div>
            </div>

            <div class="sm:col-span-2">
              <RdInput
                id="description"
                v-model="description"
                label="Nama Transaksi"
                placeholder="Contoh: Makan Siang, Belanja Mingguan Superindo"
                required
              />
            </div>

            <RdInput
              id="transaction_date"
              v-model="transactionDate"
              type="date"
              label="Tanggal Transaksi"
              required
            />

            <RdSelect
              id="category_id"
              v-model="categoryId"
              label="Kategori Transaksi"
              :options="categoryOptions"
            />

            <RdSelect
              id="account_id"
              v-model="accountId"
              label="Metode Pembayaran / Akun"
              :options="accountOptions"
              required
            />

            <RdInput
              id="merchant_name"
              v-model="merchantName"
              label="Nama Toko / Merchant"
              placeholder="Contoh: Supermarket Bahagia"
            />

            <RdInput
              id="receipt_number"
              v-model="receiptNumber"
              label="Nomor Kuitansi / Struk"
              placeholder="Contoh: STR-20261025-001"
            />
          </div>
        </div>
      </RdCard>

        <!-- Baris Item Belanja -->
        <RdCard title="Rincian Baris Barang">
          <div class="space-y-3">
            <div
              v-for="(item, idx) in items"
              :key="idx"
              class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-md border border-slate-200 dark:border-slate-700 space-y-3"
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Barang #{{ idx + 1 }}</span>
                <button
                  v-if="items.length > 1"
                  type="button"
                  class="text-xs text-red-600 hover:underline"
                  @click="removeItem(idx)"
                >
                  Hapus Baris
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2">
                  <RdInput
                    :id="`item_name_${idx}`"
                    v-model="item.item_name"
                    placeholder="Nama barang / jasa"
                  />
                </div>
                <div>
                  <RdInput
                    :id="`qty_${idx}`"
                    v-model.number="item.quantity"
                    type="number"
                    step="any"
                    min="0.0001"
                    placeholder="Qty"
                    @input="updateItemTotal(item)"
                  />
                </div>
                <div>
                  <RdInput
                    :id="`price_${idx}`"
                    v-model.number="item.price_minor"
                    type="number"
                    min="0"
                    placeholder="Harga satuan"
                    @input="updateItemTotal(item)"
                  />
                </div>
              </div>

              <div class="flex justify-end text-xs text-slate-500 font-medium">
                Subtotal Baris: <span class="font-bold text-slate-800 dark:text-slate-100 ml-1"><RdMoney :amount-minor="item.total_minor" /></span>
              </div>
            </div>

            <RdButton
              type="button"
              variant="secondary"
              size="sm"
              class="w-full"
              @click="addItem"
            >
              + Tambah Baris Barang
            </RdButton>
          </div>
        </RdCard>

        <RdCard title="Catatan Tambahan">
          <RdTextarea
            id="notes"
            v-model="notes"
            label="Catatan Pembukuan"
            placeholder="Informasi pelengkap transaksi..."
            :rows="3"
          />
        </RdCard>
      </div>

      <!-- Kolom Kanan: Ringkasan Total & Aksi Simpan (1 Kolom) -->
      <div class="space-y-6">
        <RdCard title="Ringkasan Nominal">
          <div class="space-y-4">
            <div class="flex justify-between items-center text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <span class="text-slate-500">Jumlah Baris:</span>
              <span class="font-bold">{{ items.length }} item</span>
            </div>

            <div class="flex justify-between items-center text-sm border-b border-slate-100 dark:border-slate-800 pb-3">
              <span class="text-slate-500">Total Mutasi:</span>
              <span class="text-base font-extrabold text-teal-800 dark:text-teal-400">
                <RdMoney :amount-minor="grandTotalMinor" />
              </span>
            </div>

            <div class="space-y-2 pt-2">
              <RdButton
                type="submit"
                variant="primary"
                size="md"
                class="w-full"
                :loading="isSubmitting"
              >
                Simpan Perubahan
              </RdButton>
              <a :href="`/t/${tenant.slug}/apps/pekan/transactions/${transaction.id}`" class="block">
                <RdButton
                  type="button"
                  variant="outline"
                  size="md"
                  class="w-full"
                >
                  Batal
                </RdButton>
              </a>
            </div>
          </div>
        </RdCard>
      </div>
    </form>
  </RudimahAppShell>
</template>
