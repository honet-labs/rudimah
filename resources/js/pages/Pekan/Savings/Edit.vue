<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import RdImageUploadMultiple from '../../../design-system/RdImageUploadMultiple.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceSavings } from '../../../types/pekan';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  saving: FinanceSavings;
}>();

const isSubmitting = ref(false);
const name = ref(props.saving.name || '');
const targetAmountMinor = ref(props.saving.target_amount_minor || 10000000);
const currentAmountMinor = ref(props.saving.current_amount_minor || 0);
const targetDate = ref(props.saving.target_date ? props.saving.target_date.substring(0, 10) : '');
const notes = ref(props.saving.notes || '');
const existingImages = ref<any[]>(props.saving.images || []);
const newFiles = ref<File[]>([]);

function handleSubmit() {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  router.post(`/t/${props.tenant.slug}/apps/pekan/savings/${props.saving.id}`, {
    _method: 'PUT',
    name: name.value,
    target_amount_minor: Number(targetAmountMinor.value),
    current_amount_minor: Number(currentAmountMinor.value),
    target_date: targetDate.value || null,
    notes: notes.value,
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
      title="Ubah Pos Tabungan Impian"
      description="Perbarui target akumulasi dana, saldo saat ini, atau tenggat waktu"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Tabungan', href: `/t/${tenant.slug}/apps/pekan/savings` },
        { label: 'Ubah' }
      ]"
    />

    <form @submit.prevent="handleSubmit" class="max-w-2xl">
      <RdCard title="Informasi Target Tabungan">
        <div class="space-y-4">
          <RdInput
            id="name"
            v-model="name"
            label="Nama Pos Tabungan / Impian"
            placeholder="Contoh: Dana Darurat 6 Bulan"
            required
          />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <RdInput
              id="target_amount_minor"
              v-model.number="targetAmountMinor"
              type="number"
              min="10000"
              label="Target Nominal Total (Rp)"
              required
            />

            <RdInput
              id="current_amount_minor"
              v-model.number="currentAmountMinor"
              type="number"
              min="0"
              label="Saldo Awal / Saat Ini (Rp)"
            />
          </div>

          <RdInput
            id="target_date"
            v-model="targetDate"
            type="date"
            label="Target Waktu Pencapaian"
            description="Perkiraan tanggal target dana impian harus terkumpul penuh"
          />

          <RdTextarea
            id="notes"
            v-model="notes"
            label="Catatan Rencana"
            placeholder="Informasi rekening simpanan atau strategi alokasi..."
            :rows="3"
          />

          <!-- Upload / Kelola Foto Target Impian / Bukti (Multiple Images) -->
          <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
            <RdImageUploadMultiple
              v-model="newFiles"
              v-model:existing-images="existingImages"
              label="Foto Target Impian / Bukti Simpanan (Bisa Banyak Foto)"
              description="Perbarui atau tambahkan gambar barang impian, tujuan wisata, atau dokumen tabungan (JPG, PNG, WEBP maks 5MB)"
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
            <a :href="`/t/${tenant.slug}/apps/pekan/savings`">
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
