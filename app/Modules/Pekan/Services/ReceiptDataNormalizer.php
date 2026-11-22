<?php

namespace App\Modules\Pekan\Services;

use InvalidArgumentException;

/** One receipt contract for every vision provider; never invent a receipt date. */
class ReceiptDataNormalizer
{
    public static function instructions(): string
    {
        return "\nAturan pembacaan: tambahkan transaction_date_raw berisi teks tanggal persis pada struk. "
            . 'Tanggal Indonesia adalah DD/MM/YYYY atau DD-MM-YY (hari lebih dahulu), bukan MM/DD/YYYY. '
            . 'Gunakan tanggal transaksi, bukan tanggal cetak ulang, kedaluwarsa, atau tanggal hari ini. '
            . 'Jika tanggal tidak terbaca lengkap, transaction_date dan transaction_date_raw harus null; jangan menebak. '
            . 'total_minor adalah TOTAL BELANJA/TOTAL BAYAR setelah diskon, bukan uang tunai, kembalian, atau subtotal. '
            . 'PPN yang sudah termasuk harga jangan ditambahkan lagi. Jangan kurangi diskon dua kali. '
            . 'subtotal_minor + tax_minor + service_charge_minor - discount_minor harus sama dengan total_minor. '
            . 'Pertahankan total tercetak; jika rincian tidak cocok, tetap berikan angka tercetak agar pengguna memeriksa. '
            . 'Jangan mengarang nomor struk. Kembalikan seluruh barang, termasuk barang gratis dengan total 0.';
    }

    public static function date(?string $value): ?string
    {
        $value = trim($value ?? '');
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T\s].*)?$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/\b(\d{1,2})[\/\.\-\s]+(\d{1,2}|[a-zA-Z]+)[\/\.\-\s]+(\d{4}|\d{2})\b/', $value, $m)) {
            $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5,
                'jun' => 6, 'jul' => 7, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10,
                'nov' => 11, 'des' => 12, 'dec' => 12];
            $day = (int) $m[1];
            $month = ctype_digit($m[2]) ? (int) $m[2] : ($months[strtolower(substr($m[2], 0, 3))] ?? 0);
            $year = (int) $m[3];
            if (strlen($m[3]) === 2) $year += 2000;
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    public static function money(mixed $value): int
    {
        if (is_int($value)) return max(0, $value);
        if (is_float($value)) return max(0, (int) round($value));
        $value = preg_replace('/(?:Rp\.?|IDR|\s)/i', '', (string) $value);
        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,00)?$/', $value)) {
            $value = str_replace('.', '', explode(',', $value)[0]);
        } elseif (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.00)?$/', $value)) {
            $value = str_replace(',', '', explode('.', $value)[0]);
        } elseif (preg_match('/^\d+[,.]00$/', $value)) {
            $value = substr($value, 0, -3);
        }
        if (!ctype_digit($value) || strlen($value) > 15) {
            throw new InvalidArgumentException('Nominal struk tidak valid. Silakan pindai ulang atau periksa struk.');
        }
        return (int) $value;
    }

    public static function normalize(array $raw): array
    {
        $items = [];
        foreach (($raw['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $quantity = str_replace(',', '.', (string) ($item['quantity'] ?? 1));
            $quantity = is_numeric($quantity) && (float) $quantity > 0 ? (float) $quantity : 1;
            $price = self::money($item['price_minor'] ?? $item['price'] ?? 0);
            $discount = self::money($item['discount_minor'] ?? $item['discount'] ?? 0);
            $items[] = [
                'item_name' => (string) ($item['item_name'] ?? $item['name'] ?? 'Item'),
                'quantity' => $quantity,
                'price_minor' => $price,
                'discount_minor' => $discount,
                'total_minor' => self::money($item['total_minor'] ?? $item['total'] ?? max(0, (int) round($quantity * $price) - $discount)),
            ];
        }
        $subtotal = self::money($raw['subtotal_minor'] ?? $raw['subtotal'] ?? array_sum(array_column($items, 'total_minor')));
        $tax = self::money($raw['tax_minor'] ?? $raw['tax'] ?? 0);
        $service = self::money($raw['service_charge_minor'] ?? $raw['service_charge'] ?? 0);
        $discount = self::money($raw['discount_minor'] ?? $raw['discount'] ?? 0);
        $total = self::money($raw['total_minor'] ?? $raw['total'] ?? max(0, $subtotal + $tax + $service - $discount));
        $rawDate = $raw['transaction_date_raw'] ?? null;
        // Raw printed evidence takes precedence over a model's potentially US-formatted interpretation.
        $date = $rawDate !== null && trim($rawDate) !== ''
            ? self::date($rawDate) : self::date($raw['transaction_date'] ?? $raw['date'] ?? null);
        if ($total <= 0 || empty($raw['merchant_name'] ?? $raw['store_name'] ?? null)) {
            throw new InvalidArgumentException('Struk belum terbaca lengkap (toko atau total kosong). Gunakan foto yang lebih jelas.');
        }

        return [
            'merchant_name' => (string) ($raw['merchant_name'] ?? $raw['store_name']),
            'receipt_number' => (string) ($raw['receipt_number'] ?? $raw['invoice_number'] ?? ''),
            'transaction_date' => $date,
            'transaction_date_raw' => $rawDate,
            'date_needs_review' => $date === null,
            'currency' => 'IDR',
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'service_charge_minor' => $service,
            'discount_minor' => $discount,
            'total_minor' => $total,
            'totals_match' => $total === $subtotal + $tax + $service - $discount,
            'items' => $items,
        ];
    }
}
