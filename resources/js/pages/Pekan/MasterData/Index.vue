<script setup lang="ts">
import { ref } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdModal from '../../../design-system/RdModal.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceAccount, FinanceCategory } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    accounts?: FinanceAccount[];
    categories?: FinanceCategory[];
  }>(),
  {
    accounts: () => [
      { id: 'acc-1', name: 'BCA Utama', account_type: 'bank', currency: 'Rp', opening_balance_minor: 10000000, current_balance_minor: 15500000, is_active: true },
      { id: 'acc-2', name: 'Dompet Tunai', account_type: 'cash', currency: 'Rp', opening_balance_minor: 500000, current_balance_minor: 415000, is_active: true },
      { id: 'acc-3', name: 'Gopay / OVO', account_type: 'ewallet', currency: 'Rp', opening_balance_minor: 250000, current_balance_minor: 120000, is_active: true },
    ],
    categories: () => [
      { id: 'cat-1', name: 'Gaji Pokok', category_type: 'income', is_active: true },
      { id: 'cat-2', name: 'Belanja Dapur', category_type: 'expense', is_active: true },
      { id: 'cat-3', name: 'Transportasi', category_type: 'expense', is_active: true },
      { id: 'cat-4', name: 'Listrik & Air', category_type: 'expense', is_active: true },
    ],
  }
);

const isAddAccountOpen = ref(false);
const isAddCategoryOpen = ref(false);

const newAccountName = ref('');
const newAccountType = ref('bank');
const newAccountBalance = ref(0);

const newCategoryName = ref('');
const newCategoryType = ref('expense');

const accountColumns = [
  { key: 'name', label: 'Nama Rekening / Dompet' },
  { key: 'account_type', label: 'Jenis Akun', width: '130px' },
  { key: 'current_balance_minor', label: 'Saldo Terkini', align: 'right' as const },
  { key: 'is_active', label: 'Status', width: '100px', align: 'center' as const },
];

const categoryColumns = [
  { key: 'name', label: 'Nama Kategori' },
  { key: 'category_type', label: 'Tipe Mutasi', width: '130px' },
  { key: 'is_active', label: 'Status', width: '100px', align: 'center' as const },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Master Data Keuangan"
      description="Pengelolaan akun rekening bank, dompet kas, dan struktur kategori pembukuan"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Master Data' }]"
    />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Tabel Akun & Rekening Bank -->
      <RdCard title="Daftar Rekening & Dompet" description="Tempat penampungan saldo kas dan alur transaksi">
        <template #actions>
          <RdButton size="sm" variant="outline" @click="isAddAccountOpen = true">
            Tambah Rekening
          </RdButton>
        </template>
        <RdTable :columns="accountColumns" :items="accounts">
          <template #account_type="{ value }">
            <span class="text-xs uppercase font-medium text-slate-500">
              {{ value === 'bank' ? 'Bank' : value === 'cash' ? 'Tunai' : 'E-Wallet' }}
            </span>
          </template>
          <template #current_balance_minor="{ value }">
            <span class="font-semibold text-slate-800 dark:text-slate-100">
              <RdMoney :amount-minor="value" />
            </span>
          </template>
          <template #is_active="{ value }">
            <RdBadge :variant="value ? 'success' : 'neutral'">
              {{ value ? 'Aktif' : 'Nonaktif' }}
            </RdBadge>
          </template>
        </RdTable>
      </RdCard>

      <!-- Tabel Kategori Transaksi -->
      <RdCard title="Daftar Kategori Transaksi" description="Klasifikasi pos pemasukan dan pengeluaran belanja">
        <template #actions>
          <RdButton size="sm" variant="outline" @click="isAddCategoryOpen = true">
            Tambah Kategori
          </RdButton>
        </template>
        <RdTable :columns="categoryColumns" :items="categories">
          <template #category_type="{ value }">
            <span class="text-xs uppercase font-medium text-slate-500">
              {{ value === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
            </span>
          </template>
          <template #is_active="{ value }">
            <RdBadge :variant="value ? 'success' : 'neutral'">
              {{ value ? 'Aktif' : 'Nonaktif' }}
            </RdBadge>
          </template>
        </RdTable>
      </RdCard>
    </div>

    <!-- Modal Tambah Rekening -->
    <RdModal
      v-model="isAddAccountOpen"
      title="Tambah Rekening Baru"
      description="Masukkan data rekening bank atau dompet kas"
    >
      <form class="space-y-4">
        <RdInput
          id="acc_name"
          v-model="newAccountName"
          label="Nama Rekening / Dompet"
          placeholder="Contoh: BCA Tabungan, Mandiri, Dompet Tunai"
          required
        />
        <RdSelect
          v-model="newAccountType"
          label="Jenis Akun"
          :options="[
            { label: 'Rekening Bank', value: 'bank' },
            { label: 'Uang Tunai (Cash)', value: 'cash' },
            { label: 'Dompet Digital (E-Wallet)', value: 'ewallet' },
          ]"
          required
        />
        <RdInput
          id="acc_balance"
          v-model.number="newAccountBalance"
          type="number"
          label="Saldo Awal (Rp)"
          required
        />
      </form>

      <template #footer>
        <RdButton variant="outline" size="sm" @click="isAddAccountOpen = false">Batal</RdButton>
        <RdButton variant="primary" size="sm">Simpan Rekening</RdButton>
      </template>
    </RdModal>

    <!-- Modal Tambah Kategori -->
    <RdModal
      v-model="isAddCategoryOpen"
      title="Tambah Kategori Baru"
      description="Pos klasifikasi transaksi"
    >
      <form class="space-y-4">
        <RdInput
          id="cat_name"
          v-model="newCategoryName"
          label="Nama Kategori"
          placeholder="Contoh: Pendidikan, Kesehatan, Investasi"
          required
        />
        <RdSelect
          v-model="newCategoryType"
          label="Tipe Kategori"
          :options="[
            { label: 'Pengeluaran (Expense)', value: 'expense' },
            { label: 'Pemasukan (Income)', value: 'income' },
          ]"
          required
        />
      </form>

      <template #footer>
        <RdButton variant="outline" size="sm" @click="isAddCategoryOpen = false">Batal</RdButton>
        <RdButton variant="primary" size="sm">Simpan Kategori</RdButton>
      </template>
    </RdModal>
  </RudimahAppShell>
</template>
