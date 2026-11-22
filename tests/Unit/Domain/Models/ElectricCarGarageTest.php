<?php

namespace Tests\Unit\Domain\Models;

use App\Domain\Enums\VehicleType;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Vehicle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ElectricCarGarageTest extends TestCase
{
    /**
     * UT-PR-07 s/d UT-PR-10 - Biaya isi listrik = (kapasitas - sisa) x harga per kWh.
     */
    #[DataProvider('biayaProvider')]
    public function test_biaya_tambahan(float $kapasitas, float $sisa, float $hargaPerKwh, float $diharapkan): void
    {
        $mobil = (new Vehicle())->forceFill([
            'type' => VehicleType::MOBIL_LISTRIK,
            'kapasitas_baterai_kwh' => $kapasitas,
        ]);
        $garasi = (new ElectricCarGarage())->forceFill([
            'id' => 'E001',
            'harga_listrik_per_kwh' => $hargaPerKwh,
        ]);

        $this->assertEqualsWithDelta($diharapkan, $garasi->biayaTambahan($mobil, $sisa), 0.001);
    }

    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function biayaProvider(): array
    {
        return [
            'UT-PR-07 60/20 kWh' => [60, 20, 2500, 100000],
            'UT-PR-08 sisa = kapasitas' => [60, 60, 2500, 0],
            'UT-PR-09 sisa 0' => [60, 0, 2500, 150000],
            'UT-PR-10 pecahan' => [57.5, 12.3, 2466, 111463.20],
        ];
    }

    public function test_kwh_dibutuhkan(): void
    {
        $mobil = (new Vehicle())->forceFill([
            'type' => VehicleType::MOBIL_LISTRIK,
            'kapasitas_baterai_kwh' => 60,
        ]);
        $garasi = (new ElectricCarGarage())->forceFill(['id' => 'E001', 'harga_listrik_per_kwh' => 2500]);

        $this->assertEqualsWithDelta(40, $garasi->kwhDibutuhkan($mobil, 20), 0.001);
    }
}
