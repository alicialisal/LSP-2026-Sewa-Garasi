<?php

namespace App\Domain\Pricing;

use App\Domain\Models\Garage;

/**
 * Sewa bulanan: FAKTOR_BULANAN x harga sewa harian, tidak bergantung pada
 * jumlah hari bulan tersebut.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class MonthlyPricingStrategy implements PricingStrategy
{
    /**
     * Aturan "harga sewa bulanan adalah 25 x harga sewa harian".
     */
    public const FAKTOR_BULANAN = 25;

    /**
     * Biaya sewa bulanan, dibulatkan dua desimal.
     *
     * @param  Garage  $garasi  garasi yang disewa, sumber harga sewa harian (rupiah)
     * @param  RentalPeriod  $periode  periode sewa; tidak dipakai dalam perhitungan
     * @return float biaya sewa dalam rupiah
     */
    public function hitungBiayaSewa(Garage $garasi, RentalPeriod $periode): float
    {
        return round($garasi->harga_sewa_harian * self::FAKTOR_BULANAN, 2);
    }
}
