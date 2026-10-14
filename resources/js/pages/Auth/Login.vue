<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdCheckbox from '../../design-system/RdCheckbox.vue';
import RdThemeToggle from '../../design-system/RdThemeToggle.vue';

const props = defineProps<{
  error?: string;
  status?: string;
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
  email: '',
  password: '',
  remember: false,
});

function submit() {
  const token = getCsrfToken() || csrfToken.value;
  form.transform((data) => ({
    ...data,
    _token: token,
  })).post('/login', {
    headers: {
      'X-CSRF-TOKEN': token,
    },
    preserveScroll: true,
    onError: (errs: any) => {
      // Jika terjadi kesalahan CSRF atau status 419, segarkan halaman
      if (errs && (errs.message === 'CSRF token mismatch.' || errs.csrf)) {
        window.location.reload();
      }
    },
    onFinish: () => {
      form.reset('password');
    },
  });
}
</script>

<template>
  <div class="relative min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="absolute top-4 right-4">
      <RdThemeToggle />
    </div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-800 text-white font-black text-lg mb-2">
        R
      </div>
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        RUDIMAH
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Ruang Digital Manajemen Harian
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form @submit.prevent="submit" class="space-y-4">
          <input type="hidden" name="_token" :value="csrfToken" />

          <div v-if="error || form.errors.email || form.errors.password" class="p-3 bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-800 text-red-800 dark:text-red-200 text-xs rounded-md">
            {{ error || form.errors.email || form.errors.password }}
          </div>

          <div v-if="status" class="p-3 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs rounded-md">
            {{ status }}
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
          />

          <RdInput
            id="password"
            name="password"
            v-model="form.password"
            type="password"
            label="Kata Sandi"
            placeholder="Masukkan kata sandi"
            :error="form.errors.password"
            required
          />

          <div class="flex items-center justify-between text-xs">
            <RdCheckbox
              v-model="form.remember"
              label="Ingat saya di perangkat ini"
            />
            <a
              href="/forgot-password"
              class="font-medium text-teal-700 hover:text-teal-800 dark:text-teal-400 dark:hover:text-teal-300"
            >
              Lupa kata sandi?
            </a>
          </div>

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="form.processing"
            >
              Masuk ke RUDIMAH
            </RdButton>
          </div>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          Belum memiliki akun?
          <a href="/register" class="font-medium text-teal-700 hover:underline">
            Daftar akun baru
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
