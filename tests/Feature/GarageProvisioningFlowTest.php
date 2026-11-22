<?php

namespace Tests\Feature;

use App\Domain\Enums\GarageType;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Exceptions\KuotaGarasiHabisException;
use App\Services\GarageProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembuatan garasi berkelompok dengan repository Eloquent dan MySQL sungguhan.
 */
class GarageProvisioningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function service(): GarageProvisioningService
    {
        return $this->app->make(GarageProvisioningService::class);
    }

    public function test_batch_berurutan_melanjutkan_nomor_terakhir_per_tipe(): void
    {
        $pertama = $this->service()->buatBerkelompok(['tipeGarasi' => GarageType::ELECTRIC, 'jumlah' => 3, 'hargaSewaHarian' => 30000, 'hargaListrikPerKwh' => 2500]);
        $kedua = $this->service()->buatBerkelompok(['tipeGarasi' => GarageType::ELECTRIC, 'jumlah' => 2, 'hargaSewaHarian' => 30000, 'hargaListrikPerKwh' => 2500]);
        $mobil = $this->service()->buatBerkelompok(['tipeGarasi' => GarageType::CAR, 'jumlah' => 1, 'hargaSewaHarian' => 25000, 'hargaListrikPerKwh' => null]);

        $this->assertSame(['E001', 'E002', 'E003'], $pertama->pluck('id')->all());
        $this->assertSame(['E004', 'E005'], $kedua->pluck('id')->all());
        $this->assertSame(['C001'], $mobil->pluck('id')->all());
        $this->assertContainsOnlyInstancesOf(ElectricCarGarage::class, $pertama);
        $this->assertDatabaseHas('garages', ['id' => 'E005', 'status' => 'AVAILABLE', 'harga_listrik_per_kwh' => '2500.00']);
        $this->assertDatabaseHas('garages', ['id' => 'C001', 'harga_listrik_per_kwh' => null]);
    }

    /**
     * FT-04: saat garasi sepeda terakhir S998, membuat 3 garasi sepeda gagal dan
     * tidak ada garasi baru yang tersimpan; sisa kuota 1.
     */
    public function test_melebihi_sisa_kuota_tidak_menyimpan_apa_pun(): void
    {
        Garage::factory()->bike('S998')->create();

        $this->assertSame(1, $this->service()->sisaKuota(GarageType::BIKE));

        try {
            $this->service()->buatBerkelompok(['tipeGarasi' => GarageType::BIKE, 'jumlah' => 3, 'hargaSewaHarian' => 5000, 'hargaListrikPerKwh' => null]);
            $this->fail('KuotaGarasiHabisException tidak dilempar.');
        } catch (KuotaGarasiHabisException) {
            $this->assertDatabaseCount('garages', 1);
        }
    }

    public function test_kuota_terakhir_dapat_dipakai_tepat(): void
    {
        Garage::factory()->bike('S998')->create();

        $hasil = $this->service()->buatBerkelompok(['tipeGarasi' => GarageType::BIKE, 'jumlah' => 1, 'hargaSewaHarian' => 5000, 'hargaListrikPerKwh' => null]);

        $this->assertSame(['S999'], $hasil->pluck('id')->all());
        $this->assertSame(0, $this->service()->sisaKuota(GarageType::BIKE));
    }
}
