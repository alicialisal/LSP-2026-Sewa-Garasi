<?php

namespace Tests\Unit\Domain\Rules;

use App\Domain\Enums\GarageType;
use App\Domain\Enums\VehicleType;
use App\Domain\Rules\GarageCompatibilityRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GarageCompatibilityRuleTest extends TestCase
{
    /**
     * UT-GC-01 s/d UT-GC-12 - Seluruh 12 kombinasi kendaraan dan garasi.
     */
    #[DataProvider('kombinasiProvider')]
    public function test_kompatibilitas_kendaraan_dan_garasi(
        VehicleType $kendaraan,
        GarageType $garasi,
        bool $diharapkan,
    ): void {
        $this->assertSame($diharapkan, (new GarageCompatibilityRule())->lolos($kendaraan, $garasi));
    }

    /**
     * @return array<string, array{VehicleType, GarageType, bool}>
     */
    public static function kombinasiProvider(): array
    {
        return [
            'UT-GC-01 sepeda di S' => [VehicleType::SEPEDA, GarageType::BIKE, true],
            'UT-GC-02 sepeda di C' => [VehicleType::SEPEDA, GarageType::CAR, false],
            'UT-GC-03 sepeda di E' => [VehicleType::SEPEDA, GarageType::ELECTRIC, false],
            'UT-GC-04 motor di S' => [VehicleType::SEPEDA_MOTOR, GarageType::BIKE, true],
            'UT-GC-05 motor di C' => [VehicleType::SEPEDA_MOTOR, GarageType::CAR, false],
            'UT-GC-06 motor di E' => [VehicleType::SEPEDA_MOTOR, GarageType::ELECTRIC, false],
            'UT-GC-07 mobil di S' => [VehicleType::MOBIL, GarageType::BIKE, false],
            'UT-GC-08 mobil di C' => [VehicleType::MOBIL, GarageType::CAR, true],
            'UT-GC-09 mobil di E' => [VehicleType::MOBIL, GarageType::ELECTRIC, false],
            'UT-GC-10 mobil listrik di S' => [VehicleType::MOBIL_LISTRIK, GarageType::BIKE, false],
            'UT-GC-11 mobil listrik di C' => [VehicleType::MOBIL_LISTRIK, GarageType::CAR, true],
            'UT-GC-12 mobil listrik di E' => [VehicleType::MOBIL_LISTRIK, GarageType::ELECTRIC, true],
        ];
    }

    /**
     * Pesan kegagalan tersedia untuk ditampilkan di form.
     */
    public function test_pesan_tidak_kosong(): void
    {
        $this->assertNotSame('', (new GarageCompatibilityRule())->pesan());
    }
}
