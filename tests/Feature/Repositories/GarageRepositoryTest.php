<?php

namespace Tests\Feature\Repositories;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Domain\Models\Rental;
use App\Repositories\GarageRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GarageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private GarageRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new GarageRepository();
    }

    public function test_nomor_terakhir_nol_bila_belum_ada(): void
    {
        $this->assertSame(0, $this->repo->nomorTerakhir(GarageType::BIKE));
    }

    /**
     * Nomor dibandingkan sebagai angka (10 > 2) dan dihitung per tipe.
     */
    public function test_nomor_terakhir_per_tipe_secara_numerik(): void
    {
        Garage::factory()->car('C002')->create();
        Garage::factory()->car('C010')->create();
        Garage::factory()->bike('S005')->create();

        $this->assertSame(10, $this->repo->nomorTerakhir(GarageType::CAR));
        $this->assertSame(5, $this->repo->nomorTerakhir(GarageType::BIKE));
        $this->assertSame(0, $this->repo->nomorTerakhir(GarageType::ELECTRIC));
    }

    /**
     * Opsi A: query lewat satu kelas turunan tetap memuat kelas yang sesuai tipe.
     */
    public function test_cari_id_mengembalikan_kelas_turunan_sesuai_tipe(): void
    {
        Garage::factory()->electric('E001')->create();
        Garage::factory()->car('C001')->create();

        $listrik = $this->repo->cariId('E001');
        $mobil = $this->repo->cariId('C001');

        $this->assertInstanceOf(ElectricCarGarage::class, $listrik);
        $this->assertInstanceOf(CarGarage::class, $mobil);
        $this->assertSame(2500.0, $listrik->harga_listrik_per_kwh);
    }

    public function test_cari_id_tidak_ditemukan_mengembalikan_null(): void
    {
        $this->assertNull($this->repo->cariId('C999'));
    }

    public function test_cari_id_dengan_kunci_berjalan_di_dalam_transaksi(): void
    {
        Garage::factory()->car('C001')->create();

        $garasi = DB::transaction(fn () => $this->repo->cariId('C001', true));

        $this->assertInstanceOf(CarGarage::class, $garasi);
    }

    /**
     * Setara UT-AC-07 s/d UT-AC-10 terhadap basis data sungguhan.
     */
    public function test_tersedia_pada_menyaring_sewa_aktif_yang_beririsan(): void
    {
        Garage::factory()->car('C001')->create();
        Garage::factory()->car('C002')->create();
        Garage::factory()->car('C003')->create();
        Garage::factory()->car('C004')->create();
        Garage::factory()->bike('S001')->create();

        // C001: sewa AKTIF beririsan hari ini -> tidak tersedia.
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['garage_id' => 'C001']);
        // C002: sewa SELESAI historis yang tanggalnya beririsan -> tetap tersedia.
        Rental::factory()->periode('2026-11-21', '2026-11-23')->selesai()->create(['garage_id' => 'C002']);
        // C003: sewa AKTIF yang sudah lewat -> tersedia.
        Rental::factory()->periode('2026-11-01', '2026-11-20')->create(['garage_id' => 'C003']);
        // C004: tanpa sewa -> tersedia.

        $tanggal = CarbonImmutable::parse('2026-11-21');
        $hasil = $this->repo->tersediaPada(GarageType::CAR, $tanggal, $tanggal);

        $this->assertSame(['C002', 'C003', 'C004'], $hasil->pluck('id')->all());
        $this->assertContainsOnlyInstancesOf(CarGarage::class, $hasil);
    }

    /**
     * Hari terakhir sewa lama bersifat inklusif: sewa berakhir 21 Nov masih menghalangi 21 Nov.
     */
    public function test_hari_terakhir_sewa_menghalangi(): void
    {
        Garage::factory()->car('C001')->create();
        Rental::factory()->periode('2026-11-19', '2026-11-21')->create(['garage_id' => 'C001']);

        $hariIni = CarbonImmutable::parse('2026-11-21');
        $besok = CarbonImmutable::parse('2026-11-22');

        $this->assertCount(0, $this->repo->tersediaPada(GarageType::CAR, $hariIni, $hariIni));
        $this->assertCount(1, $this->repo->tersediaPada(GarageType::CAR, $besok, $besok));
    }

    /**
     * simpanBerkelompok memuat kelas turunan sesuai tipe dan menyimpan seluruh baris.
     */
    public function test_simpan_berkelompok(): void
    {
        $hasil = $this->repo->simpanBerkelompok([
            ['id' => 'E001', 'type' => GarageType::ELECTRIC, 'harga_sewa_harian' => 30000.0, 'harga_listrik_per_kwh' => 2500.0, 'status' => GarageStatus::AVAILABLE],
            ['id' => 'E002', 'type' => GarageType::ELECTRIC, 'harga_sewa_harian' => 30000.0, 'harga_listrik_per_kwh' => 2500.0, 'status' => GarageStatus::AVAILABLE],
        ]);

        $this->assertCount(2, $hasil);
        $this->assertContainsOnlyInstancesOf(ElectricCarGarage::class, $hasil);
        $this->assertDatabaseHas('garages', ['id' => 'E002', 'type' => 'ELECTRIC', 'status' => 'AVAILABLE']);
        $this->assertSame(2, $this->repo->nomorTerakhir(GarageType::ELECTRIC));
    }

    /**
     * semua(): urut menurut tipe (sepeda, mobil, listrik) lalu kode; sewa aktif ikut dimuat.
     */
    public function test_semua_garasi_berurutan_dengan_sewa_aktif(): void
    {
        Garage::factory()->electric('E001')->create();
        Garage::factory()->car('C002')->used()->create();
        Garage::factory()->car('C001')->create();
        Garage::factory()->bike('S001')->create();
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['garage_id' => 'C002']);
        Rental::factory()->periode('2026-11-01', '2026-11-03')->selesai()->create(['garage_id' => 'C002', 'nomor_nota' => 'SEW-20261101-0001']);

        $semua = $this->repo->semua(null);

        $this->assertSame(['S001', 'C001', 'C002', 'E001'], $semua->pluck('id')->all());
        $c002 = $semua->firstWhere('id', 'C002');
        $this->assertTrue($c002->relationLoaded('rentals'));
        $this->assertCount(1, $c002->rentals);
        $this->assertSame(['C001', 'C002'], $this->repo->semua(GarageType::CAR)->pluck('id')->all());
    }

    public function test_jumlah_per_tipe_dan_status(): void
    {
        Garage::factory()->car('C001')->create();
        Garage::factory()->car('C002')->used()->create();
        Garage::factory()->car('C003')->used()->create();
        Garage::factory()->electric('E001')->create();

        $this->assertEquals([
            'CAR' => ['AVAILABLE' => 1, 'USED' => 2],
            'ELECTRIC' => ['AVAILABLE' => 1],
        ], $this->repo->jumlahPerTipeDanStatus());
    }

    public function test_ubah_status(): void
    {
        Garage::factory()->car('C001')->create();

        $this->repo->ubahStatus('C001', GarageStatus::USED);

        $this->assertSame(GarageStatus::USED, $this->repo->cariId('C001')->status);
    }
}
