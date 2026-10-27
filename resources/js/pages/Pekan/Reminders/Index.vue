<script setup lang="ts">
import { ref } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
import RdModal from '../../../design-system/RdModal.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceReminder } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    reminders?: FinanceReminder[];
  }>(),
  {
    reminders: () => [],
  }
);

const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string }>({ url: '', title: '' });

function openPreview(url: string, title: string) {
  previewImage.value = { url, title };
  previewModalOpen.value = true;
}

const columns = [
  { key: 'due_date', label: 'Jatuh Tempo', width: '130px' },
  { key: 'title', label: 'Nama Tagihan / Kewajiban' },
  { key: 'tenor', label: 'Tenor', width: '100px', align: 'center' as const },
  { key: 'amount_minor', label: 'Nominal Tagihan', align: 'right' as const },
  { key: 'status', label: 'Status', width: '120px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '120px', align: 'center' as const },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pengingat Tagihan & Kewajiban"
      description="Jadwal pembayaran tagihan rutin, utilitas, dan cicilan agar tidak terlambat"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Pengingat' }]"
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/reminders/create`">
          <RdButton variant="primary" size="sm">
            Tambah Pengingat
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <!-- Konten Daftar Pengingat -->
    <div v-if="reminders && reminders.length > 0">
      <!-- 1. Tampilan Desktop & Tablet (Tabel Lengkap) -->
      <div class="hidden sm:block">
        <RdCard padding>
          <RdTable :columns="columns" :items="reminders">
            <template #due_date="{ value }">
              <RdDateTime :value="value" format="date" />
            </template>
            <template #title="{ item }">
              <div class="space-y-1">
                <span class="font-medium text-slate-800 dark:text-slate-100 block">{{ item.title }}</span>
                <div v-if="item.images && item.images.length > 0" class="flex items-center gap-1.5 flex-wrap">
                  <button
                    v-for="(img, idx) in item.images"
                    :key="idx"
                    type="button"
                    @click.stop="openPreview(img.url, `${item.title} - Berkas ${idx + 1}`)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors border border-slate-200 dark:border-slate-700"
                    title="Klik untuk melihat bukti tagihan"
                  >
                    <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Bukti {{ idx + 1 }}</span>
                  </button>
                </div>
              </div>
            </template>
            <template #tenor="{ item }">
              <span v-if="item.total_tenor" class="text-xs text-slate-500 font-mono">
                {{ item.current_tenor }} dari {{ item.total_tenor }}
              </span>
              <span v-else class="text-xs text-slate-400">Rutin</span>
            </template>
            <template #amount_minor="{ value }">
              <span class="font-semibold text-slate-800 dark:text-slate-100">
                <RdMoney :amount-minor="value" />
              </span>
            </template>
            <template #status="{ value }">
              <RdBadge :variant="value === 'paid' ? 'success' : value === 'overdue' ? 'danger' : 'neutral'">
                {{ value === 'paid' ? 'Sudah Lunas' : value === 'overdue' ? 'Lewat Tempo' : 'Menunggu Bayar' }}
              </RdBadge>
            </template>
            <template #actions="{ item }">
              <div class="flex items-center justify-center gap-2">
                <a :href="`/t/${tenant.slug}/apps/pekan/reminders/${item.id}/edit`">
                  <RdButton variant="outline" size="sm">
                    Ubah
                  </RdButton>
                </a>
              </div>
            </template>
          </RdTable>
        </RdCard>
      </div>

      <!-- 2. Tampilan Khusus Ponsel / Mobile (Stack Card Bebas Scroll Horizontal) -->
      <div class="sm:hidden space-y-3 w-full">
        <div
          v-for="rem in reminders"
          :key="rem.id"
          class="p-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3 w-full"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug">
                {{ rem.title }}
              </h4>
              <div class="flex items-center gap-1.5 mt-1 text-xs text-slate-500">
                <span>Jatuh Tempo:</span>
                <span class="font-medium text-slate-700 dark:text-slate-300">
                  <RdDateTime :value="rem.due_date" format="date" />
                </span>
              </div>
            </div>
            <RdBadge
              :variant="rem.status === 'paid' ? 'success' : rem.status === 'overdue' ? 'danger' : 'neutral'"
              class="shrink-0"
            >
              {{ rem.status === 'paid' ? 'Sudah Lunas' : rem.status === 'overdue' ? 'Lewat Tempo' : 'Menunggu' }}
            </RdBadge>
          </div>

          <!-- Pratinjau Foto Bukti Tagihan di Mobile -->
          <div v-if="rem.images && rem.images.length > 0" class="flex items-center gap-2 pt-1 flex-wrap">
            <button
              v-for="(img, idx) in rem.images"
              :key="idx"
              type="button"
              @click="openPreview(img.url, `${rem.title} - Berkas ${idx + 1}`)"
              class="relative rounded border border-slate-200 dark:border-slate-700 overflow-hidden w-11 h-11 bg-slate-100 dark:bg-slate-800 shrink-0 hover:opacity-90 transition-opacity"
            >
              <img :src="img.url" :alt="img.name || 'Bukti tagihan'" class="w-full h-full object-cover" />
            </button>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">({{ rem.images.length }} lampiran)</span>
          </div>

          <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div>
              <span class="text-slate-400 text-[11px] block">Nominal Tagihan</span>
              <span class="text-sm font-bold text-slate-900 dark:text-slate-100">
                <RdMoney :amount-minor="rem.amount_minor" />
              </span>
            </div>
            <div class="text-right">
              <span class="text-slate-400 text-[11px] block">Tenor</span>
              <span v-if="rem.total_tenor" class="font-semibold text-slate-700 dark:text-slate-300 font-mono">
                {{ rem.current_tenor }} / {{ rem.total_tenor }}
              </span>
              <span v-else class="text-slate-500 font-medium">Rutin Bulanan</span>
            </div>
          </div>

          <div class="pt-1">
            <a :href="`/t/${tenant.slug}/apps/pekan/reminders/${rem.id}/edit`" class="block w-full">
              <RdButton variant="outline" size="sm" class="w-full">
                Ubah Rincian Tagihan
              </RdButton>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State Bersih Saat Belum Ada Pengingat -->
    <RdEmptyState
      v-else
      title="Belum Ada Pengingat Tagihan"
      description="Catat tagihan utilitas, cicilan, atau kewajiban berkala agar Anda mendapatkan notifikasi pengingat sebelum tanggal jatuh tempo."
    >
      <template #actions>
        <a :href="`/t/${tenant.slug}/apps/pekan/reminders/create`">
          <RdButton variant="primary" size="sm">
            + Tambah Pengingat Baru
          </RdButton>
        </a>
      </template>
    </RdEmptyState>

    <!-- Modal Lightbox Pratinjau Gambar Bukti Tagihan -->
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
          <span>Pratinjau Berkas Lampiran</span>
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
