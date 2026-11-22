<?php

namespace Tests\Feature;

use App\Domain\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migrasi 2026_11_22_000002: plat lama diseragamkan ke format simpan
 * "DD 1234 AB". Plat yang tidak sesuai struktur dan kendaraan tanpa plat
 * dibiarkan.
 */
class MigrasiNomorPlatTest extends TestCase
{
    use RefreshDatabase;

    public function test_plat_lama_diseragamkan_dan_nilai_lain_dibiarkan(): void
    {
        $rapat = Vehicle::factory()->create(['nomor_plat' => 'DD1001AA']);
        $hurufKecil = Vehicle::factory()->create(['nomor_plat' => 'b1a']);
        $sudahBenar = Vehicle::factory()->create(['nomor_plat' => 'D 777']);
        $tidakValid = Vehicle::factory()->create(['nomor_plat' => 'TEST12']);
        $sepeda = Vehicle::factory()->sepeda()->create();

        $migrasi = require database_path('migrations/2026_11_22_000002_seragamkan_nomor_plat_kendaraan.php');
        $migrasi->up();

        $this->assertSame('DD 1001 AA', $rapat->refresh()->nomor_plat);
        $this->assertSame('B 1 A', $hurufKecil->refresh()->nomor_plat);
        $this->assertSame('D 777', $sudahBenar->refresh()->nomor_plat);
        $this->assertSame('TEST12', $tidakValid->refresh()->nomor_plat);
        $this->assertNull($sepeda->refresh()->nomor_plat);
    }
}
