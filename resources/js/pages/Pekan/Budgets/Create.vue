<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { FinanceCategory } from '../../../types/pekan';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  categories: FinanceCategory[];
}>();

const form = useForm({
  name: '',
  category_id: props.categories[0]?.id || '',
  amount_limit_minor: 1000000,
  period: 'monthly',
  start_date: new Date().toISOString().substring(0, 10),
  alert_threshold_pct: 80,
  notes: '',
});

const categoryOptions = props.categories.map((c) => ({
  label: c.name,
  value: c.id,
}));

const periodOptions = [
  { label: 'Bulanan (Monthly)', value: 'monthly' },
  { label: 'Mingguan (Weekly)', value: 'weekly' },
  { label: 'Tahunan (Yearly)', value: 'yearly' },
];

function submit() {
  form.post(`/t/${props.tenant.slug}/apps/pekan/budgets`);
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
      title="Buat Anggaran Baru"
      description="Tetapkan batas maksimal pengeluaran per kategori agar kondisi finansial tetap terkelola"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Anggaran', href: `/t/${tenant.slug}/apps/pekan/budgets` },
        { label: 'Buat Baru' }
      ]"
    />

    <form @submit.prevent="submit" class="space-y-6 max-w-3xl">
      <RdCard title="Informasi Batas Anggaran">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Nama Pos Anggaran
            </label>
            <RdInput
              v-model="form.name"
              placeholder="Contoh: Belanja Bahan Makanan, Tagihan Utilitas, Rekreasi"
              required
            />
            <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Kategori Belanja Terkait
              </label>
              <RdSelect
                v-model="form.category_id"
                :options="categoryOptions"
                required
              />
              <p v-if="form.errors.category_id" class="text-xs text-red-600 mt-1">{{ form.errors.category_id }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Batas Nominal Anggaran (Rp)
              </label>
              <RdInput
                v-model.number="form.amount_limit_minor"
                type="number"
                min="1000"
                step="1000"
                required
              />
              <p v-if="form.errors.amount_limit_minor" class="text-xs text-red-600 mt-1">{{ form.errors.amount_limit_minor }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Periode Alokasi
              </label>
              <RdSelect
                v-model="form.period"
                :options="periodOptions"
                required
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Tanggal Mulai Efektif
              </label>
              <RdInput
                v-model="form.start_date"
                type="date"
                required
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Ambang Batas Peringatan (%)
              </label>
              <RdInput
                v-model.number="form.alert_threshold_pct"
                type="number"
                min="10"
                max="100"
                required
              />
              <span class="text-[11px] text-slate-400 mt-0.5 block">Kirim alert saat belanja mencapai % ini</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Catatan / Instruksi Anggaran (Opsional)
            </label>
            <RdTextarea
              v-model="form.notes"
              rows="3"
              placeholder="Catatan tambahan mengenai batas pos belanja ini..."
            />
          </div>
        </div>
      </RdCard>

      <div class="flex items-center justify-end gap-3 pt-2">
        <a :href="`/t/${tenant.slug}/apps/pekan/budgets`">
          <RdButton variant="outline" type="button">
            Batal
          </RdButton>
        </a>
        <RdButton type="submit" variant="primary" :loading="form.processing">
          Simpan Anggaran
        </RdButton>
      </div>
    </form>
  </RudimahAppShell>
</template>
