<?php

namespace App\Domain\Pricing;

use App\Domain\Enums\RentalType;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Domain\Models\Vehicle;
use LogicException;

/**
 * Konteks pola Strategy: memilih strategi menurut tipe sewa, lalu menghitung
 * rincian biaya termasuk biaya isi listrik.
 *
 * Objek ini tidak berubah; untukTipeSewa() mengembalikan instance baru.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class PricingContext
{
    public function __construct(private readonly ?PricingStrategy $strategy = null)
    {
    }

    /**
     * Konteks baru dengan strategi sesuai tipe sewa.
     *
     * @param  RentalType  $tipeSewa  tipe sewa, HARIAN atau BULANAN
     * @return self konteks baru dengan strategi harian atau bulanan
     */
    public function untukTipeSewa(RentalType $tipeSewa): self
    {
        return new self(match ($tipeSewa) {
            RentalType::HARIAN => new DailyPricingStrategy(),
            RentalType::BULANAN => new MonthlyPricingStrategy(),
        });
    }

    /**
     * Strategi yang sedang dipakai.
     *
     * @return PricingStrategy strategi perhitungan biaya sewa
     * @throws LogicException jika untukTipeSewa() belum dipanggil
     */
    public function strategi(): PricingStrategy
    {
        return $this->strategy
            ?? throw new LogicException('Strategi belum dipilih. Panggil untukTipeSewa() terlebih dahulu.');
    }

    /**
     * Rincian biaya sewa. Harga listrik per kWh diambil dari garasi dan
     * disertakan di rincian (untuk ditampilkan di estimasi) hanya bila mobil
     * listrik parkir di garasi mobil listrik; harga ini tidak disimpan di
     * tabel sewa.
     *
     * @param  Garage  $garasi  garasi yang disewa
     * @param  Vehicle  $kendaraan  kendaraan yang diparkir
     * @param  float  $sisaBateraiKwh  sisa baterai dalam kWh; diabaikan bila kendaraan bukan mobil listrik
     * @param  RentalPeriod  $periode  periode sewa hasil RentalTypeResolver
     * @return RentalCost rincian biaya sewa, biaya listrik, dan total dalam rupiah
     * @throws LogicException jika untukTipeSewa() belum dipanggil
     */
    public function hitung(Garage $garasi, Vehicle $kendaraan, float $sisaBateraiKwh, RentalPeriod $periode): RentalCost
    {
        $biayaSewa = $this->strategi()->hitungBiayaSewa($garasi, $periode);
        $biayaListrik = $garasi->biayaTambahan($kendaraan, $sisaBateraiKwh);

        $isiListrik = $garasi instanceof ElectricCarGarage && $kendaraan->isElectric();

        return new RentalCost(
            $biayaSewa,
            $isiListrik ? $garasi->kwhDibutuhkan($kendaraan, $sisaBateraiKwh) : 0.0,
            $isiListrik ? $garasi->harga_listrik_per_kwh : null,
            $biayaListrik,
        );
    }
}
