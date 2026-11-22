/**
 * Format rupiah di sisi browser, sama dengan CurrencyFormatter::keRupiah():
 * tanpa pecahan, pemisah ribuan titik. Contoh: 1500000 -> "Rp1.500.000".
 */
export default function rupiah(nilai) {
    const angka = Math.round(Number(nilai) || 0);

    return 'Rp' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
