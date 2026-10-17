<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdTextarea from '../../../design-system/RdTextarea.vue';
import type { User, Tenant, AppManifest } from '../../../types/core';

const props = defineProps<{
  user: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
}>();

const form = useForm({
  name: '',
  target_amount_minor: 10000000,
  current_amount_minor: 0,
  target_date: '',
  notes: '',
});

function submit() {
  form.post(`/t/${props.tenant.slug}/apps/pekan/savings`);
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
      title="Buat Pos Tabungan Baru"
      description="Rencanakan target akumulasi simpanan dan impian keuangan keluarga secara terukur"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Tabungan', href: `/t/${tenant.slug}/apps/pekan/savings` },
        { label: 'Buat Baru' }
      ]"
    />

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <RdCard title="Informasi Target Tabungan">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Nama Impian / Pos Tabungan
            </label>
            <RdInput
              v-model="form.name"
              placeholder="Contoh: Dana Darurat 6 Bulan, Liburan Akhir Tahun, Renovasi Rumah"
              required
            />
            <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Target Nominal Dana (Rp)
              </label>
              <RdInput
                v-model.number="form.target_amount_minor"
                type="number"
                min="10000"
                step="10000"
                required
              />
              <p v-if="form.errors.target_amount_minor" class="text-xs text-red-600 mt-1">{{ form.errors.target_amount_minor }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Saldo Awal Yang Terkumpul (Rp)
              </label>
              <RdInput
                v-model.number="form.current_amount_minor"
                type="number"
                min="0"
                step="10000"
              />
              <span class="text-[11px] text-slate-400 mt-0.5 block">Isi 0 jika tabungan dimulai dari awal</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Target Tenggat Waktu Tercapai (Opsional)
            </label>
            <RdInput
              v-model="form.target_date"
              type="date"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Catatan / Motivasi Finansial
            </label>
            <RdTextarea
              v-model="form.notes"
              rows="3"
              placeholder="Tuliskan catatan atau motivasi untuk pos tabungan ini..."
            />
          </div>
        </div>
      </RdCard>

      <div class="flex items-center justify-end gap-3 pt-2">
        <a :href="`/t/${tenant.slug}/apps/pekan/savings`">
          <RdButton variant="outline" type="button">
            Batal
          </RdButton>
        </a>
        <RdButton type="submit" variant="primary" :loading="form.processing">
          Simpan Pos Tabungan
        </RdButton>
      </div>
    </form>
  </RudimahAppShell>
</template>
