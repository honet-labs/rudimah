<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdCheckbox from '../../../design-system/RdCheckbox.vue';
import RdModal from '../../../design-system/RdModal.vue';
import RdAlert from '../../../design-system/RdAlert.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';

interface CategoryItem {
  id: string;
  name: string;
  category_type: 'expense' | 'income' | 'savings';
  parent_id?: string | null;
  icon?: string | null;
  color?: string | null;
  is_active: boolean;
  created_at?: string;
}

interface AccountItem {
  id: string;
  name: string;
  account_type: 'cash' | 'bank' | 'ewallet' | 'credit_card' | 'other';
  current_balance_minor: number;
  is_active: boolean;
  created_at?: string;
}

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  categories?: CategoryItem[];
  accounts?: AccountItem[];
  status?: string;
  error?: string;
}>();

const mainSection = ref<'categories' | 'accounts' | 'notifications'>('categories');

// --- 1. MANAJEMEN KATEGORI ---
const categoryColumns = [
  { key: 'name', label: 'Nama Kategori' },
  { key: 'category_type', label: 'Jenis', width: '150px', align: 'center' as const },
  { key: 'is_active', label: 'Status', width: '120px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '100px', align: 'center' as const },
];

const categoryTab = ref<'all' | 'expense' | 'income' | 'savings'>('all');

const expenseCount = computed(() => (props.categories || []).filter(c => c.category_type === 'expense').length);
const incomeCount = computed(() => (props.categories || []).filter(c => c.category_type === 'income').length);
const savingsCount = computed(() => (props.categories || []).filter(c => c.category_type === 'savings').length);

const filteredCategories = computed(() => {
  const list = props.categories || [];
  if (categoryTab.value === 'all') return list;
  return list.filter(c => c.category_type === categoryTab.value);
});

const showCategoryModal = ref(false);
const isEditing = ref(false);
const editingId = ref<string | null>(null);
const isSubmitting = ref(false);

const categoryForm = ref({
  name: '',
  category_type: 'expense' as 'expense' | 'income' | 'savings',
  is_active: true,
});

const categoryTypeOptions = [
  { label: 'Pengeluaran (Expense)', value: 'expense' },
  { label: 'Pemasukan (Income)', value: 'income' },
  { label: 'Tabungan (Savings)', value: 'savings' },
];

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  const defaultType = categoryTab.value === 'income' ? 'income' : (categoryTab.value === 'savings' ? 'savings' : 'expense');
  categoryForm.value = {
    name: '',
    category_type: defaultType,
    is_active: true,
  };
  showCategoryModal.value = true;
}

function openEditModal(category: CategoryItem) {
  isEditing.value = true;
  editingId.value = category.id;
  categoryForm.value = {
    name: category.name,
    category_type: category.category_type,
    is_active: category.is_active,
  };
  showCategoryModal.value = true;
}

function submitCategory() {
  if (!categoryForm.value.name.trim()) return;
  isSubmitting.value = true;
  const baseUrl = `/t/${props.tenant.slug}/apps/pekan/categories`;

  if (isEditing.value && editingId.value) {
    router.put(`${baseUrl}/${editingId.value}`, categoryForm.value, {
      preserveScroll: true,
      onFinish: () => {
        isSubmitting.value = false;
        showCategoryModal.value = false;
      },
    });
  } else {
    router.post(baseUrl, categoryForm.value, {
      preserveScroll: true,
      onFinish: () => {
        isSubmitting.value = false;
        showCategoryModal.value = false;
      },
    });
  }
}

const showDeleteModal = ref(false);
const deletingCategory = ref<CategoryItem | null>(null);
const isDeleting = ref(false);

function confirmDelete(category: CategoryItem) {
  deletingCategory.value = category;
  showDeleteModal.value = true;
}

function deleteCategory() {
  if (!deletingCategory.value) return;
  isDeleting.value = true;
  const url = `/t/${props.tenant.slug}/apps/pekan/categories/${deletingCategory.value.id}`;

  router.delete(url, {
    preserveScroll: true,
    onFinish: () => {
      isDeleting.value = false;
      showDeleteModal.value = false;
      deletingCategory.value = null;
    },
  });
}

// --- 2. MANAJEMEN METODE PEMBAYARAN (AKUN KEUANGAN) ---
const accountColumns = [
  { key: 'name', label: 'Nama Metode / Akun' },
  { key: 'account_type', label: 'Tipe', width: '150px', align: 'center' as const },
  { key: 'current_balance_minor', label: 'Saldo Saat Ini', width: '170px', align: 'right' as const },
  { key: 'is_active', label: 'Status', width: '120px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '100px', align: 'center' as const },
];

const showAccountModal = ref(false);
const isEditingAccount = ref(false);
const editingAccountId = ref<string | null>(null);
const isSubmittingAccount = ref(false);

const accountForm = ref({
  name: '',
  account_type: 'bank' as 'cash' | 'bank' | 'ewallet' | 'credit_card' | 'other',
  current_balance_rupiah: 0,
  is_active: true,
});

const accountTypeOptions = [
  { label: 'Rekening Bank', value: 'bank' },
  { label: 'Kas Tunai (Cash)', value: 'cash' },
  { label: 'Dompet Digital (E-Wallet)', value: 'ewallet' },
  { label: 'Kartu Kredit (Credit Card)', value: 'credit_card' },
  { label: 'Lainnya', value: 'other' },
];

function openCreateAccountModal() {
  isEditingAccount.value = false;
  editingAccountId.value = null;
  accountForm.value = {
    name: '',
    account_type: 'bank',
    current_balance_rupiah: 0,
    is_active: true,
  };
  showAccountModal.value = true;
}

function openEditAccountModal(account: AccountItem) {
  isEditingAccount.value = true;
  editingAccountId.value = account.id;
  accountForm.value = {
    name: account.name,
    account_type: account.account_type,
    current_balance_rupiah: Math.round((account.current_balance_minor || 0) / 100),
    is_active: account.is_active,
  };
  showAccountModal.value = true;
}

function submitAccount() {
  if (!accountForm.value.name.trim()) return;
  isSubmittingAccount.value = true;
  const baseUrl = `/t/${props.tenant.slug}/apps/pekan/accounts`;
  const payload = {
    name: accountForm.value.name.trim(),
    account_type: accountForm.value.account_type,
    current_balance_minor: Math.round(Number(accountForm.value.current_balance_rupiah || 0) * 100),
    is_active: accountForm.value.is_active,
  };

  if (isEditingAccount.value && editingAccountId.value) {
    router.put(`${baseUrl}/${editingAccountId.value}`, payload, {
      preserveScroll: true,
      onFinish: () => {
        isSubmittingAccount.value = false;
        showAccountModal.value = false;
      },
    });
  } else {
    router.post(baseUrl, payload, {
      preserveScroll: true,
      onFinish: () => {
        isSubmittingAccount.value = false;
        showAccountModal.value = false;
      },
    });
  }
}

const showDeleteAccountModal = ref(false);
const deletingAccount = ref<AccountItem | null>(null);
const isDeletingAccount = ref(false);

function confirmDeleteAccount(account: AccountItem) {
  deletingAccount.value = account;
  showDeleteAccountModal.value = true;
}

function deleteAccount() {
  if (!deletingAccount.value) return;
  isDeletingAccount.value = true;
  const url = `/t/${props.tenant.slug}/apps/pekan/accounts/${deletingAccount.value.id}`;

  router.delete(url, {
    preserveScroll: true,
    onFinish: () => {
      isDeletingAccount.value = false;
      showDeleteAccountModal.value = false;
      deletingAccount.value = null;
    },
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
      title="Pengaturan Modul PEKAN"
      description="Konfigurasi kategori transaksi keuangan, metode pembayaran, kanal pengingat tagihan, dan preferensi modul"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Pengaturan' }]"
    />

    <!-- Feedback Notifikasi Status / Error -->
    <div v-if="status" class="mb-4 sm:mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-4 sm:mb-6">
      <RdAlert variant="error" title="Kendala Pengaturan">
        {{ error }}
      </RdAlert>
    </div>

    <!-- TAB NAVIGASI UTAMA PENGATURAN -->
    <div class="border-b border-slate-200 dark:border-slate-800 mb-6 flex gap-2 overflow-x-auto pb-1">
      <button
        type="button"
        :class="[
          'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 shrink-0 cursor-pointer',
          mainSection === 'categories'
            ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
            : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
        ]"
        @click="mainSection = 'categories'"
      >
        <span>Kategori Transaksi</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
          {{ (categories || []).length }}
        </span>
      </button>

      <button
        type="button"
        :class="[
          'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 shrink-0 cursor-pointer',
          mainSection === 'accounts'
            ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
            : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
        ]"
        @click="mainSection = 'accounts'"
      >
        <span>Metode Pembayaran (Akun Kas)</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
          {{ (accounts || []).length }}
        </span>
      </button>

      <button
        type="button"
        :class="[
          'px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 shrink-0 cursor-pointer',
          mainSection === 'notifications'
            ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
            : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
        ]"
        @click="mainSection = 'notifications'"
      >
        <span>Kanal Notifikasi & Integrasi</span>
      </button>
    </div>

    <!-- SEKSI 1: MANAJEMEN KATEGORI TRANSAKSI -->
    <div v-if="mainSection === 'categories'" class="space-y-6">
      <RdCard
        title="Kategori Transaksi Keuangan"
        description="Kelola pos pengeluaran, pemasukan, dan tabungan kas untuk pencatatan transaksi, anggaran bulanan, dan ekstraksi struk OCR"
      >
        <template #actions>
          <RdButton variant="primary" size="sm" @click="openCreateModal">
            + Tambah Kategori
          </RdButton>
        </template>

        <!-- Filter Sub-Tab Kategori -->
        <div class="border-b border-slate-200 dark:border-slate-800 mb-4 flex gap-1 sm:gap-2 overflow-x-auto pb-1 -mb-px">
          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 shrink-0 cursor-pointer',
              categoryTab === 'all'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="categoryTab = 'all'"
          >
            <span>Semua Kategori</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
              {{ (categories || []).length }}
            </span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 shrink-0 cursor-pointer',
              categoryTab === 'expense'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="categoryTab = 'expense'"
          >
            <span>Pengeluaran</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
              {{ expenseCount }}
            </span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 shrink-0 cursor-pointer',
              categoryTab === 'income'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="categoryTab = 'income'"
          >
            <span>Pemasukan</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
              {{ incomeCount }}
            </span>
          </button>

          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-1.5 shrink-0 cursor-pointer',
              categoryTab === 'savings'
                ? 'border-teal-600 text-teal-700 dark:text-teal-400 bg-teal-50/50 dark:bg-teal-950/20'
                : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            ]"
            @click="categoryTab = 'savings'"
          >
            <span>Tabungan</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
              {{ savingsCount }}
            </span>
          </button>
        </div>

        <!-- Tabel Kategori Transaksi -->
        <div v-if="filteredCategories.length > 0">
          <RdTable :columns="categoryColumns" :items="filteredCategories">
            <template #name="{ item }">
              <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
            </template>

            <template #category_type="{ value }">
              <RdBadge
                :variant="value === 'income' ? 'success' : (value === 'savings' ? 'info' : 'neutral')"
                class="whitespace-nowrap"
              >
                {{ value === 'income' ? 'Pemasukan' : (value === 'savings' ? 'Tabungan' : 'Pengeluaran') }}
              </RdBadge>
            </template>

            <template #is_active="{ value }">
              <RdBadge :variant="value ? 'success' : 'neutral'" class="whitespace-nowrap">
                {{ value ? 'Aktif' : 'Nonaktif' }}
              </RdBadge>
            </template>

            <template #actions="{ item }">
              <div class="flex items-center justify-center gap-1">
                <button
                  type="button"
                  class="p-1.5 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Ubah Kategori"
                  @click="openEditModal(item as CategoryItem)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="p-1.5 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Hapus Kategori"
                  @click="confirmDelete(item as CategoryItem)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </template>
          </RdTable>
        </div>

        <RdEmptyState
          v-else
          title="Belum Ada Kategori Transaksi"
          description="Tambahkan kategori pengeluaran, pemasukan, atau tabungan baru agar dapat dipilih saat mencatat transaksi."
        >
          <template #actions>
            <RdButton variant="primary" size="sm" @click="openCreateModal">
              + Tambah Kategori Baru
            </RdButton>
          </template>
        </RdEmptyState>
      </RdCard>
    </div>

    <!-- SEKSI 2: MANAJEMEN METODE PEMBAYARAN (AKUN KEUANGAN) -->
    <div v-else-if="mainSection === 'accounts'" class="space-y-6">
      <RdCard
        title="Metode Pembayaran & Akun Kas"
        description="Kelola rekening bank, dompet digital, kartu kredit, dan kas tunai untuk sumber pembayaran saat pencatatan transaksi maupun pemindaian struk"
      >
        <template #actions>
          <RdButton variant="primary" size="sm" @click="openCreateAccountModal">
            + Tambah Metode Pembayaran
          </RdButton>
        </template>

        <div v-if="(accounts || []).length > 0">
          <RdTable :columns="accountColumns" :items="accounts || []">
            <template #name="{ item }">
              <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
            </template>

            <template #account_type="{ value }">
              <RdBadge variant="neutral" class="whitespace-nowrap uppercase text-[10px]">
                {{ value === 'cash' ? 'Tunai' : (value === 'bank' ? 'Bank' : (value === 'ewallet' ? 'E-Wallet' : (value === 'credit_card' ? 'Kartu Kredit' : 'Lainnya'))) }}
              </RdBadge>
            </template>

            <template #current_balance_minor="{ value }">
              <span class="font-mono font-medium text-slate-800 dark:text-slate-200">
                <RdMoney :amount-minor="value || 0" />
              </span>
            </template>

            <template #is_active="{ value }">
              <RdBadge :variant="value ? 'success' : 'neutral'" class="whitespace-nowrap">
                {{ value ? 'Aktif' : 'Nonaktif' }}
              </RdBadge>
            </template>

            <template #actions="{ item }">
              <div class="flex items-center justify-center gap-1">
                <button
                  type="button"
                  class="p-1.5 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Ubah Metode Pembayaran"
                  @click="openEditAccountModal(item as AccountItem)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="p-1.5 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Hapus Metode Pembayaran"
                  @click="confirmDeleteAccount(item as AccountItem)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </template>
          </RdTable>
        </div>

        <RdEmptyState
          v-else
          title="Belum Ada Metode Pembayaran"
          description="Tambahkan rekening bank, dompet digital, atau kas tunai agar dapat dipilih sebagai sumber dana saat mencatat transaksi."
        >
          <template #actions>
            <RdButton variant="primary" size="sm" @click="openCreateAccountModal">
              + Tambah Metode Pembayaran Baru
            </RdButton>
          </template>
        </RdEmptyState>
      </RdCard>
    </div>

    <!-- SEKSI 3: PENGATURAN NOTIFIKASI & INTEGRASI -->
    <div v-else-if="mainSection === 'notifications'" class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
      <!-- Pengaturan Pengingat & Notifikasi -->
      <RdCard title="Kanal Notifikasi Tagihan" description="Pilih media pengiriman pengingat jatuh tempo pembayaran">
        <div class="space-y-4">
          <RdCheckbox
            label="Kirim notifikasi email harian untuk tagihan mendekati jatuh tempo"
            :model-value="true"
          />
          <RdCheckbox
            label="Kirim peringatan ambang batas anggaran saat belanja mencapai 80%"
            :model-value="true"
          />
          <div class="pt-2">
            <RdButton variant="primary" size="sm">
              Simpan Preferensi
            </RdButton>
          </div>
        </div>
      </RdCard>

      <!-- Integrasi Bot WhatsApp -->
      <RdCard title="Integrasi WhatsApp Bot" description="Pencatatan mutasi belanja instan via pesan WhatsApp">
        <div class="space-y-3 text-xs">
          <div class="flex items-center justify-between p-3 border rounded-md">
            <div>
              <span class="font-semibold text-slate-800 dark:text-slate-100">Status Layanan Bot</span>
              <p class="text-slate-500">Koneksi gateway resmi Meta Cloud API</p>
            </div>
            <RdBadge variant="neutral">Tersedia</RdBadge>
          </div>
          <p class="text-slate-500">
            Hubungkan nomor WhatsApp untuk mencatat pengeluaran harian cukup dengan mengirim pesan teks atau foto kuitansi ke bot.
          </p>
          <div class="pt-2">
            <RdButton variant="outline" size="sm">
              Konfigurasi Kunci API
            </RdButton>
          </div>
        </div>
      </RdCard>
    </div>

    <!-- MODAL: TAMBAH / UBAH KATEGORI TRANSAKSI -->
    <RdModal
      v-model="showCategoryModal"
      :title="isEditing ? 'Ubah Kategori Transaksi' : 'Tambah Kategori Transaksi'"
      :description="isEditing ? 'Perbarui informasi pos kategori keuangan' : 'Buat pos kategori baru untuk pengeluaran, pemasukan, atau tabungan ruang kerja'"
      max-width="md"
    >
      <form @submit.prevent="submitCategory" class="space-y-4 py-2">
        <RdInput
          v-model="categoryForm.name"
          label="Nama Kategori"
          placeholder="Contoh: Belanja Bulanan, Gaji, Transportasi, Tabungan Impian"
          hint="Nama pos yang mudah dipahami saat input transaksi"
          required
        />

        <RdSelect
          v-model="categoryForm.category_type"
          label="Jenis Transaksi"
          :options="categoryTypeOptions"
          hint="Pilih apakah kategori ini diperuntukkan bagi pengeluaran, pemasukan, atau tabungan"
          required
        />

        <div class="pt-1">
          <RdCheckbox
            v-model="categoryForm.is_active"
            label="Kategori Aktif"
            description="Kategori aktif dapat langsung dipilih saat pencatatan transaksi kas maupun perencanaan anggaran"
          />
        </div>
      </form>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isSubmitting"
            @click="showCategoryModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isSubmitting || !categoryForm.name.trim()"
            @click="submitCategory"
          >
            <span v-if="isSubmitting">Menyimpan...</span>
            <span v-else>{{ isEditing ? 'Simpan Perubahan' : 'Buat Kategori' }}</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: KONFIRMASI HAPUS KATEGORI -->
    <RdModal
      v-model="showDeleteModal"
      title="Hapus Kategori Transaksi"
      description="Konfirmasi penghapusan kategori pos keuangan"
      max-width="md"
    >
      <div class="space-y-3 py-2 text-xs text-slate-600 dark:text-slate-300">
        <p>
          Apakah Anda yakin ingin menghapus kategori <strong>{{ deletingCategory?.name }}</strong>?
        </p>
        <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500">
          <strong>Perlindungan Integritas Kas:</strong> Jika kategori ini telah tercatat pada transaksi sebelumnya, sistem akan otomatis mengubah statusnya menjadi <em>Nonaktif</em> agar riwayat laporan keuangan tetap valid.
        </div>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isDeleting"
            @click="showDeleteModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="danger"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isDeleting"
            @click="deleteCategory"
          >
            <span v-if="isDeleting">Menghapus...</span>
            <span v-else>Hapus Kategori</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: TAMBAH / UBAH METODE PEMBAYARAN (AKUN KEUANGAN) -->
    <RdModal
      v-model="showAccountModal"
      :title="isEditingAccount ? 'Ubah Metode Pembayaran' : 'Tambah Metode Pembayaran'"
      :description="isEditingAccount ? 'Perbarui informasi rekening bank, dompet digital, atau kas' : 'Buat rekening, dompet digital, kartu kredit, atau kas tunai baru'"
      max-width="md"
    >
      <form @submit.prevent="submitAccount" class="space-y-4 py-2">
        <RdInput
          v-model="accountForm.name"
          label="Nama Metode / Akun"
          placeholder="Contoh: BCA Utama, Kas Dompet, GoPay, Mandiri Kartu Kredit"
          hint="Nama akun atau dompet yang mudah dikenali saat pembayaran transaksi"
          required
        />

        <RdSelect
          v-model="accountForm.account_type"
          label="Tipe Akun"
          :options="accountTypeOptions"
          hint="Tentukan jenis instrumen penyimpanan dana ini"
          required
        />

        <RdInput
          v-model.number="accountForm.current_balance_rupiah"
          type="number"
          label="Saldo Awal / Terkini (Rp)"
          placeholder="0"
          hint="Nominal saldo riil yang tersimpan dalam akun ini saat ini"
          required
        />

        <div class="pt-1">
          <RdCheckbox
            v-model="accountForm.is_active"
            label="Metode Pembayaran Aktif"
            description="Metode pembayaran aktif akan otomatis tampil dalam pilihan pembayaran transaksi"
          />
        </div>
      </form>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isSubmittingAccount"
            @click="showAccountModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="primary"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isSubmittingAccount || !accountForm.name.trim()"
            @click="submitAccount"
          >
            <span v-if="isSubmittingAccount">Menyimpan...</span>
            <span v-else>{{ isEditingAccount ? 'Simpan Perubahan' : 'Buat Metode Pembayaran' }}</span>
          </RdButton>
        </div>
      </template>
    </RdModal>

    <!-- MODAL: KONFIRMASI HAPUS METODE PEMBAYARAN -->
    <RdModal
      v-model="showDeleteAccountModal"
      title="Hapus Metode Pembayaran"
      description="Konfirmasi penghapusan akun atau metode pembayaran"
      max-width="md"
    >
      <div class="space-y-3 py-2 text-xs text-slate-600 dark:text-slate-300">
        <p>
          Apakah Anda yakin ingin menghapus metode pembayaran <strong>{{ deletingAccount?.name }}</strong>?
        </p>
        <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500">
          <strong>Perlindungan Rekonsiliasi:</strong> Jika metode pembayaran ini telah digunakan pada transaksi terdahulu, sistem akan otomatis mengubah statusnya menjadi <em>Nonaktif</em> agar rekonsiliasi dan laporan mutasi kas Anda tetap utuh.
        </div>
      </div>

      <template #footer>
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
          <RdButton
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isDeletingAccount"
            @click="showDeleteAccountModal = false"
          >
            Batal
          </RdButton>
          <RdButton
            variant="danger"
            size="sm"
            class="w-full sm:w-auto"
            :disabled="isDeletingAccount"
            @click="deleteAccount"
          >
            <span v-if="isDeletingAccount">Menghapus...</span>
            <span v-else>Hapus Metode</span>
          </RdButton>
        </div>
      </template>
    </RdModal>
  </RudimahAppShell>
</template>
