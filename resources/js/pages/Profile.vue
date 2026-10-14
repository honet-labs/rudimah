<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RudimahAppShell from '../layouts/RudimahAppShell.vue';
import RdPageHeader from '../design-system/RdPageHeader.vue';
import RdCard from '../design-system/RdCard.vue';
import RdButton from '../design-system/RdButton.vue';
import RdInput from '../design-system/RdInput.vue';
import RdBadge from '../design-system/RdBadge.vue';
import RdThemeToggle from '../design-system/RdThemeToggle.vue';
import type { User, Tenant } from '../types/core';

const props = defineProps<{
  user: User;
  tenant: Tenant;
}>();

const form = useForm({
  name: props.user.name,
});

function submitProfile() {
  form.post('/profile');
}
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant">
    <RdPageHeader
      title="Pengaturan Profil dan Keamanan"
      description="Kelola informasi pribadi, sesi login, dan autentikasi multi-faktor (MFA)"
      :breadcrumbs="[{ label: 'Beranda', href: '/' }, { label: 'Profil' }]"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Informasi Akun -->
      <RdCard title="Informasi Pribadi" description="Data akun yang digunakan untuk masuk ke platform">
        <form @submit.prevent="submitProfile" class="space-y-4">
          <RdInput
            id="name"
            name="name"
            v-model="form.name"
            label="Nama Lengkap"
            :error="form.errors.name"
            required
          />

          <RdInput
            id="email"
            name="email"
            :model-value="user.email"
            type="email"
            label="Alamat Email"
            disabled
            hint="Email digunakan sebagai identitas akun dan tidak dapat diganti sembarangan"
          />

          <div class="pt-2">
            <RdButton type="submit" variant="primary" size="sm" :loading="form.processing">
              Simpan Profil
            </RdButton>
          </div>
        </form>
      </RdCard>

      <!-- Keamanan Akun & MFA -->
      <RdCard title="Autentikasi Multi-Faktor (MFA)" description="Lapisan keamanan tambahan untuk melindungi akun Anda">
        <div class="space-y-4 text-xs">
          <div class="p-3 border rounded-md flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-100">
                Aplikasi Autentikator (TOTP)
              </p>
              <p class="text-slate-500">
                Google Authenticator, Aegis, atau 1Password
              </p>
            </div>
            <RdBadge :variant="user.has_mfa ? 'success' : 'neutral'">
              {{ user.has_mfa ? 'Aktif' : 'Belum Aktif' }}
            </RdBadge>
          </div>

          <div class="p-3 border rounded-md flex items-center justify-between">
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-100">
                Kunci Akses (Passkey / WebAuthn)
              </p>
              <p class="text-slate-500">
                Biometrik sidik jari atau kunci keamanan fisik
              </p>
            </div>
            <RdBadge variant="neutral">
              Tersedia
            </RdBadge>
          </div>

          <div class="pt-2 flex gap-2">
            <RdButton variant="outline" size="sm">
              Atur Ulang Kredensial MFA
            </RdButton>
            <RdButton variant="outline" size="sm">
              Lihat Kode Pemulihan
            </RdButton>
          </div>
        </div>
      </RdCard>

      <!-- Preferensi Tampilan Antarmuka -->
      <RdCard title="Preferensi Tampilan" description="Pilih tema tampilan antarmuka yang nyaman untuk mata Anda" class="md:col-span-2">
        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-800">
          <div>
            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
              Tema Terang / Gelap
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Sesuaikan kontras tampilan secara instan atau ikuti preferensi sistem operasi perangkat Anda
            </p>
          </div>
          <RdThemeToggle show-label size="md" />
        </div>
      </RdCard>
    </div>
  </RudimahAppShell>
</template>
