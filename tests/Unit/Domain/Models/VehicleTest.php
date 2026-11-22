<?php

namespace Tests\Unit\Domain\Models;

use App\Domain\Enums\VehicleType;
use App\Domain\Models\Vehicle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    private function kendaraan(VehicleType $tipe, ?float $kapasitas = null, int $penyewaId = 1): Vehicle
    {
        return (new Vehicle())->forceFill([
            'penyewa_id' => $penyewaId,
            'type' => $tipe,
            'kapasitas_baterai_kwh' => $kapasitas,
        ]);
    }

    /**
     * UT-PR-18 - Kebutuhan pengisian = kapasitas - sisa.
     */
    public function test_kebutuhan_pengisian_kwh(): void
    {
        $mobil = $this->kendaraan(VehicleType::MOBIL_LISTRIK, 60);

        $this->assertEqualsWithDelta(40, $mobil->kebutuhanPengisianKwh(20), 0.001);
    }

    /**
     * UT-PR-19 - Sisa melebihi kapasitas ditolak.
     */
    public function test_kebutuhan_pengisian_menolak_sisa_melebihi_kapasitas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->kendaraan(VehicleType::MOBIL_LISTRIK, 60)->kebutuhanPengisianKwh(70);
    }

    /**
     * UT-PR-20 - Kendaraan tanpa kapasitas baterai (sepeda) tidak butuh pengisian.
     */
    public function test_kebutuhan_pengisian_nol_tanpa_kapasitas(): void
    {
        $this->assertEqualsWithDelta(0, $this->kendaraan(VehicleType::SEPEDA)->kebutuhanPengisianKwh(0), 0.001);
    }

    public function test_sisa_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->kendaraan(VehicleType::MOBIL_LISTRIK, 60)->kebutuhanPengisianKwh(-1);
    }

    public function test_is_electric_hanya_untuk_mobil_listrik(): void
    {
        $this->assertTrue($this->kendaraan(VehicleType::MOBIL_LISTRIK, 60)->isElectric());
        $this->assertFalse($this->kendaraan(VehicleType::MOBIL)->isElectric());
        $this->assertFalse($this->kendaraan(VehicleType::SEPEDA_MOTOR)->isElectric());
    }

    public function test_dimiliki_oleh(): void
    {
        $mobil = $this->kendaraan(VehicleType::MOBIL, null, 7);

        $this->assertTrue($mobil->dimilikiOleh(7));
        $this->assertFalse($mobil->dimilikiOleh(8));
    }
}
