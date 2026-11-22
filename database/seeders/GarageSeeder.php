<?php

namespace Database\Seeders;

use App\Domain\Enums\GarageType;
use App\Domain\Models\CarGarage;
use App\Services\GarageProvisioningService;
use Illuminate\Database\Seeder;

/**
 * Garasi contoh untuk demo, dibuat lewat GarageProvisioningService agar kode
 * dan aturannya sama dengan pembuatan oleh admin. Harga hanya contoh.
 * Tidak melakukan apa pun bila sudah ada garasi.
 */
class GarageSeeder extends Seeder
{
    public function run(GarageProvisioningService $service): void
    {
        if (CarGarage::query()->exists()) {
            return;
        }

        $service->buatBerkelompok(['tipeGarasi' => GarageType::BIKE, 'jumlah' => 5, 'hargaSewaHarian' => 5000, 'hargaListrikPerKwh' => null]);
        $service->buatBerkelompok(['tipeGarasi' => GarageType::CAR, 'jumlah' => 5, 'hargaSewaHarian' => 25000, 'hargaListrikPerKwh' => null]);
        $service->buatBerkelompok(['tipeGarasi' => GarageType::ELECTRIC, 'jumlah' => 3, 'hargaSewaHarian' => 30000, 'hargaListrikPerKwh' => 2500]);
    }
}
