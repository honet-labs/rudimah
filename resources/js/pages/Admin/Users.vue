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

interface AdminUserItem {
  id: string;
  name: string;
  email: string;
  is_super_admin: boolean;
  has_mfa: boolean;
  workspaces_count: number;
  created_at: string;
}

const props = defineProps<{
  user: User;
  tenant?: Tenant | null;
  status?: string;
  error?: string;
  usersList?: AdminUserItem[];
}>();

const searchQuery = ref('');
const roleFilter = ref<'all' | 'admin' | 'user'>('all');
const mfaFilter = ref<'all' | 'mfa_enabled' | 'mfa_disabled'>('all');

const roleOptions = [
  { label: 'Semua Hak Akses', value: 'all' },
  { label: 'Hanya Super Admin', value: 'admin' },
  { label: 'Pengguna Biasa', value: 'user' },
];

const mfaOptions = [
  { label: 'Semua Status MFA', value: 'all' },
  { label: 'MFA Aktif', value: 'mfa_enabled' },
  { label: 'Belum Ada MFA', value: 'mfa_disabled' },
];

const filteredUsers = computed(() => {
  let list = props.usersList || [];
  if (roleFilter.value === 'admin') {
    list = list.filter((u) => u.is_super_admin);
  } else if (roleFilter.value === 'user') {
    list = list.filter((u) => !u.is_super_admin);
  }

  if (mfaFilter.value === 'mfa_enabled') {
    list = list.filter((u) => u.has_mfa);
  } else if (mfaFilter.value === 'mfa_disabled') {
    list = list.filter((u) => !u.has_mfa);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
  }
  return list;
});

const userColumns = [
  { key: 'name', label: 'Nama Pengguna' },
  { key: 'email', label: 'Alamat Email' },
  { key: 'has_mfa', label: 'Keamanan MFA', width: '130px', align: 'center' as const },
  { key: 'is_super_admin', label: 'Hak Akses', width: '130px', align: 'center' as const },
  { key: 'workspaces_count', label: 'Ruang Kerja', width: '120px', align: 'center' as const },
  { key: 'created_at', label: 'Terdaftar', width: '120px' },
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
      title="Pengguna Terdaftar"
      description="Pantau seluruh akun pengguna terdaftar beserta kepatuhan keamanan MFA dan hak akses Super Administrator"
      :breadcrumbs="[{ label: 'Administrasi', href: '/admin' }, { label: 'Pengguna Terdaftar' }]"
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
        <span class="text-xs text-slate-500 font-medium truncate block">Total Pengguna</span>
        <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
          {{ props.usersList?.length || 0 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Akun identitas global</p>
      </RdCard>

      <RdCard padding>
        <span class="text-xs text-slate-500 font-medium truncate block">Super Administrator</span>
        <div class="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
          {{ props.usersList?.filter(u => u.is_super_admin).length || 0 }}
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Akses root platform</p>
      </RdCard>

      <RdCard padding class="col-span-2 sm:col-span-1">
        <span class="text-xs text-slate-500 font-medium truncate block">Kepatuhan MFA</span>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
          {{ props.usersList?.filter(u => u.has_mfa).length || 0 }}
          <span class="text-xs font-normal text-slate-500">
            / {{ props.usersList?.length || 0 }} akun
          </span>
        </div>
        <p class="text-[11px] text-slate-500 mt-1">Dilindungi Passkey / TOTP</p>
      </RdCard>
    </div>

    <!-- Filter & Pencarian -->
    <RdCard padding class="mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 items-end">
        <div>
          <RdInput
            v-model="searchQuery"
            label="Cari Pengguna"
            placeholder="Ketik nama atau alamat email..."
          />
        </div>
        <div>
          <RdSelect
            v-model="roleFilter"
            label="Filter Hak Akses"
            :options="roleOptions"
          />
        </div>
        <div>
          <RdSelect
            v-model="mfaFilter"
            label="Filter Status MFA"
            :options="mfaOptions"
          />
        </div>
      </div>
    </RdCard>

    <!-- Tabel Pengguna -->
    <RdCard
      title="Daftar Pengguna Global"
      :description="`Menampilkan ${filteredUsers.length} dari ${props.usersList?.length || 0} pengguna terdaftar`"
    >
      <RdTable :columns="userColumns" :items="filteredUsers">
        <template #name="{ item }">
          <span class="font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ item.name }}</span>
        </template>
        <template #email="{ value }">
          <span class="text-xs text-slate-600 dark:text-slate-400 font-mono whitespace-nowrap">{{ value }}</span>
        </template>
        <template #has_mfa="{ value }">
          <RdBadge :variant="value ? 'success' : 'warning'" class="whitespace-nowrap">
            {{ value ? 'MFA Aktif' : 'Belum Ada MFA' }}
          </RdBadge>
        </template>
        <template #is_super_admin="{ value }">
          <RdBadge :variant="value ? 'info' : 'neutral'" class="whitespace-nowrap">
            {{ value ? 'Super Admin' : 'Pengguna Biasa' }}
          </RdBadge>
        </template>
        <template #workspaces_count="{ value }">
          <span class="text-xs font-medium whitespace-nowrap">{{ value }} Ruang Kerja</span>
        </template>
        <template #created_at="{ value }">
          <span class="text-xs text-slate-500 whitespace-nowrap">{{ value || '-' }}</span>
        </template>
        <template #empty>
          <div class="text-center py-8 text-slate-500 dark:text-slate-400 text-sm">
            Tidak ada pengguna yang sesuai dengan kriteria pencarian.
          </div>
        </template>
      </RdTable>
    </RdCard>
  </RudimahAppShell>
</template>
