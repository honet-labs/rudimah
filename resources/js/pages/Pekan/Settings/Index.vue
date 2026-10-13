<script setup lang="ts">
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdBadge from '../../../design-system/RdBadge.vue';
import RdCheckbox from '../../../design-system/RdCheckbox.vue';
import type { User, Tenant } from '../../../types/core';

defineProps<{
  user: User;
  tenant: Tenant;
}>();
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pengaturan Modul PEKAN"
      description="Konfigurasi kanal pengingat, integrasi pesan WhatsApp, dan kebijakan data ruang kerja"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Pengaturan' }]"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl">
      <!-- Pengaturan Pengingat & Notifikasi -->
      <RdCard title="Kanal Notifikasi Tagihan" description="Pilih media pengiriman pengingat jatuh tempo pembayaran">
        <div class="space-y-4">
          <RdCheckbox
            label="Kirim notifikasi email harian untuk tagihan mendekati jatuh tempo"
            :model-value="true"
          />
          <RdCheckbox
            label="Kirim peringatan ambang batas anggaran saat belanja mencapai 80%"
            :model-value="true"
          />
          <div class="pt-2">
            <RdButton variant="primary" size="sm">
              Simpan Preferensi
            </RdButton>
          </div>
        </div>
      </RdCard>

      <!-- Integrasi Bot WhatsApp -->
      <RdCard title="Integrasi WhatsApp Bot" description="Pencatatan mutasi belanja instan via pesan WhatsApp">
        <div class="space-y-3 text-xs">
          <div class="flex items-center justify-between p-3 border rounded-md">
            <div>
              <span class="font-semibold text-slate-800 dark:text-slate-100">Status Layanan Bot</span>
              <p class="text-slate-500">Koneksi gateway resmi Meta Cloud API</p>
            </div>
            <RdBadge variant="neutral">Tersedia</RdBadge>
          </div>
          <p class="text-slate-500">
            Hubungkan nomor WhatsApp untuk mencatat pengeluaran harian cukup dengan mengirim pesan teks atau foto kuitansi ke bot.
          </p>
          <div class="pt-2">
            <RdButton variant="outline" size="sm">
              Konfigurasi Kunci API
            </RdButton>
          </div>
        </div>
      </RdCard>
    </div>
  </RudimahAppShell>
</template>
