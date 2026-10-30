<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import RudimahAppShell from '../layouts/RudimahAppShell.vue';
import RdPageHeader from '../design-system/RdPageHeader.vue';
import RdCard from '../design-system/RdCard.vue';
import RdButton from '../design-system/RdButton.vue';
import RdInput from '../design-system/RdInput.vue';
import RdSelect from '../design-system/RdSelect.vue';
import RdBadge from '../design-system/RdBadge.vue';
import RdThemeToggle from '../design-system/RdThemeToggle.vue';
import RdAlert from '../design-system/RdAlert.vue';
import type { User, Tenant } from '../types/core';

interface TenantOption {
  id: string;
  name: string;
  slug: string;
}

interface ChatSession {
  id: string;
  channel: string;
  channel_user_id: string;
  tenant_id: string;
  tenant_name: string;
  last_interaction_at?: string;
  created_at?: string;
}

interface ActivePairing {
  code: string;
  tenant_id: string;
  tenant_name: string;
  expires_at: string;
  minutes_remaining: number;
}

const props = withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    userTenants?: TenantOption[];
    chatSessions?: ChatSession[];
    activePairing?: ActivePairing | null;
    botInfo?: {
      telegram_username?: string;
      whatsapp_enabled?: boolean;
    };
    status?: string;
    error?: string;
  }>(),
  {
    userTenants: () => [],
    chatSessions: () => [],
    activePairing: null,
    botInfo: () => ({}),
  }
);

const form = useForm({
  name: props.user.name,
});

function submitProfile() {
  form.post('/profile');
}

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

function submitPassword() {
  passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset();
    },
  });
}

// Integrasi Chat (Web Pairing)
const initialTenantId = props.userTenants && props.userTenants.length > 0
  ? props.userTenants[0].id
  : (props.tenant?.id || '');

const pairingForm = useForm({
  tenant_id: initialTenantId,
  channel: 'any',
});

const tenantSelectOptions = computed(() => {
  return props.userTenants.map(t => ({
    value: t.id,
    label: `${t.name} (${t.slug})`,
  }));
});

function submitPairing() {
  pairingForm.post('/profile/chat-pairings', {
    preserveScroll: true,
  });
}

const deletingSessionId = ref<string | null>(null);

function disconnectSession(sessionId: string) {
  if (confirm('Apakah Anda yakin ingin memutuskan tautan nomor/akun chat ini dari sistem RUDIMAH?')) {
    deletingSessionId.value = sessionId;
    router.delete(`/profile/chat-sessions/${sessionId}`, {
      preserveScroll: true,
      onFinish: () => {
        deletingSessionId.value = null;
      },
    });
  }
}
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant">
    <RdPageHeader
      title="Pengaturan Profil dan Keamanan"
      description="Kelola informasi pribadi, sesi login, pembaruan kata sandi, dan autentikasi multi-faktor (MFA)"
      :breadcrumbs="[{ label: 'Beranda', href: '/' }, { label: 'Profil' }]"
    />

    <!-- Feedback Banner Notifikasi Sukses / Gagal -->
    <div v-if="status" class="mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-6">
      <RdAlert variant="error" title="Perhatian / Kendala Sistem">
        {{ error }}
      </RdAlert>
    </div>

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

      <!-- Pembaruan Kata Sandi Akun -->
      <RdCard title="Pembaruan Kata Sandi" description="Pastikan akun Anda menggunakan kata sandi yang kuat dan aman">
        <form @submit.prevent="submitPassword" class="space-y-4">
          <RdInput
            id="current_password"
            name="current_password"
            v-model="passwordForm.current_password"
            type="password"
            label="Kata Sandi Saat Ini"
            placeholder="Masukkan kata sandi lama"
            :error="passwordForm.errors.current_password"
            required
          />

          <RdInput
            id="new_password"
            name="password"
            v-model="passwordForm.password"
            type="password"
            label="Kata Sandi Baru"
            placeholder="Minimal 8 karakter"
            :error="passwordForm.errors.password"
            required
          />

          <RdInput
            id="new_password_confirmation"
            name="password_confirmation"
            v-model="passwordForm.password_confirmation"
            type="password"
            label="Konfirmasi Kata Sandi Baru"
            placeholder="Ulangi kata sandi baru"
            :error="passwordForm.errors.password_confirmation"
            required
          />

          <div class="pt-2">
            <RdButton type="submit" variant="primary" size="sm" :loading="passwordForm.processing">
              Perbarui Kata Sandi
            </RdButton>
          </div>
        </form>
      </RdCard>

      <!-- Integrasi Asisten Chat (WhatsApp & Telegram) -->
      <RdCard
        title="Integrasi Asisten Chat (WhatsApp & Telegram)"
        description="Hubungkan nomor WhatsApp atau akun Telegram Anda menggunakan metode Web Pairing yang aman untuk mengakses Asisten AI RUDIMAH tanpa mengetikkan kata sandi di chat."
        class="md:col-span-2"
      >
        <div class="space-y-6 text-sm">
          <!-- Daftar Sesi Chat yang Sedang Terhubung -->
          <div>
            <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
              Tautan Obrolan Aktif ({{ chatSessions.length }})
            </h4>

            <div v-if="chatSessions.length > 0" class="space-y-3">
              <div
                v-for="s in chatSessions"
                :key="s.id"
                class="p-4 border rounded-lg border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900"
              >
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <RdBadge :variant="s.channel === 'whatsapp' ? 'success' : 'neutral'">
                      {{ s.channel === 'whatsapp' ? 'WhatsApp (WAHA)' : 'Telegram Bot' }}
                    </RdBadge>
                    <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">
                      {{ s.channel_user_id }}
                    </span>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    Terhubung ke Ruang Kerja: <strong class="text-slate-700 dark:text-slate-200">{{ s.tenant_name }}</strong>
                    <span v-if="s.last_interaction_at" class="ml-2">
                      (Terakhir aktif {{ s.last_interaction_at }})
                    </span>
                  </div>
                </div>

                <div>
                  <RdButton
                    variant="outline"
                    size="sm"
                    :loading="deletingSessionId === s.id"
                    @click="disconnectSession(s.id)"
                  >
                    Putuskan Tautan
                  </RdButton>
                </div>
              </div>
            </div>

            <div v-else class="p-4 rounded-md border border-dashed border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500">
              Belum ada nomor WhatsApp atau akun Telegram yang terhubung dengan akun Anda.
            </div>
          </div>

          <!-- Tampilan Kode Pairing Aktif (Jika Ada) -->
          <div
            v-if="activePairing"
            class="p-5 rounded-lg border border-teal-500/30 bg-teal-50/50 dark:bg-teal-950/20 space-y-3"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-teal-800 dark:text-teal-300">
                Kode Hubung Sekali Pakai (Pairing Code)
              </span>
              <span class="text-xs text-slate-500 dark:text-slate-400">
                Berlaku ~{{ activePairing.minutes_remaining }} menit lagi
              </span>
            </div>

            <div class="py-2 text-center bg-white dark:bg-slate-900 rounded border border-teal-200 dark:border-teal-900">
              <div class="font-mono text-3xl font-extrabold tracking-widest text-teal-800 dark:text-teal-200">
                {{ activePairing.code }}
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Ruang Kerja Target: <strong class="text-slate-700 dark:text-slate-200">{{ activePairing.tenant_name }}</strong>
              </p>
            </div>

            <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
              <p class="font-medium">Petunjuk Penggunaan:</p>
              <ol class="list-decimal list-inside space-y-0.5 text-slate-500 dark:text-slate-400">
                <li>Buka aplikasi WhatsApp atau Telegram Anda.</li>
                <li>
                  <span v-if="botInfo.telegram_username">Untuk Telegram: kirim pesan ke bot <strong class="text-slate-700 dark:text-slate-200">@{{ botInfo.telegram_username }}</strong>. </span>
                  Kirim pesan teks berisi kode di atas: <strong class="font-mono text-slate-800 dark:text-slate-100">{{ activePairing.code }}</strong>
                </li>
                <li>Bot akan memverifikasi dan langsung mengikat sesi ke akun Anda secara otomatis.</li>
              </ol>
            </div>
          </div>

          <!-- Formulir Buat Kode Hubung Baru -->
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
            <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
              Hubungkan Nomor Baru
            </h4>

            <form @submit.prevent="submitPairing" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
              <div class="sm:col-span-2">
                <RdSelect
                  id="tenant_id"
                  v-model="pairingForm.tenant_id"
                  label="Pilih Ruang Kerja Awal"
                  :options="tenantSelectOptions"
                  hint="Pilih ruang kerja yang ingin Anda kelola melalui chat. Anda dapat berpindah ruang kerja kapan saja di dalam chat dengan perintah /tenant."
                  required
                />
              </div>

              <div>
                <RdButton
                  type="submit"
                  variant="primary"
                  size="md"
                  class="w-full"
                  :loading="pairingForm.processing"
                >
                  Buat Kode Hubung
                </RdButton>
              </div>
            </form>
          </div>
        </div>
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
      <RdCard title="Preferensi Tampilan" description="Pilih tema tampilan antarmuka yang nyaman untuk mata Anda">
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
