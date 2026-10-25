<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdThemeToggle from '../../design-system/RdThemeToggle.vue';

defineProps<{
  error?: string;
  errors?: Record<string, string>;
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
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function submit() {
  const token = getCsrfToken() || csrfToken.value;
  form.transform((data) => ({
    ...data,
    _token: token,
  })).post('/register', {
    headers: {
      'X-CSRF-TOKEN': token,
    },
    preserveScroll: true,
    onError: (errs: any) => {
      if (errs && (errs.message === 'CSRF token mismatch.' || errs.csrf)) {
        window.location.reload();
      }
    },
    onFinish: () => {
      form.reset('password', 'password_confirmation');
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
        Pendaftaran Akun RUDIMAH
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Buat akun untuk memulai manajemen keuangan dan hidup harian
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form @submit.prevent="submit" class="space-y-4">
          <input type="hidden" name="_token" :value="csrfToken" />

          <div v-if="error || form.errors.name || form.errors.email || form.errors.password" class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 text-xs rounded-md">
            {{ error || form.errors.name || form.errors.email || form.errors.password }}
          </div>

          <RdInput
            id="name"
            name="name"
            v-model="form.name"
            label="Nama Lengkap"
            placeholder="Masukkan nama Anda"
            :error="form.errors.name"
            required
          />

          <RdInput
            id="email"
            name="email"
            v-model="form.email"
            type="email"
            label="Alamat Email"
            placeholder="nama@domain.com"
            :error="form.errors.email"
            required
          />

          <RdInput
            id="password"
            name="password"
            v-model="form.password"
            type="password"
            label="Kata Sandi"
            placeholder="Minimal 8 karakter"
            :error="form.errors.password"
            required
          />

          <RdInput
            id="password_confirmation"
            name="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            label="Konfirmasi Kata Sandi"
            placeholder="Ketik ulang kata sandi"
            :error="form.errors.password_confirmation"
            required
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="form.processing"
            >
              Daftar Sekarang
            </RdButton>
          </div>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          Sudah memiliki akun?
          <a href="/login" class="font-medium text-teal-700 hover:underline">
            Masuk ke akun
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
