<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\GarageType;
use App\Domain\Enums\VehicleType;
use PHPUnit\Framework\TestCase;

class VehicleTypeTest extends TestCase
{
    /**
     * UT-VT-01 - Sepeda tidak wajib nomor plat.
     */
    public function test_sepeda_tidak_wajib_nomor_plat(): void
    {
        $this->assertFalse(VehicleType::SEPEDA->wajibNomorPlat());
    }

    /**
     * UT-VT-02 - Sepeda motor, mobil, dan mobil listrik wajib nomor plat.
     */
    public function test_kendaraan_bermotor_wajib_nomor_plat(): void
    {
        $this->assertTrue(VehicleType::SEPEDA_MOTOR->wajibNomorPlat());
        $this->assertTrue(VehicleType::MOBIL->wajibNomorPlat());
        $this->assertTrue(VehicleType::MOBIL_LISTRIK->wajibNomorPlat());
    }

    /**
     * UT-VT-03 - Hanya mobil listrik wajib info baterai.
     */
    public function test_mobil_listrik_wajib_info_baterai(): void
    {
        $this->assertTrue(VehicleType::MOBIL_LISTRIK->wajibInfoBaterai());
    }

    /**
     * UT-VT-04 - Sepeda, sepeda motor, dan mobil bensin tidak wajib info baterai.
     */
    public function test_kendaraan_non_listrik_tidak_wajib_info_baterai(): void
    {
        $this->assertFalse(VehicleType::SEPEDA->wajibInfoBaterai());
        $this->assertFalse(VehicleType::SEPEDA_MOTOR->wajibInfoBaterai());
        $this->assertFalse(VehicleType::MOBIL->wajibInfoBaterai());
    }

    /**
     * UT-VT-05 - Sepeda dan sepeda motor hanya boleh di garasi sepeda.
     */
    public function test_sepeda_dan_sepeda_motor_hanya_garasi_bike(): void
    {
        $this->assertSame([GarageType::BIKE], VehicleType::SEPEDA->tipeGarasiDiizinkan());
        $this->assertSame([GarageType::BIKE], VehicleType::SEPEDA_MOTOR->tipeGarasiDiizinkan());
    }

    /**
     * UT-VT-06 - Mobil bensin hanya boleh di garasi mobil.
     */
    public function test_mobil_hanya_garasi_car(): void
    {
        $this->assertSame([GarageType::CAR], VehicleType::MOBIL->tipeGarasiDiizinkan());
    }

    /**
     * UT-VT-07 - Mobil listrik boleh di garasi mobil atau garasi mobil listrik.
     */
    public function test_mobil_listrik_boleh_garasi_car_dan_electric(): void
    {
        $this->assertSame(
            [GarageType::CAR, GarageType::ELECTRIC],
            VehicleType::MOBIL_LISTRIK->tipeGarasiDiizinkan(),
        );
    }
}
