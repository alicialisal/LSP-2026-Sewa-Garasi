<?php

namespace Tests\Feature\Repositories;

use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use App\Repositories\VehicleRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VehicleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new VehicleRepository();
    }

    public function test_cari_nomor_plat(): void
    {
        $kendaraan = Vehicle::factory()->create(['nomor_plat' => 'DD 1234 AB']);

        $this->assertSame($kendaraan->id, $this->repo->cariNomorPlat('DD 1234 AB')->id);
        $this->assertNull($this->repo->cariNomorPlat('DD 9999 ZZ'));
    }

    public function test_simpan_kendaraan_baru_dan_perubahan(): void
    {
        $kendaraan = Vehicle::factory()->make(['nomor_plat' => 'DD 1234 AB']);

        $disimpan = $this->repo->simpan($kendaraan);
        $this->assertNotNull($disimpan->id);

        $disimpan->merek = 'Honda';
        $this->repo->simpan($disimpan);

        $this->assertSame('Honda', $disimpan->fresh()->merek);
    }

    /**
     * Beberapa sepeda tanpa plat boleh ada bersamaan (indeks unik MySQL mengizinkan banyak NULL).
     */
    public function test_banyak_sepeda_tanpa_plat_diperbolehkan(): void
    {
        $this->repo->simpan(Vehicle::factory()->sepeda()->make());
        $this->repo->simpan(Vehicle::factory()->sepeda()->make());

        $this->assertSame(2, Vehicle::query()->whereNull('nomor_plat')->count());
    }

    /**
     * Kendaraan yang pernah diparkir penyewa: hanya miliknya dan yang punya
     * sewa, terbaru diparkir lebih dulu, dengan penanda sedang disewa hari ini.
     */
    public function test_pernah_diparkir_penyewa(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();
        $mobil = Vehicle::factory()->create(['penyewa_id' => $penyewa->id]);
        $motor = Vehicle::factory()->create(['penyewa_id' => $penyewa->id]);
        Vehicle::factory()->create(['penyewa_id' => $penyewa->id]); // belum pernah diparkir
        Rental::factory()->periode('2026-10-01', '2026-10-03')->create(['penyewa_id' => $penyewa->id, 'vehicle_id' => $mobil->id]);
        Rental::factory()->periode('2026-11-20', '2026-11-23')->create(['penyewa_id' => $penyewa->id, 'vehicle_id' => $motor->id]);
        Rental::factory()->periode('2026-11-20', '2026-11-23')->create(); // milik penyewa lain

        $hasil = $this->repo->pernahDiparkirPenyewa($penyewa->id, CarbonImmutable::parse('2026-11-21'));

        $this->assertSame([$motor->id, $mobil->id], $hasil->pluck('id')->all());
        $this->assertTrue((bool) $hasil[0]->sedang_disewa);
        $this->assertFalse((bool) $hasil[1]->sedang_disewa);
    }

    /**
     * Nomor plat unik di seluruh sistem, diperkuat constraint UNIQUE.
     */
    public function test_nomor_plat_ganda_ditolak_oleh_constraint(): void
    {
        Vehicle::factory()->create(['nomor_plat' => 'DD 1234 AB']);

        $this->expectException(QueryException::class);

        $this->repo->simpan(Vehicle::factory()->make(['nomor_plat' => 'DD 1234 AB']));
    }
}
