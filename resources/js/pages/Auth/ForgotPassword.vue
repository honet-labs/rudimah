<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdThemeToggle from '../../design-system/RdThemeToggle.vue';

const props = defineProps<{
  status?: string;
  error?: string;
  channelsConfigured?: boolean;
  unconfiguredNotice?: string;
}>();

const csrfToken = ref('');

function getCsrfToken(): string {
  if (typeof document !== 'undefined') {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }
  return '';
}

onMounted(() => {
  csrfToken.value = getCsrfToken();
});

const form = useForm({
  _token: '',
  email: '',
});

function submit() {
  const token = getCsrfToken() || csrfToken.value;
  form.transform((data) => ({
    ...data,
    _token: token,
  })).post('/forgot-password', {
    headers: {
      'X-CSRF-TOKEN': token,
    },
    preserveScroll: true,
    onError: (errs: any) => {
      if (errs && (errs.message === 'CSRF token mismatch.' || errs.csrf)) {
        window.location.reload();
      }
    },
  });
}
</script>

<template>
  <div class="relative min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="absolute top-4 right-4">
      <RdThemeToggle />
    </div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-800 text-white font-black text-lg mb-2">
        R
      </div>
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        Pemulihan Kata Sandi
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Masukkan email akun Anda untuk menerima tautan atur ulang kata sandi
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <!-- Peringatan Kanal Notifikasi Belum Disetup -->
        <div
          v-if="channelsConfigured === false || unconfiguredNotice"
          class="mb-5 p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-amber-900 dark:text-amber-200 rounded-lg text-xs space-y-2"
        >
          <div class="font-bold uppercase tracking-wider text-[11px] text-amber-800 dark:text-amber-300">
            Perhatian: Kanal Notifikasi Belum Dikonfigurasi
          </div>
          <p class="leading-relaxed">
            Kanal pengiriman notifikasi (Email SMTP, Telegram Bot, atau WhatsApp WAHA) belum disiapkan pada Admin Control Panel atau berkas .env. Tautan pemulihan otomatis belum dapat dikirim ke perangkat Anda.
          </p>
          <div class="pt-2 border-t border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300">
            <strong>Bagi Administrator Sistem:</strong> Anda dapat mereset kata sandi administrator secara langsung via terminal server:
            <code class="block mt-1 p-1.5 bg-amber-100 dark:bg-amber-900/60 rounded font-mono text-[11px] select-all text-slate-800 dark:text-slate-200">bash reset-admin-password.sh</code>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <input type="hidden" name="_token" :value="csrfToken" />

          <div v-if="status" class="p-3 bg-teal-50 dark:bg-teal-950/40 text-teal-800 dark:text-teal-200 text-xs rounded-md border border-teal-300 dark:border-teal-800">
            {{ status }}
          </div>
          <div v-if="error || form.errors.email" class="p-3 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-200 text-xs rounded-md border border-red-300 dark:border-red-800">
            {{ error || form.errors.email }}
          </div>

          <RdInput
            id="email"
            name="email"
            v-model="form.email"
            type="email"
            label="Alamat Email"
            placeholder="nama@domain.com"
            :error="form.errors.email"
            required
            :disabled="channelsConfigured === false"
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="form.processing"
              :disabled="channelsConfigured === false"
            >
              {{ channelsConfigured === false ? 'Kanal Pengiriman Belum Dikonfigurasi' : 'Kirim Tautan Pemulihan' }}
            </RdButton>
          </div>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          <a href="/login" class="font-medium text-teal-700 hover:underline">
            Kembali ke halaman login
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
