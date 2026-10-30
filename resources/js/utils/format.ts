/**
 * Utilitas pemformatan untuk RUDIMAH
 */

/**
 * Format kuantitas (quantity) item transaksi.
 * Jika bilangan bulat (misal 1 atau 6), tampilkan angka murni tanpa koma/desimal (contoh: "6", "1").
 * Jika memiliki pecahan (misal 2.5 liter bensin), tampilkan dengan koma desimal tanpa angka nol basi di ujung (contoh: "2,5").
 */
export function formatQuantity(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '1';
  }
  const num = typeof value === 'number' ? value : parseFloat(String(value));
  if (isNaN(num)) {
    return '1';
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  }).format(num);
}
