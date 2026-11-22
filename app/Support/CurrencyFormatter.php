<?php

namespace App\Support;

/**
 * Format mata uang rupiah. Fungsi murni tanpa status.
 * keAngka() dan bulatkanRatusan() belum dibuat karena belum punya skenario.
 * Dipakai AdminExtensionController untuk menampilkan biaya.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class CurrencyFormatter
{
    /**
     * Contoh: 1500000 menjadi "Rp1.500.000". Pecahan dibulatkan ke rupiah.
     *
     * @param  int|float|string  $nilai  nominal dalam rupiah
     * @return string nominal berformat rupiah dengan pemisah ribuan titik
     */
    public function keRupiah(int|float|string $nilai): string
    {
        return 'Rp'.number_format((float) $nilai, 0, ',', '.');
    }
}
