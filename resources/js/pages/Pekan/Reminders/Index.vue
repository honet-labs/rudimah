<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdEmptyState from '../../../design-system/RdEmptyState.vue';
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
  </RudimahAppShell>
</template>
