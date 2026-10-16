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

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
}>();

const form = useForm({
  title: '',
  amount_minor: 500000,
  due_date: new Date().toISOString().substring(0, 10),
  repeat_interval: 'monthly',
  is_installment: false,
  total_tenor: 12,
  current_tenor: 1,
  description: '',
});

const repeatOptions = [
  { label: 'Bulanan (Setiap Bulan)', value: 'monthly' },
  { label: 'Tahunan (Setiap Tahun)', value: 'yearly' },
  { label: 'Mingguan (Setiap Minggu)', value: 'weekly' },
  { label: 'Hanya Sekali (Non-rutin)', value: 'once' },
];

function submit() {
  form.post(`/t/${props.tenant.slug}/apps/pekan/reminders`);
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
      title="Tambah Jadwal Pengingat Tagihan"
      description="Catat kewajiban pembayaran berkala atau cicilan agar tidak terlewat jatuh tempo"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Pengingat', href: `/t/${tenant.slug}/apps/pekan/reminders` },
        { label: 'Tambah Baru' }
      ]"
    />

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <RdCard title="Informasi Tagihan / Kewajiban">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Nama Tagihan / Kewajiban
            </label>
            <RdInput
              v-model="form.title"
              placeholder="Contoh: Tagihan Listrik PLN, WiFi Rumah, Asuransi BPJS, Cicilan KPR"
              required
            />
            <p v-if="form.errors.title" class="text-xs text-red-600 mt-1">{{ form.errors.title }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Nominal Tagihan (Rp)
              </label>
              <RdInput
                v-model.number="form.amount_minor"
                type="number"
                min="1000"
                step="1000"
                required
              />
              <p v-if="form.errors.amount_minor" class="text-xs text-red-600 mt-1">{{ form.errors.amount_minor }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Tanggal Jatuh Tempo
              </label>
              <RdInput
                v-model="form.due_date"
                type="date"
                required
              />
              <p v-if="form.errors.due_date" class="text-xs text-red-600 mt-1">{{ form.errors.due_date }}</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Siklus Perulangan
            </label>
            <RdSelect
              v-model="form.repeat_interval"
              :options="repeatOptions"
              required
            />
          </div>

          <!-- Pilihan Cicilan / Tenor -->
          <div class="p-3 border border-slate-200 dark:border-slate-800 rounded-lg bg-slate-50 dark:bg-slate-900/50 space-y-3">
            <div class="flex items-center gap-2">
              <input
                id="is_installment"
                v-model="form.is_installment"
                type="checkbox"
                class="rounded border-slate-300 dark:border-slate-700 text-teal-600 focus:ring-teal-500"
              />
              <label for="is_installment" class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                Ini adalah cicilan dengan batas tenor (misal: kredit laptop, KPR)
              </label>
            </div>

            <div v-if="form.is_installment" class="grid grid-cols-2 gap-4 pt-1">
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                  Tenor Berjalan Saat Ini
                </label>
                <RdInput
                  v-model.number="form.current_tenor"
                  type="number"
                  min="1"
                />
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                  Total Seluruh Tenor
                </label>
                <RdInput
                  v-model.number="form.total_tenor"
                  type="number"
                  min="1"
                />
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Keterangan / Nomor Pelanggan (Opsional)
            </label>
            <RdTextarea
              v-model="form.description"
              rows="2"
              placeholder="Contoh: No. ID Pelanggan PLN 5234xxx atau VA pembayaran..."
            />
          </div>
        </div>
      </RdCard>

      <div class="flex items-center justify-end gap-3 pt-2">
        <a :href="`/t/${tenant.slug}/apps/pekan/reminders`">
          <RdButton variant="outline" type="button">
            Batal
          </RdButton>
        </a>
        <RdButton type="submit" variant="primary" :loading="form.processing">
          Jadwalkan Pengingat
        </RdButton>
      </div>
    </form>
  </RudimahAppShell>
</template>
