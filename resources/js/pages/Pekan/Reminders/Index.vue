<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdTable from '../../../design-system/RdTable.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdDateTime from '../../../design-system/RdDateTime.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import type { User, Tenant } from '../../../types/core';
import type { FinanceReminder } from '../../../types/pekan';

withDefaults(
  defineProps<{
    user: User;
    tenant: Tenant;
    reminders?: FinanceReminder[];
  }>(),
  {
    reminders: () => [
      {
        id: 'rem-1',
        title: 'Tagihan Listrik PLN Pasca Bayar',
        amount_minor: 450000,
        due_date: '2026-10-20',
        repeat_interval: 'monthly',
        status: 'pending',
      },
      {
        id: 'rem-2',
        title: 'Internet Fiber Rumah',
        amount_minor: 385000,
        due_date: '2026-10-18',
        repeat_interval: 'monthly',
        status: 'pending',
      },
      {
        id: 'rem-3',
        title: 'Cicilan Perangkat Komputer',
        amount_minor: 1250000,
        due_date: '2026-10-25',
        repeat_interval: 'monthly',
        status: 'pending',
        current_tenor: 5,
        total_tenor: 12,
      },
    ],
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
  </RudimahAppShell>
</template>
