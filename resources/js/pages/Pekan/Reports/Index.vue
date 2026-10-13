<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdTable from '../../../design-system/RdTable.vue';
import type { User, Tenant } from '../../../types/core';

defineProps<{
  user: User;
  tenant: Tenant;
}>();

const breakdownData = [
  { category: 'Konsumsi Harian', total_minor: 3200000, percentage: 38.8 },
  { category: 'Tagihan Utilitas', total_minor: 1250000, percentage: 15.1 },
  { category: 'Transportasi', total_minor: 850000, percentage: 10.3 },
  { category: 'Pendidikan', total_minor: 1500000, percentage: 18.2 },
  { category: 'Hiburan', total_minor: 1450000, percentage: 17.6 },
];

const columns = [
  { key: 'category', label: 'Kategori Belanja' },
  { key: 'percentage', label: 'Porsi (%)', width: '120px', align: 'center' as const },
  { key: 'total_minor', label: 'Total Nominal', align: 'right' as const },
];
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Laporan Arus Kas & Analisis Keuangan"
      description="Evaluasi pergerakan kas masuk, realisasi pos pengeluaran, dan tren bulanan"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Laporan' }]"
    >
      <template #actions>
        <RdButton variant="outline" size="sm">
          Unduh Laporan CSV
        </RdButton>
        <RdButton variant="primary" size="sm">
          Cetak Dokumen PDF
        </RdButton>
      </template>
    </RdPageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <RdCard title="Ringkasan Performa Arus Kas">
          <div class="grid grid-cols-3 gap-4 text-xs mb-6">
            <div class="p-3 border rounded-md">
              <span class="text-slate-500">Total Arus Masuk</span>
              <div class="text-base font-bold text-teal-800 dark:text-teal-300 mt-1">
                <RdMoney :amount-minor="15500000" />
              </div>
            </div>
            <div class="p-3 border rounded-md">
              <span class="text-slate-500">Total Arus Keluar</span>
              <div class="text-base font-bold text-slate-800 dark:text-slate-200 mt-1">
                <RdMoney :amount-minor="8250000" />
              </div>
            </div>
            <div class="p-3 border rounded-md">
              <span class="text-slate-500">Surplus Kas</span>
              <div class="text-base font-bold text-teal-800 dark:text-teal-300 mt-1">
                <RdMoney :amount-minor="7250000" />
              </div>
            </div>
          </div>

          <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-md text-xs text-slate-600 dark:text-slate-300">
            Kondisi arus kas ruang kerja ini berada dalam status sehat dengan tingkat rasio tabungan sebesar 46.7% dari total pendapatan bulan berjalan.
          </div>
        </RdCard>

        <!-- Tabel Distribusi Pengeluaran -->
        <RdCard title="Komposisi Pengeluaran per Kategori">
          <RdTable :columns="columns" :items="breakdownData">
            <template #percentage="{ value }">
              <span class="font-mono text-xs font-medium">{{ value }}%</span>
            </template>
            <template #total_minor="{ value }">
              <span class="font-semibold text-slate-800 dark:text-slate-100">
                <RdMoney :amount-minor="value" />
              </span>
            </template>
          </RdTable>
        </RdCard>
      </div>

      <!-- Parameter Filter Laporan -->
      <div>
        <RdCard title="Filter Rentang Waktu">
          <div class="space-y-4 text-xs">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Periode Laporan</label>
              <select class="w-full px-3 py-2 rounded-md border border-slate-300 dark:border-slate-700 text-xs bg-white dark:bg-slate-800">
                <option>Bulan Berjalan (Oktober 2026)</option>
                <option>Bulan Lalu (September 2026)</option>
                <option>Kuartal 3 2026</option>
                <option>Tahun Kalender 2026</option>
              </select>
            </div>

            <div class="pt-2">
              <RdButton variant="primary" size="sm" class="w-full">
                Perbarui Laporan
              </RdButton>
            </div>
          </div>
        </RdCard>
      </div>
    </div>
  </RudimahAppShell>
</template>
