<?php

namespace App\Domain\Models;

/**
 * Garasi mobil listrik (kode E001-E999). Satu-satunya tipe yang mengenakan
 * biaya isi listrik: (kapasitas baterai - sisa baterai) x harga per kWh.
 *
 * harga_listrik_per_kwh dalam rupiah per kWh.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class ElectricCarGarage extends Garage
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['type' => 'ELECTRIC'];

    /**
     * Kebutuhan pengisian (kWh) untuk kendaraan ini; nol bila bukan mobil listrik.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang akan diisi
     * @param  float  $sisaBateraiKwh  sisa baterai saat sewa dibuat, dalam kWh
     * @return float jumlah kWh yang perlu diisi
     */
    public function kwhDibutuhkan(Vehicle $kendaraan, float $sisaBateraiKwh): float
    {
        if (! $kendaraan->isElectric()) {
            return 0.0;
        }

        return $kendaraan->kebutuhanPengisianKwh($sisaBateraiKwh);
    }

    /**
     * Biaya isi listrik = kWh dibutuhkan x harga listrik per kWh, dibulatkan
     * dua desimal.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang diparkir
     * @param  float  $sisaBateraiKwh  sisa baterai saat sewa dibuat, dalam kWh
     * @return float biaya listrik dalam rupiah; 0 bila bukan mobil listrik
     */
    public function biayaTambahan(Vehicle $kendaraan, float $sisaBateraiKwh): float
    {
        return round(
            $this->kwhDibutuhkan($kendaraan, $sisaBateraiKwh) * (float) $this->harga_listrik_per_kwh,
            2,
        );
    }
}
