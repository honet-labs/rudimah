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
import RdImageUploadMultiple from '../../../design-system/RdImageUploadMultiple.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceReminder } from '../../../types/pekan';

interface CategoryOption {
  id: string;
  name: string;
}

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  reminder: FinanceReminder;
  categories?: CategoryOption[];
}>();

const isSubmitting = ref(false);
const title = ref(props.reminder.title || '');
const categoryId = ref(props.reminder.category_id || '');
const amountMinor = ref(props.reminder.amount_minor || 100000);
const dueDate = ref(props.reminder.due_date ? props.reminder.due_date.substring(0, 10) : new Date().toISOString().substring(0, 10));
const repeatInterval = ref(props.reminder.repeat_interval || 'monthly');
const isInstallment = ref(!!props.reminder.total_tenor);
const totalTenor = ref(props.reminder.total_tenor || 12);
const currentTenor = ref(props.reminder.current_tenor || 1);
const description = ref(props.reminder.description || '');
const existingImages = ref<any[]>(props.reminder.images || []);
const newFiles = ref<File[]>([]);

const categoryOptions = computed(() => [
  { label: 'Tanpa Kategori', value: '' },
  ...(props.categories || []).map(c => ({ label: c.name, value: c.id })),
]);

const repeatOptions = [
  { label: 'Satu Kali (Once)', value: 'once' },
  { label: 'Harian (Daily)', value: 'daily' },
  { label: 'Mingguan (Weekly)', value: 'weekly' },
  { label: 'Bulanan (Monthly)', value: 'monthly' },
  { label: 'Tahunan (Yearly)', value: 'yearly' },
];

const copiedId = ref<string | null>(null);
function copyId(id: string) {
  if (!id) return;
  navigator.clipboard.writeText(id);
  copiedId.value = id;
  setTimeout(() => {
    if (copiedId.value === id) copiedId.value = null;
  }, 2000);
}

function handleSubmit() {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  router.post(`/t/${props.tenant.slug}/apps/pekan/reminders/${props.reminder.id}`, {
    _method: 'PUT',
    category_id: categoryId.value || null,
    title: title.value,
    amount_minor: Number(amountMinor.value),
    due_date: dueDate.value,
    repeat_interval: repeatInterval.value,
    is_installment: isInstallment.value,
    total_tenor: isInstallment.value && totalTenor.value ? Number(totalTenor.value) : null,
    current_tenor: isInstallment.value && currentTenor.value ? Number(currentTenor.value) : null,
    description: description.value,
    existing_images: JSON.stringify(existingImages.value),
    images: newFiles.value,
  }, {
    forceFormData: true,
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Ubah Pengingat Tagihan"
      description="Perbarui tanggal jatuh tempo, nominal, siklus berulang, atau tenor tagihan"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Pengingat', href: `/t/${tenant.slug}/apps/pekan/reminders` },
        { label: 'Ubah' }
      ]"
    />

    <form @submit.prevent="handleSubmit" class="max-w-2xl">
      <RdCard title="Informasi Tagihan / Kewajiban">
        <div class="space-y-4">
          <!-- ID Tagihan (Primary Key) -->
          <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-md border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <span class="block text-[11px] font-medium text-slate-500 dark:text-slate-400">ID Tagihan (Primary Key)</span>
              <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100 select-all truncate block">{{ reminder.id }}</span>
            </div>
            <RdButton type="button" variant="outline" size="sm" class="shrink-0" @click="copyId(reminder.id)">
              {{ copiedId === reminder.id ? 'Tersalin' : 'Salin ID' }}
            </RdButton>
          </div>

          <RdInput
            id="title"
            v-model="title"
            label="Nama Tagihan / Kewajiban"
            placeholder="Contoh: Tagihan Listrik PLN Pasca Bayar"
            required
          />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <RdInput
              id="amount_minor"
              v-model.number="amountMinor"
              type="number"
              min="1000"
              label="Nominal Tagihan (Rp)"
              required
            />

            <RdInput
              id="due_date"
              v-model="dueDate"
              type="date"
              label="Tanggal Jatuh Tempo"
              required
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Kategori Tagihan
              </label>
              <RdSelect
                id="category_id"
                v-model="categoryId"
                :options="categoryOptions"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Frekuensi Berulang
              </label>
              <RdSelect
                id="repeat_interval"
                v-model="repeatInterval"
                :options="repeatOptions"
                required
              />
            </div>
          </div>

          <!-- Pilihan Cicilan / Tenor -->
          <div class="p-3 border border-slate-200 dark:border-slate-800 rounded-lg bg-slate-50 dark:bg-slate-900/50 space-y-3">
            <div class="flex items-center gap-2">
              <input
                id="is_installment"
                v-model="isInstallment"
                type="checkbox"
                class="rounded border-slate-300 dark:border-slate-700 text-teal-600 focus:ring-teal-500"
                @change="() => {
                  if (isInstallment && !totalTenor) {
                    totalTenor = 12;
                    currentTenor = 1;
                  }
                }"
              />
              <label for="is_installment" class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                Ini adalah cicilan dengan batas tenor (misal: kredit laptop, KPR)
              </label>
            </div>

            <div v-if="isInstallment" class="grid grid-cols-2 gap-4 pt-1">
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                  Tenor Berjalan Saat Ini
                </label>
                <RdInput
                  id="current_tenor"
                  v-model.number="currentTenor"
                  type="number"
                  min="1"
                />
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                  Total Seluruh Tenor
                </label>
                <RdInput
                  id="total_tenor"
                  v-model.number="totalTenor"
                  type="number"
                  min="1"
                />
              </div>
            </div>
          </div>

          <RdTextarea
            id="description"
            v-model="description"
            label="Keterangan / Nomor Pelanggan"
            placeholder="Contoh: ID Pelanggan PLN 531234567890..."
            :rows="3"
          />

          <!-- Unggah / Kelola Bukti Tagihan (Multiple Images) -->
          <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
            <RdImageUploadMultiple
              v-model="newFiles"
              v-model:existing-images="existingImages"
              label="Bukti Tagihan / Berkas Invoice (Bisa Banyak Foto)"
              description="Perbarui atau tambahkan foto tagihan, invoice, atau struk pembayaran (JPG, PNG, WEBP maks 5MB)"
            />
          </div>

          <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <RdButton
              type="submit"
              variant="primary"
              size="md"
              :loading="isSubmitting"
            >
              Simpan Perubahan
            </RdButton>
            <a :href="`/t/${tenant.slug}/apps/pekan/reminders`">
              <RdButton
                type="button"
                variant="outline"
                size="md"
              >
                Batal
              </RdButton>
            </a>
          </div>
        </div>
      </RdCard>
    </form>
  </RudimahAppShell>
</template>
