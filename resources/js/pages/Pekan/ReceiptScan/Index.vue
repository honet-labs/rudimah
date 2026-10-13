<script setup lang="ts">
import { ref, computed } from 'vue';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdFileUpload from '../../../design-system/RdFileUpload.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdAlert from '../../../design-system/RdAlert.vue';
import type { User, Tenant } from '../../../types/core';
import type { ReceiptExtraction } from '../../../types/pekan';

defineProps<{
  user: User;
  tenant: Tenant;
}>();

const isProcessing = ref(false);
const hasResult = ref(false);

const extraction = ref<ReceiptExtraction>({
  merchant_name: 'SUPERINDO TRANSMART',
  receipt_number: 'STR-20261012-049',
  transaction_date: '2026-10-12',
  currency: 'Rp',
  subtotal_minor: 125000,
  tax_minor: 13750,
  service_charge_minor: 0,
  discount_minor: 5000,
  total_minor: 133750,
  items: [
    { item_name: 'Beras Pandan Wangi 5kg', quantity: 1, price_minor: 75000, discount_minor: 0, total_minor: 75000 },
    { item_name: 'Minyak Goreng 2L', quantity: 1, price_minor: 34000, discount_minor: 0, total_minor: 34000 },
    { item_name: 'Telur Ayam Negeri 1kg', quantity: 1, price_minor: 16000, discount_minor: 5000, total_minor: 11000 },
  ],
});

function handleFileSelected(file: File) {
  isProcessing.value = true;
  // Simulasi pemrosesan OCR worker aman
  setTimeout(() => {
    isProcessing.value = false;
    hasResult.value = true;
  }, 1200);
}

function updateLineTotal(idx: number) {
  const item = extraction.value.items[idx];
  item.total_minor = Math.max(0, (item.quantity * item.price_minor) - item.discount_minor);
  recalculateGrandTotal();
}

function recalculateGrandTotal() {
  const sumItems = extraction.value.items.reduce((acc, it) => acc + it.total_minor, 0);
  extraction.value.subtotal_minor = sumItems;
  extraction.value.total_minor = sumItems + extraction.value.tax_minor + extraction.value.service_charge_minor - extraction.value.discount_minor;
}

const isConsistent = computed(() => {
  const expected = extraction.value.subtotal_minor + extraction.value.tax_minor + extraction.value.service_charge_minor - extraction.value.discount_minor;
  return expected === extraction.value.total_minor;
});
</script>

<template>
  <RudimahAppShell :user="user" :tenant="tenant" app-key="pekan" app-name="PEKAN">
    <RdPageHeader
      title="Pindai Kuitansi & Struk OCR"
      description="Ekstraksi otomatis rincian struk belanja dengan tinjauan manual sebelum penyimpanan final"
      :breadcrumbs="[{ label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` }, { label: 'Pindai Struk' }]"
    />

    <div class="space-y-6 max-w-4xl">
      <!-- Area Unggah Berkas Struk -->
      <RdCard title="Unggah Foto Kuitansi">
        <RdFileUpload @file-selected="handleFileSelected" />
        <div v-if="isProcessing" class="mt-4 p-4 text-center text-xs text-slate-500 bg-slate-50 dark:bg-slate-800 rounded-md">
          <div class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-4 w-4 text-teal-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Model OCR sedang memindai dan mengekstraksi data kuitansi...</span>
          </div>
        </div>
      </RdCard>

      <!-- Panel Tinjauan Hasil Ekstraksi (Human-in-the-loop) -->
      <div v-if="hasResult" class="space-y-6">
        <RdAlert variant="info" title="Konfirmasi Diperlukan">
          Data berikut diekstraksi secara otomatis oleh model OCR. Silakan periksa dan koreksi data sebelum menyimpannya sebagai transaksi resmi.
        </RdAlert>

        <RdCard title="Hasil Ekstraksi Kuitansi">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <RdInput
              id="ocr_merchant"
              v-model="extraction.merchant_name"
              label="Nama Merchant / Toko"
              required
            />
            <RdInput
              id="ocr_receipt_no"
              v-model="extraction.receipt_number"
              label="Nomor Kuitansi"
            />
            <RdInput
              id="ocr_date"
              v-model="extraction.transaction_date"
              type="date"
              label="Tanggal Struk"
              required
            />
          </div>

          <!-- Rincian Item Hasil Ekstraksi -->
          <div class="mt-4">
            <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
              Daftar Barang Terdeteksi
            </h4>
            <div class="space-y-2">
              <div
                v-for="(it, idx) in extraction.items"
                :key="idx"
                class="p-3 border border-slate-200 dark:border-slate-800 rounded-md grid grid-cols-1 sm:grid-cols-12 gap-3 items-center text-xs"
              >
                <div class="sm:col-span-6">
                  <RdInput
                    :id="`ocr_item_${idx}`"
                    v-model="it.item_name"
                    label="Nama Item"
                    required
                  />
                </div>
                <div class="sm:col-span-2">
                  <RdInput
                    :id="`ocr_qty_${idx}`"
                    v-model.number="it.quantity"
                    type="number"
                    label="Kuantitas"
                    required
                    @input="updateLineTotal(idx)"
                  />
                </div>
                <div class="sm:col-span-2">
                  <RdInput
                    :id="`ocr_price_${idx}`"
                    v-model.number="it.price_minor"
                    type="number"
                    label="Harga Satuan (Rp)"
                    required
                    @input="updateLineTotal(idx)"
                  />
                </div>
                <div class="sm:col-span-2 text-right">
                  <span class="block text-[11px] text-slate-500 mb-1">Subtotal</span>
                  <span class="font-mono font-medium"><RdMoney :amount-minor="it.total_minor" /></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Ringkasan Angka & Verifikasi Integritas -->
          <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-slate-500">Subtotal Barang:</span>
              <span class="font-mono"><RdMoney :amount-minor="extraction.subtotal_minor" /></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Pajak (PPN):</span>
              <div class="w-32">
                <RdInput
                  id="ocr_tax"
                  v-model.number="extraction.tax_minor"
                  type="number"
                  @input="recalculateGrandTotal"
                />
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Potongan Harga (Diskon):</span>
              <div class="w-32">
                <RdInput
                  id="ocr_discount"
                  v-model.number="extraction.discount_minor"
                  type="number"
                  @input="recalculateGrandTotal"
                />
              </div>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-slate-200 dark:border-slate-800 text-sm font-bold">
              <span>Total Akhir Kuitansi:</span>
              <span class="text-teal-800 dark:text-teal-300 font-mono">
                <RdMoney :amount-minor="extraction.total_minor" />
              </span>
            </div>
          </div>
        </RdCard>

        <!-- Aksi Simpan Final -->
        <div class="flex items-center justify-end gap-3">
          <RdButton variant="outline" @click="hasResult = false">
            Batalkan Ekstraksi
          </RdButton>
          <form method="POST" :action="`/t/${tenant.slug}/apps/pekan/receipt-scan/confirm`">
            <RdButton type="submit" variant="primary">
              Simpan Sebagai Transaksi Final
            </RdButton>
          </form>
        </div>
      </div>
    </div>
  </RudimahAppShell>
</template>
