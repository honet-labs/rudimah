<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RudimahAppShell from '../../../layouts/RudimahAppShell.vue';
import RdPageHeader from '../../../design-system/RdPageHeader.vue';
import RdCard from '../../../design-system/RdCard.vue';
import RdButton from '../../../design-system/RdButton.vue';
import RdFileUpload from '../../../design-system/RdFileUpload.vue';
import RdInput from '../../../design-system/RdInput.vue';
import RdSelect from '../../../design-system/RdSelect.vue';
import RdMoney from '../../../design-system/RdMoney.vue';
import RdAlert from '../../../design-system/RdAlert.vue';
import RdModal from '../../../design-system/RdModal.vue';
import { secureFetch } from '../../../utils/csrf';
import type { User, Tenant, AppManifest } from '../../../types/core';
import type { ReceiptExtraction, AttachmentImage } from '../../../types/pekan';

interface CategoryOption {
  id: string;
  name: string;
}

const props = defineProps<{
  user?: User;
  tenant: Tenant;
  tenants?: Tenant[];
  apps?: AppManifest[];
  categories?: CategoryOption[];
}>();

const isProcessing = ref(false);
const isSaving = ref(false);
const hasResult = ref(false);
const errorMessage = ref<string | null>(null);
const scanId = ref<string | null>(null);
const scannedImages = ref<AttachmentImage[]>([]);
const previewModalOpen = ref(false);
const previewImage = ref<{ url: string; title: string }>({ url: '', title: '' });

function openPreview(url: string, title: string) {
  previewImage.value = { url, title };
  previewModalOpen.value = true;
}

const selectedCategoryId = ref(props.categories?.[0]?.id || '');

const extraction = ref<ReceiptExtraction>({
  description: '',
  merchant_name: '',
  receipt_number: '',
  transaction_date: new Date().toISOString().substring(0, 10),
  currency: 'Rp',
  subtotal_minor: 0,
  tax_minor: 0,
  service_charge_minor: 0,
  discount_minor: 0,
  total_minor: 0,
  items: [],
});

async function handleFileSelected(file: File) {
  isProcessing.value = true;
  errorMessage.value = null;
  hasResult.value = false;

  const formData = new FormData();
  formData.append('receipt_file', file);

  const abortCtrl = new AbortController();
  const timeoutId = setTimeout(() => abortCtrl.abort(), 45000);

  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/apps/pekan/receipt-scan/upload`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
      },
      body: formData,
      signal: abortCtrl.signal,
    });

    const data = await res.json().catch(() => ({}));

    if (res.ok && data.success) {
      scanId.value = data.scan_id;
      scannedImages.value = Array.isArray(data.images) ? data.images : (data.extracted?._receipt_images || []);
      const ext = data.extracted || {};
      extraction.value = {
        description: ext.description || ext.merchant_name || 'Belanja',
        merchant_name: ext.merchant_name || 'Toko Belanja',
        receipt_number: ext.receipt_number || '',
        transaction_date: ext.transaction_date || new Date().toISOString().substring(0, 10),
        currency: 'Rp',
        subtotal_minor: Number(ext.subtotal_minor) || 0,
        tax_minor: Number(ext.tax_minor) || 0,
        service_charge_minor: Number(ext.service_charge_minor) || 0,
        discount_minor: Number(ext.discount_minor) || 0,
        total_minor: Number(ext.total_minor) || 0,
        items: Array.isArray(ext.items) ? ext.items.map((it: any) => ({
          item_name: it.item_name || 'Item',
          quantity: Number(it.quantity) || 1,
          price_minor: Number(it.price_minor) || 0,
          discount_minor: Number(it.discount_minor) || 0,
          total_minor: Number(it.total_minor) || 0,
        })) : [],
      };

      if (extraction.value.items.length === 0) {
        extraction.value.items.push({
          item_name: 'Belanja Umum',
          quantity: 1,
          price_minor: extraction.value.total_minor,
          discount_minor: 0,
          total_minor: extraction.value.total_minor,
        });
      }

      recalculateGrandTotal();
      hasResult.value = true;
    } else {
      errorMessage.value =
        data.message ||
        data.error ||
        (data.errors ? Object.values(data.errors).flat().join(', ') : '') ||
        'Gagal memproses pemindaian berkas struk. Pastikan berkas struk terbaca jelas.';
    }
  } catch (err: any) {
    if (err?.name === 'AbortError') {
      errorMessage.value = 'Waktu proses pemindaian struk melebihi batas (timeout 45 detik). Silakan coba unggah kembali dengan foto struk yang lebih fokus atau terang.';
    } else {
      errorMessage.value = 'Terjadi gangguan jaringan saat mengunggah struk: ' + (err.message || 'Koneksi gagal');
    }
  } finally {
    clearTimeout(timeoutId);
    isProcessing.value = false;
  }
}

function addItemRow() {
  extraction.value.items.push({
    item_name: '',
    quantity: 1,
    price_minor: 0,
    discount_minor: 0,
    total_minor: 0,
  });
}

function removeItemRow(idx: number) {
  extraction.value.items.splice(idx, 1);
  recalculateGrandTotal();
}

function updateLineTotal(idx: number) {
  const item = extraction.value.items[idx];
  if (!item) return;
  const qty = Number(item.quantity) || 1;
  const price = Number(item.price_minor) || 0;
  const discount = Number(item.discount_minor) || 0;
  item.total_minor = Math.max(0, (qty * price) - discount);
  recalculateGrandTotal();
}

function recalculateGrandTotal() {
  const sumItems = extraction.value.items.reduce((acc, it) => acc + (Number(it.total_minor) || 0), 0);
  extraction.value.subtotal_minor = sumItems;
  const tax = Number(extraction.value.tax_minor) || 0;
  const service = Number(extraction.value.service_charge_minor) || 0;
  const discount = Number(extraction.value.discount_minor) || 0;
  extraction.value.total_minor = Math.max(0, sumItems + tax + service - discount);
}

async function saveConfirmedTransaction() {
  if (!extraction.value.merchant_name) {
    errorMessage.value = 'Nama merchant / toko wajib diisi.';
    return;
  }

  isSaving.value = true;
  errorMessage.value = null;

  try {
    const res = await secureFetch(`/t/${props.tenant.slug}/apps/pekan/receipt-scan/confirm`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        scan_id: scanId.value,
        description: extraction.value.description || extraction.value.merchant_name || 'Belanja',
        merchant_name: extraction.value.merchant_name,
        receipt_number: extraction.value.receipt_number,
        transaction_date: extraction.value.transaction_date,
        category_id: selectedCategoryId.value || undefined,
        subtotal_minor: extraction.value.subtotal_minor,
        tax_minor: extraction.value.tax_minor,
        service_charge_minor: extraction.value.service_charge_minor,
        discount_minor: extraction.value.discount_minor,
        total_minor: extraction.value.total_minor,
        items: extraction.value.items,
        images: scannedImages.value,
      }),
    });

    const data = await res.json();
    if (res.ok && data.success) {
      router.visit(data.redirect_url || `/t/${props.tenant.slug}/apps/pekan/transactions`);
    } else {
      errorMessage.value = data.message || 'Gagal menyimpan transaksi dari struk.';
    }
  } catch (err: any) {
    errorMessage.value = 'Terjadi kesalahan sistem saat menyimpan transaksi: ' + (err.message || 'Koneksi gagal');
  } finally {
    isSaving.value = false;
  }
}

const categoryOptions = computed(() => {
  return (props.categories || []).map(c => ({ label: c.name, value: c.id }));
});
</script>

<template>
  <RudimahAppShell
    :user="user"
    :tenant="tenant"
    :tenants="tenants"
    :apps="apps"
    app-key="pekan"
    app-name="PEKAN"
  >
    <RdPageHeader
      title="Pindai Kuitansi & Struk OCR"
      description="Ekstraksi otomatis bukti belanja dengan model AI vision terpadu dan tinjauan verifikasi sebelum simpan"
      :breadcrumbs="[
        { label: 'PEKAN', href: `/t/${tenant.slug}/apps/pekan/dashboard` },
        { label: 'Pindai Struk' }
      ]"
    />

    <div class="space-y-6 max-w-4xl">
      <!-- Banner Kesalahan Jika Ada -->
      <RdAlert v-if="errorMessage" variant="error" title="Kendala Pemrosesan Struk">
        {{ errorMessage }}
      </RdAlert>

      <!-- Area Unggah Berkas Struk Asli -->
      <RdCard title="Unggah Foto atau Berkas Kuitansi">
        <RdFileUpload @file-selected="handleFileSelected" />
        <div v-if="isProcessing" class="mt-4 p-4 text-center text-xs text-slate-500 bg-slate-50 dark:bg-slate-800 rounded-md">
          <div class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-4 w-4 text-teal-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Model AI Vision sedang membaca dan mengekstrak rincian kuitansi...</span>
          </div>
        </div>
      </RdCard>

      <!-- Panel Tinjauan Hasil Ekstraksi Riil (Human-in-the-loop) -->
      <div v-if="hasResult" class="space-y-6">
        <RdAlert variant="info" title="Konfirmasi & Tinjauan Diperlukan">
          Data berikut diekstraksi secara langsung oleh model AI. Silakan periksa, sesuaikan nominal atau rincian item bila diperlukan sebelum mengesahkannya ke buku kas.
        </RdAlert>

        <!-- Pratinjau Foto Struk yang Dipindai -->
        <RdCard v-if="scannedImages.length > 0" title="Foto Struk yang Dipindai">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div
              v-for="(img, idx) in scannedImages"
              :key="idx"
              class="group relative cursor-pointer border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden bg-slate-50 dark:bg-slate-900 hover:border-teal-600 transition-colors"
              @click="openPreview(img.url, `Foto Struk - ${extraction.merchant_name || 'Kuitansi'}`)"
            >
              <img
                :src="img.url"
                :alt="img.name"
                class="w-28 h-28 sm:w-36 sm:h-36 object-cover rounded"
              />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-xs font-medium gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span>Perbesar</span>
              </div>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1.5">
              <div class="flex items-center gap-1.5 text-slate-800 dark:text-slate-200 font-medium">
                <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Berkas foto struk berhasil disimpan dan akan dilampirkan ke transaksi ini.</span>
              </div>
              <p class="text-slate-500">
                Klik pada foto struk untuk memperbesar tampilan dan mencocokkan rincian item atau nominal dengan fisik struk.
              </p>
            </div>
          </div>
        </RdCard>

        <RdCard title="Hasil Ekstraksi Kuitansi">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <RdInput
              id="ocr_description"
              v-model="extraction.description"
              label="Keterangan Transaksi"
              placeholder="Contoh: Makan siang, Belanja ATK, dsb."
              required
            />
            <RdInput
              id="ocr_merchant"
              v-model="extraction.merchant_name"
              label="Nama Merchant / Toko"
              required
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <RdInput
              id="ocr_receipt_no"
              v-model="extraction.receipt_number"
              label="Nomor Kuitansi / Faktur"
            />
            <RdInput
              id="ocr_date"
              v-model="extraction.transaction_date"
              type="date"
              label="Tanggal Transaksi"
              required
            />
            <div v-if="categoryOptions.length > 0">
              <RdSelect
                v-model="selectedCategoryId"
                label="Kategori Pengeluaran"
                :options="categoryOptions"
              />
            </div>
          </div>

          <!-- Rincian Item Hasil Ekstraksi -->
          <div class="mt-4">
            <div class="flex items-center justify-between mb-2">
              <h4 class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                Daftar Barang & Jasa Terdeteksi ({{ extraction.items.length }})
              </h4>
              <RdButton type="button" variant="outline" size="sm" @click="addItemRow">
                + Tambah Baris Item
              </RdButton>
            </div>

            <div class="space-y-2">
              <div
                v-for="(it, idx) in extraction.items"
                :key="idx"
                class="p-3 border border-slate-200 dark:border-slate-800 rounded-md grid grid-cols-1 sm:grid-cols-12 gap-3 items-center text-xs"
              >
                <div class="sm:col-span-5">
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
                    label="Jumlah"
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
                <div class="sm:col-span-1 flex justify-end">
                  <button
                    type="button"
                    class="p-1.5 text-slate-400 hover:text-red-600 rounded transition-colors"
                    title="Hapus baris ini"
                    @click="removeItemRow(idx)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Ringkasan Angka & Kalkulasi -->
          <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Subtotal Barang:</span>
              <span class="font-mono font-semibold"><RdMoney :amount-minor="extraction.subtotal_minor" /></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Pajak (PPN):</span>
              <div class="w-36">
                <RdInput
                  id="ocr_tax"
                  v-model.number="extraction.tax_minor"
                  type="number"
                  @input="recalculateGrandTotal"
                />
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Biaya Layanan:</span>
              <div class="w-36">
                <RdInput
                  id="ocr_service"
                  v-model.number="extraction.service_charge_minor"
                  type="number"
                  @input="recalculateGrandTotal"
                />
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-500">Potongan Harga (Diskon):</span>
              <div class="w-36">
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
              <span class="text-teal-800 dark:text-teal-300 font-mono text-base">
                <RdMoney :amount-minor="extraction.total_minor" />
              </span>
            </div>
          </div>
        </RdCard>

        <!-- Aksi Simpan Final -->
        <div class="flex items-center justify-end gap-3">
          <RdButton variant="outline" :disabled="isSaving" @click="hasResult = false; scannedImages = []">
            Batalkan Ekstraksi
          </RdButton>
          <RdButton
            type="button"
            variant="primary"
            :loading="isSaving"
            :disabled="isSaving"
            @click="saveConfirmedTransaction"
          >
            Simpan Sebagai Transaksi Final
          </RdButton>
        </div>
      </div>
    </div>

    <!-- Modal Lightbox Pratinjau Foto Struk -->
    <RdModal
      v-model="previewModalOpen"
      :title="previewImage.title"
      max-width="lg"
    >
      <div class="space-y-4">
        <div class="rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-black/5 dark:bg-black/30 flex items-center justify-center min-h-[260px]">
          <img
            :src="previewImage.url"
            :alt="previewImage.title"
            class="max-h-[70vh] w-auto max-w-full object-contain mx-auto rounded"
          />
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
          <span>Foto Asli Kuitansi / Struk Belanja</span>
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
