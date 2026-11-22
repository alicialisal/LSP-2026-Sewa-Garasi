<?php

namespace App\Domain\Rules;

/**
 * Aturan sisa baterai (asumsi nomor 10): kapasitas positif dan
 * 0 <= sisa <= kapasitas.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class BatteryLevelRule
{
    /**
     * Apakah kapasitas dan sisa baterai memenuhi aturan.
     *
     * @param  float  $kapasitasKwh  kapasitas baterai dalam kWh, harus lebih dari 0
     * @param  float  $sisaKwh  sisa baterai dalam kWh, 0 sampai kapasitas
     * @return bool true jika aturan terpenuhi
     */
    public function lolos(float $kapasitasKwh, float $sisaKwh): bool
    {
        return $kapasitasKwh > 0
            && $sisaKwh >= 0
            && $sisaKwh <= $kapasitasKwh;
    }

    /**
     * Pesan galat bila aturan tidak terpenuhi.
     *
     * @return string pesan galat
     */
    public function pesan(): string
    {
        return 'Sisa baterai harus antara 0 dan kapasitas baterai.';
    }
}
