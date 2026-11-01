<script setup lang="ts">
import { ref, computed } from 'vue';
import RudimahAppShell from '../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../design-system/RdPageHeader.vue';
import RdCard from '../../design-system/RdCard.vue';
import RdBadge from '../../design-system/RdBadge.vue';
import RdTable from '../../design-system/RdTable.vue';
import RdButton from '../../design-system/RdButton.vue';
import RdInput from '../../design-system/RdInput.vue';
import RdSelect from '../../design-system/RdSelect.vue';
import RdAlert from '../../design-system/RdAlert.vue';
import type { User, Tenant } from '../../types/core';

interface AdminTenantItem {
  id: string;
  name: string;
  slug: string;
  status: string;
  members_count: number;
  created_at: string;
}

const props = defineProps<{
  user: User;
  tenant?: Tenant | null;
  status?: string;
  error?: string;
  tenantsList?: AdminTenantItem[];
}>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');

const statusOptions = [
  { label: 'Semua Status', value: 'all' },
  { label: 'Hanya Aktif', value: 'active' },
  { label: 'Hanya Nonaktif', value: 'inactive' },
];

const filteredTenants = computed(() => {
  let list = props.tenantsList || [];
  if (statusFilter.value !== 'all') {
    list = list.filter((t) => t.status === statusFilter.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter((t) => t.name.toLowerCase().includes(q) || t.slug.toLowerCase().includes(q));
  }
  return list;
});

const tenantColumns = [
  { key: 'name', label: 'Nama Ruang Kerja' },
  { key: 'slug', label: 'Slug URL' },
  { key: 'members_count', label: 'Anggota', width: '90px', align: 'center' as const },
  { key: 'created_at', label: 'Terdaftar', width: '130px' },
  { key: 'status', label: 'Status', width: '110px', align: 'center' as const },
  { key: 'actions', label: 'Aksi', width: '150px', align: 'center' as const },
];
</script>

<template>
  <RudimahAppShell
    :user="user"
    :tenant="tenant || undefined"
    app-key="admin"
    app-name="Administrasi Sistem"
  >
    <RdPageHeader
      title="Semua Ruang Kerja"
      description="Kelola seluruh tenant ruang kerja keluarga maupun usaha yang terdaftar di platform RUDIMAH"
      :breadcrumbs="[{ label: 'Administrasi', href: '/admin' }, { label: 'Semua Ruang Kerja' }]"
    >
      <template #actions>
        <a href="/workspaces" class="w-full sm:w-auto">
          <RdButton variant="outline" size="sm" class="w-full sm:w-auto">
            Kembali ke Pemilih Ruang Kerja
          </RdButton>
        </a>
      </template>
    </RdPageHeader>

    <div v-if="status" class="mb-4 sm:mb-6">
      <RdAlert variant="success" title="Operasi Berhasil">
        {{ status }}
      </RdAlert>
    </div>
    <div v-if="error" class="mb-4 sm:mb-6">
      <RdAlert variant="error" title="Kendala Sistem">
        {{ error }}
      </RdAlert>
    </div>

    <!-- Ringkasan Cepat -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">
      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium truncate block">Total Ruang Kerja</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ props.tenantsList?.length || 0 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Terdaftar di platform</p>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium truncate block">Ruang Kerja Aktif</span>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
          {{ props.tenantsList?.filter(t => t.status === 'active').length || 0 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Dapat diakses anggota</p>
      </RdCard>

      <RdCard padding class="col-span-2 sm:col-span-1">
        <span class="text-xs text-slate-500 font-medium truncate block">Total Akumulasi Anggota</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ props.tenantsList?.reduce((acc, t) => acc + (t.members_count || 0), 0) || 0 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Membership terhubung</p>
      </RdCard>
    </div>

    <!-- Filter & Pencarian -->
    <RdCard padding class="mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 items-end">
        <div class="sm:col-span-2">
          <RdInput
            v-model="searchQuery"
            label="Cari Ruang Kerja"
            placeholder="Ketik nama ruang kerja atau slug..."
          />
        </div>
        <div>
          <RdSelect
            v-model="statusFilter"
            label="Filter Status"
            :options="statusOptions"
          />
        </div>
      </div>
    </RdCard>

    <!-- Tabel Ruang Kerja -->
    <RdCard
      title="Daftar Ruang Kerja"
      :description="`Menampilkan ${filteredTenants.length} dari ${props.tenantsList?.length || 0} ruang kerja terdaftar`"
    >
      <RdTable :columns="tenantColumns" :items="filteredTenants">
        <template #name="{ item }">
          <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
        </template>
        <template #slug="{ value }">
          <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-xs whitespace-nowrap">
            /t/{{ value }}
          </code>
        </template>
        <template #members_count="{ value }">
          <span class="font-semibold whitespace-nowrap">{{ value }}</span>
        </template>
        <template #created_at="{ value }">
          <span class="text-xs text-slate-500 whitespace-nowrap">{{ value || '-' }}</span>
        </template>
        <template #status="{ value }">
          <RdBadge :variant="value === 'active' ? 'success' : 'neutral'" class="whitespace-nowrap">
            {{ value === 'active' ? 'Aktif' : 'Nonaktif' }}
          </RdBadge>
        </template>
        <template #actions="{ item }">
          <a :href="`/t/${item.slug}/apps/pekan/dashboard`" class="inline-block whitespace-nowrap">
            <RdButton variant="outline" size="sm">
              Buka Ruang Kerja
            </RdButton>
          </a>
        </template>
        <template #empty>
          <div class="text-center py-8 text-slate-500 dark:text-slate-400 text-sm">
            Tidak ada ruang kerja yang sesuai dengan kriteria pencarian.
          </div>
        </template>
      </RdTable>
    </RdCard>
  </RudimahAppShell>
</template>
