<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdThemeToggle from '../../design-system/RdThemeToggle.vue';

defineProps<{
  status?: string;
  error?: string;
}>();

const csrfToken = ref('');

onMounted(() => {
  if (typeof document !== 'undefined') {
    csrfToken.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }
});

const form = useForm({
  email: '',
});

function submit() {
  form.post('/forgot-password', {
    preserveScroll: true,
  });
}
</script>

<template>
  <div class="relative min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="absolute top-4 right-4">
      <RdThemeToggle />
    </div>
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
      <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white uppercase">
        Pemulihan Kata Sandi
      </h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        Masukkan email akun Anda untuk menerima tautan atur ulang kata sandi
      </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-sm border border-slate-200 dark:border-slate-800 rounded-lg sm:px-10">
        <form @submit.prevent="submit" class="space-y-4">
          <input type="hidden" name="_token" :value="csrfToken" />

          <div v-if="status" class="p-3 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs rounded-md border border-slate-300 dark:border-slate-700">
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
          />

          <div class="pt-2">
            <RdButton
              type="submit"
              variant="primary"
              class="w-full"
              :loading="form.processing"
            >
              Kirim Tautan Pemulihan
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
