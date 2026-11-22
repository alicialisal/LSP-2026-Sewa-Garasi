<?php

namespace Tests\Feature;

use App\Domain\Models\Penyewa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi 2026_11_22_000001: data lama diseragamkan menjadi +62... dan kolom
 * nomor_telepon menjadi 15 karakter.
 */
class MigrasiNomorTeleponTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_nomor_telepon_15_karakter(): void
    {
        $kolom = collect(Schema::getColumns('penyewa'))->firstWhere('name', 'nomor_telepon');

        $this->assertSame('varchar(15)', $kolom['type']);
    }

    public function test_data_lama_diseragamkan_dan_nilai_tidak_valid_dibiarkan(): void
    {
        $lama = Penyewa::factory()->create(['nomor_telepon' => '081234567890']);
        $berawalan62 = Penyewa::factory()->create(['nomor_telepon' => '6285712345678']);
        $sudahBenar = Penyewa::factory()->create(['nomor_telepon' => '+6281111111111']);
        $tidakValid = Penyewa::factory()->create(['nomor_telepon' => '0211234567']);

        $migrasi = require database_path('migrations/2026_11_22_000001_ubah_nomor_telepon_penyewa.php');
        $migrasi->up();

        $this->assertSame('+6281234567890', $lama->refresh()->nomor_telepon);
        $this->assertSame('+6285712345678', $berawalan62->refresh()->nomor_telepon);
        $this->assertSame('+6281111111111', $sudahBenar->refresh()->nomor_telepon);
        $this->assertSame('0211234567', $tidakValid->refresh()->nomor_telepon);
    }
}
