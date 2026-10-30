<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdModal from '../../../design-system/RdModal.vue';
import RdConfirmDialog from '../../../design-system/RdConfirmDialog.vue';
import RdImageUploadMultiple from '../../../design-system/RdImageUploadMultiple.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceReminder, AttachmentImage } from '../../../types/pekan';

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    tenants?: Tenant[];
    apps?: AppManifest[];
    reminders?: FinanceReminder[];
  }>(),
  {
    reminders: () => [],
    tenants: () => [],
    apps: () => [],
  }
);

const tenantSlug = computed(() => props.tenant?.slug || (props.tenant as any)?.code || '');

// Tabel Kolom
const columns = [
  { key: 'title', label: 'Nama Tagihan' },
  { key: 'category_name', label: 'Kategori', width: '130px' },
  { key: 'due_date', label: 'Tanggal Jatuh Tempo', width: '150px' },
  { key: 'tenor', label: 'Tenor', width: '110px', align: 'center' as const },
  { key: 'amount_minor', label: 'Nominal', width: '140px', align: 'right' as const },
  { key: 'actions', label: 'Aksi', width: '180px', align: 'center' as const },
];


// Lightbox Pratinjau Gambar
const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string; images?: AttachmentImage[] }>({
  url: '',
  title: '',
  images: [],
});

function openPreview(url: string, title: string, images?: AttachmentImage[]) {
  previewImage.value = { url, title, images: images || [] };
  previewModalOpen.value = true;
}

// Modal Rincian Tagihan
const detailModalOpen = ref(false);
const detailReminder = ref<FinanceReminder | null>(null);

function openDetail(reminder: FinanceReminder) {
  detailReminder.value = reminder;
  detailModalOpen.value = true;
}

function openPayFromDetail() {
  const rem = detailReminder.value;
  detailModalOpen.value = false;
  if (rem) {
    openPayModal(rem);
  }
}

// Modal Riwayat Pembayaran Tagihan
const historyModalOpen = ref(false);
const historyReminder = ref<FinanceReminder | null>(null);

function openHistory(reminder: FinanceReminder) {
  historyReminder.value = reminder;
  historyModalOpen.value = true;
}

function openPayFromHistory() {
  const rem = historyReminder.value;
  historyModalOpen.value = false;
  if (rem) {
    openPayModal(rem);
  }
}

// Modal Aksi Bayar
const payModalOpen = ref(false);
const selectedReminder = ref<FinanceReminder | null>(null);

const payForm = useForm({
  payment_date: new Date().toISOString().substring(0, 10),
  amount_minor: 0,
  notes: '',
  images: [] as File[],
});

function openPayModal(reminder: FinanceReminder) {
  selectedReminder.value = reminder;
  payForm.payment_date = new Date().toISOString().substring(0, 10);
  payForm.amount_minor = reminder.amount_minor;
  payForm.notes = `Pembayaran ${reminder.title}${reminder.total_tenor ? ` (Tenor ${reminder.current_tenor || 1}/${reminder.total_tenor})` : ''}`;
  payForm.images = [];
  payForm.clearErrors();
  payModalOpen.value = true;
}

function submitPayment() {
  if (!selectedReminder.value) return;
  payForm.post(`/t/${tenantSlug.value}/apps/pekan/reminders/${selectedReminder.value.id}/pay`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      payModalOpen.value = false;
      payForm.reset();
    },
  });
}

// Dialog Hapus Tagihan
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const reminderToDelete = ref<FinanceReminder | null>(null);

function confirmDelete(reminder: FinanceReminder) {
  reminderToDelete.value = reminder;
  isDeleteDialogOpen.value = true;
}

function handleDelete() {
  if (!reminderToDelete.value) return;
  isDeleting.value = true;
  router.delete(`/t/${tenantSlug.value}/apps/pekan/reminders/${reminderToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      isDeleteDialogOpen.value = false;
      reminderToDelete.value = null;
    },
    onFinish: () => {
      isDeleting.value = false;
    },
  });
}

// Label Helpers
function repeatLabel(interval?: string): string {
  switch (interval) {
    case 'once': return 'Sekali (Non-rutin)';
    case 'daily': return 'Harian';
    case 'weekly': return 'Mingguan';
    case 'monthly': return 'Bulanan';
    case 'yearly': return 'Tahunan';
    default: return interval || 'Bulanan';
  }
}

function statusVariant(status?: string): 'success' | 'danger' | 'neutral' {
  if (status === 'paid') return 'success';
  if (status === 'overdue') return 'danger';
  return 'neutral';
}

function statusLabel(status?: string): string {
  if (status === 'paid') return 'Sudah Lunas';
  if (status === 'overdue') return 'Lewat Tempo';
  return 'Menunggu Bayar';
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
      title="Pengingat Tagihan & Kewajiban"
      description="Pencatatan dan pemantauan jadwal pembayaran tagihan rutin, utilitas, dan cicilan"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Pengingat' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/reminders/create`">
          <RdButton variant="primary" size="sm">
            Tambah Pengingat
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Konten Daftar Pengingat -->
    <div v-if="reminders && reminders.length > 0">
      <!-- 1. Tampilan Desktop & Tablet Lebar (Tabel Lengkap) -->
      <div class="hidden lg:block">
        <RdCard padding>
          <RdTable :columns="columns" :items="reminders">

            <!-- 2. Nama Tagihan -->
            <template #title="{ item }">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-slate-800 dark:text-slate-100">{{ item.title }}</span>
                  <RdBadge :variant="statusVariant(item.status)" class="text-[10px] py-0 px-1.5">
                    {{ statusLabel(item.status) }}
                  </RdBadge>
                </div>
                <!-- Lampiran berkas tagihan pokok jika ada -->
                <div v-if="item.images && item.images.length > 0" class="flex items-center gap-1.5 flex-wrap">
                  <button
                    v-for="(img, idx) in item.images"
                    :key="idx"
                    type="button"
                    @click.stop="openPreview(img.url, `${item.title} - Lampiran ${idx + 1}`)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
                    title="Klik untuk melihat bukti tagihan"
                  >
                    <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Berkas {{ idx + 1 }}</span>
                  </button>
                </div>
              </div>
            </template>

            <!-- 3. Kategori -->
            <template #category_name="{ item }">
              <span class="text-xs text-slate-700 dark:text-slate-300">
                {{ item.category_name || item.category?.name || 'Tanpa Kategori' }}
              </span>
            </template>

            <!-- 4. Tanggal Jatuh Tempo -->
            <template #due_date="{ item }">
              <RdDateTime :value="item.due_date" format="date" />
            </template>

            <!-- 5. Tenor -->
            <template #tenor="{ item }">
              <span v-if="item.total_tenor" class="text-xs font-mono font-medium text-slate-700 dark:text-slate-300">
                {{ item.current_tenor || 1 }} dari {{ item.total_tenor }}
              </span>
              <span v-else class="text-xs text-slate-400">Rutin</span>
            </template>

            <!-- 6. Nominal -->
            <template #amount_minor="{ item }">
              <span class="font-semibold text-slate-800 dark:text-slate-100">
                <RdMoney :amount-minor="item.amount_minor" />
              </span>
            </template>

            <!-- 7. Aksi (Icon-Only Buttons) -->
            <template #actions="{ item }">
              <div class="flex items-center justify-center gap-1">
                <!-- Bayar -->
                <button
                  type="button"
                  class="p-1.5 text-teal-700 hover:text-teal-900 dark:text-teal-400 dark:hover:text-teal-200 hover:bg-teal-50 dark:hover:bg-teal-950/30 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Bayar Tagihan"
                  @click.stop="openPayModal(item)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                </button>
                <!-- Riwayat -->
                <button
                  type="button"
                  class="p-1.5 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  :title="`Riwayat Pembayaran${item.payments && item.payments.length > 0 ? ` (${item.payments.length})` : ''}`"
                  @click.stop="openHistory(item)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <!-- Rincian -->
                <button
                  type="button"
                  class="p-1.5 text-slate-500 hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  title="Rincian Tagihan"
                  @click.stop="openDetail(item)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <!-- Ubah -->
                <a
                  :href="`/t/${tenantSlug}/apps/pekan/reminders/${item.id}/edit`"
                  title="Ubah Tagihan"
                  class="p-1.5 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors inline-flex items-center justify-center"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </a>
                <!-- Hapus -->
                <button
                  type="button"
                  title="Hapus Tagihan"
                  class="p-1.5 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-md transition-colors inline-flex items-center justify-center cursor-pointer"
                  @click.stop="confirmDelete(item)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </template>
          </RdTable>
        </RdCard>
      </div>

      <!-- 2. Tampilan Khusus Ponsel / Mobile (Stack Card) -->
      <div class="lg:hidden space-y-3 w-full">
        <div
          v-for="rem in reminders"
          :key="rem.id"
          class="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3 w-full"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">
                  {{ rem.category_name || rem.category?.name || 'Tanpa Kategori' }}
                </span>
              </div>
              <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">
                {{ rem.title }}
              </h4>
            </div>
            <RdBadge :variant="statusVariant(rem.status)" class="shrink-0 text-xs">
              {{ statusLabel(rem.status) }}
            </RdBadge>
          </div>

          <!-- Pratinjau Foto Lampiran Tagihan Mobile -->
          <div v-if="rem.images && rem.images.length > 0" class="flex items-center gap-2 pt-1 flex-wrap">
            <button
              v-for="(img, idx) in rem.images"
              :key="idx"
              type="button"
              @click="openPreview(img.url, `${rem.title} - Berkas ${idx + 1}`)"
              class="relative rounded border border-slate-200 dark:border-slate-700 overflow-hidden w-11 h-11 bg-slate-100 dark:bg-slate-800 shrink-0 hover:opacity-90 transition-opacity cursor-pointer"
            >
              <img :src="img.url" :alt="img.name || 'Bukti tagihan'" class="w-full h-full object-cover" />
            </button>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">({{ rem.images.length }} berkas)</span>
          </div>

          <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div>
              <span class="text-slate-400 text-[11px] block">Jatuh Tempo</span>
              <span class="font-medium text-slate-700 dark:text-slate-300">
                <RdDateTime :value="rem.due_date" format="date" />
              </span>
            </div>
            <div class="text-center">
              <span class="text-slate-400 text-[11px] block">Tenor</span>
              <span v-if="rem.total_tenor" class="font-semibold text-slate-700 dark:text-slate-300 font-mono">
                {{ rem.current_tenor || 1 }} / {{ rem.total_tenor }}
              </span>
              <span v-else class="text-slate-500 font-medium">Rutin</span>
            </div>
            <div class="text-right">
              <span class="text-slate-400 text-[11px] block">Nominal</span>
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                <RdMoney :amount-minor="rem.amount_minor" />
              </span>
            </div>
          </div>

          <!-- Aksi Mobile: Bayar, Riwayat, Rincian, Ubah, Hapus (Icon-Only Buttons) -->
          <div class="flex items-center justify-end gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              class="p-2 text-teal-700 hover:text-teal-900 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-900/50 rounded-md inline-flex items-center justify-center cursor-pointer"
              title="Bayar Tagihan"
              @click="openPayModal(rem)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </button>
            <button
              type="button"
              class="p-2 text-slate-600 hover:text-slate-900 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md inline-flex items-center justify-center cursor-pointer"
              :title="`Riwayat Pembayaran${rem.payments && rem.payments.length > 0 ? ` (${rem.payments.length})` : ''}`"
              @click="openHistory(rem)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <button
              type="button"
              class="p-2 text-slate-600 hover:text-teal-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md inline-flex items-center justify-center cursor-pointer"
              title="Rincian Tagihan"
              @click="openDetail(rem)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            <a
              :href="`/t/${tenantSlug}/apps/pekan/reminders/${rem.id}/edit`"
              title="Ubah Tagihan"
              class="p-2 text-slate-600 hover:text-slate-900 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md inline-flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </a>
            <button
              type="button"
              title="Hapus Tagihan"
              class="p-2 text-red-600 hover:text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 rounded-md inline-flex items-center justify-center cursor-pointer"
              @click="confirmDelete(rem)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State Bersih Saat Belum Ada Pengingat -->
    <RdEmptyState
      v-else
      title="Belum Ada Pengingat Tagihan"
      description="Catat tagihan utilitas, cicilan, atau kewajiban berkala agar Anda mendapatkan notifikasi pengingat sebelum tanggal jatuh tempo."
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/reminders/create`">
          <RdButton variant="primary" size="sm">
            Tambah Pengingat Baru
          </RdButton>
        </a>
      </template>
    </RdEmptyState>

    <!-- 1. Modal Aksi Bayar Tagihan -->
    <RdModal
      v-model="payModalOpen"
      :title="`Bayar Tagihan: ${selectedReminder?.title || ''}`"
      description="Catat pelunasan atau angsuran tagihan ini langsung ke pembukuan mutasi kas"
      max-width="lg"
    >
      <form @submit.prevent="submitPayment" class="space-y-4">
        <!-- Tanggal Bayar -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Tanggal Bayar <span class="text-red-500">*</span>
          </label>
          <RdInput
            v-model="payForm.payment_date"
            type="date"
            required
          />
          <p v-if="payForm.errors.payment_date" class="text-xs text-red-600 mt-1">
            {{ payForm.errors.payment_date }}
          </p>
        </div>

        <!-- Nominal Bayar -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Nominal Bayar (Rp) <span class="text-red-500">*</span>
          </label>
          <RdInput
            v-model.number="payForm.amount_minor"
            type="number"
            min="1000"
            step="1000"
            required
          />
          <div class="flex items-center justify-between text-xs text-slate-500 mt-1">
            <span>Terbilang: <RdMoney :amount-minor="payForm.amount_minor" /></span>
            <span
              v-if="selectedReminder && payForm.amount_minor !== selectedReminder.amount_minor"
              class="text-amber-600 dark:text-amber-400 font-medium"
            >
              Tagihan Pokok: <RdMoney :amount-minor="selectedReminder.amount_minor" />
            </span>
          </div>
          <p v-if="payForm.errors.amount_minor" class="text-xs text-red-600 mt-1">
            {{ payForm.errors.amount_minor }}
          </p>
        </div>

        <!-- Lampirkan Berkas Bukti Bayar -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Lampirkan Berkas Bukti Pembayaran / Struk Transfer
          </label>
          <RdImageUploadMultiple
            v-model="payForm.images"
            label=""
            description="Format: JPG, PNG, WEBP (maks. 5MB per berkas). Berkas struk/bukti transfer akan otomatis tersimpan."
          />
          <p v-if="payForm.errors.images" class="text-xs text-red-600 mt-1">
            {{ payForm.errors.images }}
          </p>
        </div>

        <!-- Keterangan -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Keterangan / Catatan Pembayaran
          </label>
          <RdTextarea
            v-model="payForm.notes"
            placeholder="Contoh: Lunas transfer via BCA / Pembayaran cicilan bulan ini"
            rows="2"
          />
          <p v-if="payForm.errors.notes" class="text-xs text-red-600 mt-1">
            {{ payForm.errors.notes }}
          </p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <RdButton
            type="button"
            variant="outline"
            @click="payModalOpen = false"
            :disabled="payForm.processing"
          >
            Batal
          </RdButton>
          <RdButton
            type="submit"
            variant="primary"
            :loading="payForm.processing"
          >
            Konfirmasi Pembayaran
          </RdButton>
        </div>
      </form>
    </RdModal>

    <!-- 2. Modal Riwayat Pembayaran Tagihan -->
    <RdModal
      v-model="historyModalOpen"
      :title="`Riwayat Pembayaran: ${historyReminder?.title || ''}`"
      description="Daftar mutasi pelunasan atau angsuran yang telah tercatat untuk tagihan ini"
      max-width="xl"
    >
      <div class="space-y-4">
        <!-- Ringkasan Singkat -->
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg flex items-center justify-between text-xs">
          <div>
            <span class="text-slate-400 block">Nama Tagihan:</span>
            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ historyReminder?.title }}</span>
          </div>
          <div class="text-right">
            <span class="text-slate-400 block">Total Riwayat:</span>
            <span class="font-semibold text-slate-800 dark:text-slate-200">
              {{ historyReminder?.payments?.length || 0 }} Kali Pembayaran
            </span>
          </div>
        </div>

        <!-- Tabel Riwayat Pembayaran -->
        <div
          v-if="historyReminder?.payments && historyReminder.payments.length > 0"
          class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-lg"
        >
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[11px] border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th class="px-3 py-2.5">Tanggal Bayar</th>
                <th class="px-3 py-2.5 text-right">Nominal</th>
                <th class="px-3 py-2.5 text-center">Tenor</th>
                <th class="px-3 py-2.5">Keterangan</th>
                <th class="px-3 py-2.5 text-center">Bukti Bayar</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              <tr
                v-for="payment in historyReminder.payments"
                :key="payment.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
              >
                <td class="px-3 py-2.5 whitespace-nowrap font-medium">
                  <RdDateTime :value="payment.payment_date" format="date" />
                </td>
                <td class="px-3 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                  <RdMoney :amount-minor="payment.amount_minor" />
                </td>
                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                  <span v-if="payment.tenor_number" class="font-mono text-slate-600 dark:text-slate-300">
                    Ke-{{ payment.tenor_number }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-3 py-2.5 max-w-[200px] truncate" :title="payment.notes || '-'">
                  {{ payment.notes || '-' }}
                </td>
                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                  <button
                    v-if="payment.images && payment.images.length > 0"
                    type="button"
                    @click="openPreview(payment.images[0].url, `Bukti Bayar - ${historyReminder.title}`, payment.images)"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-teal-700 dark:text-teal-300 transition-colors border border-slate-200 dark:border-slate-700 cursor-pointer"
                    title="Lihat bukti pembayaran"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Lihat{{ payment.images.length > 1 ? ` (${payment.images.length})` : '' }}</span>
                  </button>
                  <span v-else class="text-slate-400 text-xs">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-8 text-center space-y-2">
          <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">
            Belum ada riwayat pembayaran yang tercatat untuk tagihan ini.
          </p>
          <p class="text-xs text-slate-400">
            Gunakan tombol "Bayar Tagihan Ini" untuk mencatat pembayaran dan menyimpan bukti transfer.
          </p>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
          <RdButton variant="outline" size="sm" @click="historyModalOpen = false">
            Tutup
          </RdButton>
          <RdButton variant="primary" size="sm" @click="openPayFromHistory">
            Bayar Tagihan Ini
          </RdButton>
        </div>
      </div>
    </RdModal>

    <!-- 3. Modal Rincian Tagihan -->
    <RdModal
      v-model="detailModalOpen"
      :title="`Rincian Tagihan: ${detailReminder?.title || ''}`"
      description="Informasi menyeluruh mengenai jadwal tagihan atau kewajiban berkala"
      max-width="lg"
    >
      <div v-if="detailReminder" class="space-y-4">
        <!-- Grid Informasi -->
        <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg text-xs">
          <div>
            <span class="text-slate-400 block">Nama Tagihan:</span>
            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ detailReminder.title }}</span>
          </div>
          <div>
            <span class="text-slate-400 block">Status Tagihan:</span>
            <RdBadge :variant="statusVariant(detailReminder.status)" class="mt-0.5">
              {{ statusLabel(detailReminder.status) }}
            </RdBadge>
          </div>
          <div>
            <span class="text-slate-400 block">Kategori:</span>
            <RdBadge variant="neutral" class="mt-0.5">
              {{ detailReminder.category_name || detailReminder.category?.name || 'Tanpa Kategori' }}
            </RdBadge>
          </div>
          <div>
            <span class="text-slate-400 block">Tanggal Jatuh Tempo:</span>
            <span class="font-medium text-slate-800 dark:text-slate-200 block mt-0.5">
              <RdDateTime :value="detailReminder.due_date" format="date" />
            </span>
          </div>
          <div>
            <span class="text-slate-400 block">Siklus Perulangan:</span>
            <span class="font-medium text-slate-800 dark:text-slate-200 block mt-0.5">
              {{ repeatLabel(detailReminder.repeat_interval) }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 block">Tenor:</span>
            <span v-if="detailReminder.total_tenor" class="font-mono font-medium text-slate-800 dark:text-slate-200 block mt-0.5">
              {{ detailReminder.current_tenor || 1 }} dari {{ detailReminder.total_tenor }}
            </span>
            <span v-else class="text-slate-500 font-medium block mt-0.5">
              Rutin / Bukan Cicilan
            </span>
          </div>
          <div class="col-span-2 pt-2 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <span class="text-slate-500 font-medium">Nominal Tagihan Pokok:</span>
            <span class="text-base font-bold text-slate-900 dark:text-slate-100">
              <RdMoney :amount-minor="detailReminder.amount_minor" />
            </span>
          </div>
        </div>

        <!-- Deskripsi / Catatan Tambahan -->
        <div class="text-xs">
          <span class="text-slate-400 font-medium block mb-1">Catatan Tambahan:</span>
          <p class="text-slate-700 dark:text-slate-300 p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded">
            {{ detailReminder.description || 'Tidak ada catatan tambahan.' }}
          </p>
        </div>

        <!-- Lampiran Berkas Tagihan Asli -->
        <div class="text-xs">
          <span class="text-slate-400 font-medium block mb-1.5">Berkas Lampiran Tagihan:</span>
          <div
            v-if="detailReminder.images && detailReminder.images.length > 0"
            class="flex items-center gap-2 flex-wrap"
          >
            <button
              v-for="(img, idx) in detailReminder.images"
              :key="idx"
              type="button"
              @click="openPreview(img.url, `${detailReminder.title} - Berkas ${idx + 1}`)"
              class="relative rounded border border-slate-200 dark:border-slate-700 overflow-hidden w-14 h-14 bg-slate-100 dark:bg-slate-800 shrink-0 hover:opacity-90 transition-opacity"
              :title="img.name || `Berkas ${idx + 1}`"
            >
              <img :src="img.url" :alt="img.name || 'Bukti tagihan'" class="w-full h-full object-cover" />
            </button>
          </div>
          <p v-else class="text-slate-400 italic">
            Tidak ada berkas lampiran yang diunggah untuk tagihan ini.
          </p>
        </div>

        <!-- Footer Tombol Aksi -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
          <RdButton variant="outline" size="sm" @click="detailModalOpen = false">
            Tutup
          </RdButton>
          <div class="flex items-center gap-2">
            <a :href="`/t/${tenantSlug}/apps/pekan/reminders/${detailReminder.id}/edit`">
              <RdButton variant="outline" size="sm">
                Ubah Tagihan
              </RdButton>
            </a>
            <RdButton variant="primary" size="sm" @click="openPayFromDetail">
              Bayar Sekarang
            </RdButton>
          </div>
        </div>
      </div>
    </RdModal>

    <!-- 4. Modal Lightbox Pratinjau Foto Lampiran -->
    <RdModal
      v-model="previewModalOpen"
      :title="previewImage.title"
      max-width="lg"
    >
      <div class="space-y-4">
        <div class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-black/5 dark:bg-black/30 flex items-center justify-center min-h-[240px]">
          <img
            :src="previewImage.url"
            :alt="previewImage.title"
            class="max-h-[65vh] w-auto max-w-full object-contain mx-auto rounded"
          />
        </div>
        <!-- Thumbnail jika ada beberapa gambar -->
        <div
          v-if="previewImage.images && previewImage.images.length > 1"
          class="flex items-center gap-2 overflow-x-auto pb-1"
        >
          <button
            v-for="(subImg, sIdx) in previewImage.images"
            :key="sIdx"
            type="button"
            @click="previewImage.url = subImg.url"
            :class="[
              'w-12 h-12 rounded border-2 overflow-hidden shrink-0 transition-all cursor-pointer',
              previewImage.url === subImg.url ? 'border-teal-600 scale-105' : 'border-slate-200 dark:border-slate-700 opacity-60 hover:opacity-100'
            ]"
            :title="subImg.name || `Lampiran ${sIdx + 1}`"
          >
            <img :src="subImg.url" :alt="subImg.name || `Gambar ${sIdx + 1}`" class="w-full h-full object-cover" />
          </button>
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
          <span>Pratinjau Berkas Lampiran</span>
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

    <!-- 5. Dialog Konfirmasi Hapus Tagihan -->
    <RdConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Hapus Jadwal Pengingat Tagihan?"
      :message="`Jadwal pengingat '${reminderToDelete?.title || 'ini'}' akan dihapus. Riwayat transaksi mutasi yang sudah dicatat sebelumnya di buku kas tidak akan terhapus.`"
      danger
      confirm-text="Ya, Hapus Tagihan"
      :loading="isDeleting"
      @confirm="handleDelete"
    />
  </RudimahAppShell>
</template>
