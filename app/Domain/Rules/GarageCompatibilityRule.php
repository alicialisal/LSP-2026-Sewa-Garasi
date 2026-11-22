<?php

namespace App\Domain\Rules;

use App\Domain\Enums\GarageType;
use App\Domain\Enums\VehicleType;

/**
 * Aturan kompatibilitas (asumsi nomor 11): sepeda dan sepeda motor ke garasi
 * sepeda, mobil bensin ke garasi mobil, mobil listrik ke garasi mobil atau
 * garasi mobil listrik. Aturan sebenarnya berada di VehicleType.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarageCompatibilityRule
{
    /**
     * Apakah tipe kendaraan boleh memakai tipe garasi tersebut.
     *
     * @param  VehicleType  $kendaraan  tipe kendaraan
     * @param  GarageType  $garasi  tipe garasi tujuan
     * @return bool true jika tipe garasi diizinkan untuk tipe kendaraan
     */
    public function lolos(VehicleType $kendaraan, GarageType $garasi): bool
    {
        return in_array($garasi, $kendaraan->tipeGarasiDiizinkan(), true);
    }

    /**
     * Pesan galat bila aturan tidak terpenuhi.
     *
     * @return string pesan galat
     */
    public function pesan(): string
    {
        return 'Tipe kendaraan tidak cocok dengan tipe garasi yang dipilih.';
    }
}
