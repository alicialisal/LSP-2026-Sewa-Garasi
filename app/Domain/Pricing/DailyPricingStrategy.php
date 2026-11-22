<?php

namespace App\Domain\Pricing;

use App\Domain\Models\Garage;

/**
 * Sewa harian: harga sewa harian x jumlah hari.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class DailyPricingStrategy implements PricingStrategy
{
    /**
     * Biaya sewa harian, dibulatkan dua desimal.
     *
     * @param  Garage  $garasi  garasi yang disewa, sumber harga sewa harian (rupiah)
     * @param  RentalPeriod  $periode  periode sewa, sumber jumlah hari
     * @return float biaya sewa dalam rupiah
     */
    public function hitungBiayaSewa(Garage $garasi, RentalPeriod $periode): float
    {
        return round($garasi->harga_sewa_harian * $periode->jumlahHari, 2);
    }
}
