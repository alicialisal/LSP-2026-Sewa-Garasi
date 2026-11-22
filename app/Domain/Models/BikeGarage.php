<?php

namespace App\Domain\Models;

/**
 * Garasi sepeda dan sepeda motor (kode S001-S999). Tanpa biaya tambahan.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class BikeGarage extends Garage
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['type' => 'BIKE'];

    /**
     * Garasi ini tidak mengenakan biaya tambahan.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang diparkir; tidak dipakai
     * @param  float  $sisaBateraiKwh  sisa baterai dalam kWh; tidak dipakai
     * @return float selalu 0
     */
    public function biayaTambahan(Vehicle $kendaraan, float $sisaBateraiKwh): float
    {
        return 0.0;
    }
}
