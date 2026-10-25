<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceBudget, FinanceCategory } from '../../../types/pekan';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  categories?: FinanceCategory[];
  budget: FinanceBudget;
}>();

const isSubmitting = ref(false);
const name = ref(props.budget.name || '');
const categoryId = ref(props.budget.category_id || (props.categories?.[0]?.id || ''));
const amountLimitMinor = ref(props.budget.amount_limit_minor || 1000000);
const period = ref<'weekly' | 'monthly' | 'yearly'>((props.budget.period as any) || 'monthly');
const startDate = ref(props.budget.start_date ? props.budget.start_date.substring(0, 10) : new Date().toISOString().substring(0, 10));
const alertThresholdPct = ref(props.budget.alert_threshold_pct || 80);
const notes = ref(props.budget.notes || '');

const periodOptions = [
  { label: 'Mingguan (Weekly)', value: 'weekly' },
  { label: 'Bulanan (Monthly)', value: 'monthly' },
  { label: 'Tahunan (Yearly)', value: 'yearly' },
];

const categoryOptions = (props.categories || []).map(c => ({
  label: c.name,
  value: c.id,
}));

function handleSubmit() {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  router.put(`/t/${props.tenant.slug}/apps/pekan/budgets/${props.budget.id}`, {
    name: name.value,
    category_id: categoryId.value,
    amount_limit_minor: Number(amountLimitMinor.value),
    period: period.value,
    start_date: startDate.value,
    alert_threshold_pct: Number(alertThresholdPct.value),
    notes: notes.value,
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
      title="Ubah Anggaran Belanja"
      description="Perbarui batas plafon, periode, atau kategori pengeluaran anggaran"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Anggaran', href: `/t/${tenant.slug}/apps/pekan/budgets` },
        { label: 'Ubah' }
      ]"
    />

    <form @submit.prevent="handleSubmit" class="max-w-2xl">
      <RdCard title="Informasi Anggaran">
        <div class="space-y-4">
          <RdInput
            id="name"
            v-model="name"
            label="Nama Anggaran"
            placeholder="Contoh: Belanja Bulanan Supermarket"
            required
          />

          <RdSelect
            id="category_id"
            v-model="categoryId"
            label="Kategori Pengeluaran Terkait"
            :options="categoryOptions"
            required
          />

          <RdInput
            id="amount_limit_minor"
            v-model.number="amountLimitMinor"
            type="number"
            min="1000"
            label="Batas Maksimal Plafon (Rupiah)"
            required
          />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <RdSelect
              id="period"
              v-model="period"
              label="Periode Anggaran"
              :options="periodOptions"
              required
            />

            <RdInput
              id="start_date"
              v-model="startDate"
              type="date"
              label="Tanggal Mulai Berlaku"
              required
            />
          </div>

          <RdInput
            id="alert_threshold_pct"
            v-model.number="alertThresholdPct"
            type="number"
            min="10"
            max="100"
            label="Ambang Peringatan / Alert Threshold (%)"
            description="Sistem akan memberikan sinyal peringatan bila realisasi telah mencapai persentase ini"
            required
          />

          <RdTextarea
            id="notes"
            v-model="notes"
            label="Catatan Anggaran"
            placeholder="Keterangan tambahan atau pos pembagian anggaran..."
            :rows="3"
          />

          <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <RdButton
              type="submit"
              variant="primary"
              size="md"
              :loading="isSubmitting"
            >
              Simpan Perubahan
            </RdButton>
            <a :href="`/t/${tenant.slug}/apps/pekan/budgets`">
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
