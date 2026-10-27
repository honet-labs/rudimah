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
import type { User, Tenant } from '../../../types/core';

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

const props = defineProps<{
  user: User;
  tenant: Tenant;
  categories?: CategoryItem[];
  status?: string;
  error?: string;
}>();

const categoryColumns = [
  { key: 'name', label: 'Nama Kategori' },
  { key: 'category_type', label: 'Jenis', width: '150px', align: 'center' as const },
  { key: 'is_active', label: 'Status', width: '120px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '150px', align: 'center' as const },
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

// Modal Tambah / Ubah
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

// Modal Konfirmasi Hapus
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
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pengaturan Modul PEKAN"
      description="Konfigurasi kategori transaksi keuangan, kanal pengingat tagihan, dan preferensi modul"
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

    <!-- SEKSI 1: MANAJEMEN KATEGORI TRANSAKSI -->
    <div class="mb-6">
      <RdCard
        title="Kategori Transaksi Keuangan"
        description="Kelola pos pengeluaran, pemasukan, dan tabungan kas untuk pencatatan transaksi, anggaran bulanan, dan ekstraksi struk OCR"
      >
        <template #actions>
          <RdButton variant="primary" size="sm" @click="openCreateModal">
            + Tambah Kategori
          </RdButton>
        </template>

        <!-- Filter Tab Kategori (Semua, Pengeluaran, Pemasukan) -->
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
              <div class="flex items-center justify-center gap-1.5">
                <RdButton variant="outline" size="sm" @click="openEditModal(item as CategoryItem)">
                  Ubah
                </RdButton>
                <RdButton variant="ghost" size="sm" @click="confirmDelete(item as CategoryItem)">
                  Hapus
                </RdButton>
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

    <!-- SEKSI 2: PENGATURAN LAINNYA (GRID) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
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
  </RudimahAppShell>
</template>
