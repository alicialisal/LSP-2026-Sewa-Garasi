<?php

namespace App\Domain\Models;

/**
 * Garasi mobil (kode C001-C999). Tanpa biaya tambahan, termasuk untuk mobil
 * listrik yang parkir di garasi ini.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class CarGarage extends Garage
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['type' => 'CAR'];

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
