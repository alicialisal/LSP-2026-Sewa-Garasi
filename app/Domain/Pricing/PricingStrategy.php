<?php

namespace App\Domain\Pricing;

use App\Domain\Models\Garage;

/**
 * Strategi perhitungan biaya sewa (pola Strategy).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
interface PricingStrategy
{
    /**
     * Biaya sewa untuk garasi dan periode tertentu, tanpa biaya listrik.
     *
     * @param  Garage  $garasi  garasi yang disewa
     * @param  RentalPeriod  $periode  periode sewa
     * @return float biaya sewa dalam rupiah
     */
    public function hitungBiayaSewa(Garage $garasi, RentalPeriod $periode): float;
}
