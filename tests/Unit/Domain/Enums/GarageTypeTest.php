<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\GarageType;
use App\Domain\Models\BikeGarage;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GarageTypeTest extends TestCase
{
    /**
     * Awalan kode garasi: S001-S999, C001-C999, E001-E999 (erd.dbml).
     */
    #[DataProvider('prefixProvider')]
    public function test_prefix_kode(GarageType $tipe, string $diharapkan): void
    {
        $this->assertSame($diharapkan, $tipe->prefixKode());
    }

    /**
     * @return array<string, array{GarageType, string}>
     */
    public static function prefixProvider(): array
    {
        return [
            'BIKE' => [GarageType::BIKE, 'S'],
            'CAR' => [GarageType::CAR, 'C'],
            'ELECTRIC' => [GarageType::ELECTRIC, 'E'],
        ];
    }

    /**
     * Setiap tipe memiliki label yang terbaca penyewa.
     */
    /**
     * Pemetaan tipe ke kelas turunan Garage (Single Table Inheritance).
     */
    public function test_kelas_model(): void
    {
        $this->assertSame(BikeGarage::class, GarageType::BIKE->kelasModel());
        $this->assertSame(CarGarage::class, GarageType::CAR->kelasModel());
        $this->assertSame(ElectricCarGarage::class, GarageType::ELECTRIC->kelasModel());
    }

    public function test_setiap_tipe_memiliki_label(): void
    {
        foreach (GarageType::cases() as $tipe) {
            $this->assertNotSame('', $tipe->label());
        }
    }
}
