<script setup lang="ts">
import { ref } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdModal from '../../../design-system/RdModal.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceSavings } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    savings?: FinanceSavings[];
  }>(),
  {
    savings: () => [],
  }
);

const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string }>({ url: '', title: '' });

const copiedId = ref<string | null>(null);
function copyId(id: string) {
  if (!id) return;
  navigator.clipboard.writeText(id);
  copiedId.value = id;
  setTimeout(() => {
    if (copiedId.value === id) copiedId.value = null;
  }, 2000);
}

function openPreview(url: string, title: string) {
  previewImage.value = { url, title };
  previewModalOpen.value = true;
}
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pos Tabungan & Impian Keuangan"
      description="Rencanakan akumulasi dana target untuk masa depan keluarga secara terukur"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Tabungan' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/savings/create`">
          <RdButton variant="primary" size="sm">
            Buat Pos Tabungan Baru
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Konten Daftar Tabungan -->
    <div v-if="savings && savings.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <RdCard
        v-for="s in savings"
        :key="s.id"
        padding
      >
        <div class="flex items-center justify-between mb-1.5">
          <button
            type="button"
            @click="copyId(s.id)"
            class="font-mono text-[11px] text-slate-500 hover:text-teal-700 dark:hover:text-teal-400 transition-colors select-all cursor-pointer inline-flex items-center gap-1 group text-left"
            :title="`Klik untuk menyalin ID Tabungan: ${s.id}`"
          >
            <span>#{{ s.id ? s.id.substring(0, 8) : '-' }}</span>
            <span v-if="copiedId === s.id" class="text-[10px] text-teal-600 font-sans font-semibold">Tersalin</span>
            <svg v-else class="w-3 h-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </button>
          <RdBadge variant="neutral">
            {{ Math.round(s.progress_percent) }}%
          </RdBadge>
        </div>

        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">
          {{ s.name }}
        </h3>

        <!-- Galeri Foto Impian / Berkas Lampiran -->
        <div v-if="s.images && s.images.length > 0" class="my-2.5">
          <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <button
              v-for="(img, idx) in s.images"
              :key="idx"
              type="button"
              @click="openPreview(img.url, `${s.name} - Foto ${idx + 1}`)"
              class="relative rounded border border-slate-200 dark:border-slate-700 overflow-hidden w-12 h-12 bg-slate-100 dark:bg-slate-800 shrink-0 group hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-teal-500"
              :title="img.name || `Foto impian ${idx + 1}`"
            >
              <img :src="img.url" :alt="img.name || 'Foto impian'" class="w-full h-full object-cover" />
            </button>
          </div>
          <span class="text-[10px] text-slate-400">
            {{ s.images.length }} foto impian / berkas terlampir
          </span>
        </div>

        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden my-3">
          <div
            class="h-full bg-teal-700 transition-all"
            :style="{ width: `${s.progress_percent}%` }"
          ></div>
        </div>

        <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 mb-4">
          <div class="flex justify-between">
            <span class="text-slate-500">Terkumpul:</span>
            <span class="font-semibold"><RdMoney :amount-minor="s.current_amount_minor" /></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Target Total:</span>
            <span class="font-semibold"><RdMoney :amount-minor="s.target_amount_minor" /></span>
          </div>
          <div v-if="s.target_date" class="flex justify-between text-[11px] text-slate-400">
            <span>Target Waktu:</span>
            <span><RdDateTime :value="s.target_date" format="date" /></span>
          </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
          <span class="text-[11px] text-slate-400 uppercase font-medium">Status: Aktif</span>
          <a :href="`/t/${tenant.slug}/apps/pekan/savings/${s.id}/edit`">
            <RdButton variant="outline" size="sm">
              Ubah Pos
            </RdButton>
          </a>
        </div>
      </RdCard>
    </div>

    <!-- Empty State Bersih Bila Belum Ada Pos Tabungan -->
    <RdEmptyState
      v-else
      title="Belum Ada Pos Tabungan"
      description="Rencanakan dan pantau impian keuangan masa depan Anda seperti dana darurat, liburan, atau pembelian aset impian."
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/savings/create`">
          <RdButton variant="primary" size="sm">
            + Buat Pos Tabungan Baru
          </RdButton>
        </a>
      </template>
    </RdEmptyState>

    <!-- Modal Lightbox Pratinjau Foto Impian / Bukti Simpanan -->
    <RdModal
      v-model="previewModalOpen"
      :title="previewImage.title"
      max-width="lg"
    >
      <div class="space-y-4">
        <div class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-black/5 dark:bg-black/30 flex items-center justify-center min-h-[220px]">
          <img
            :src="previewImage.url"
            :alt="previewImage.title"
            class="max-h-[65vh] w-auto max-w-full object-contain mx-auto rounded"
          />
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
          <span>Pratinjau Foto Impian</span>
          <a
            :href="previewImage.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 text-teal-600 hover:text-teal-700 dark:text-teal-400 font-medium hover:underline"
          >
            <span>Buka Gambar Penuh</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>
      </div>
    </RdModal>
  </RudimahAppShell>
</template>
