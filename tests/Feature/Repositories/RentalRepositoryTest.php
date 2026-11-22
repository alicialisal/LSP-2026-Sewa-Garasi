<?php

namespace Tests\Feature\Repositories;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use App\Repositories\RentalRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RentalRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new RentalRepository();
    }

    private function tanggal(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal);
    }

    public function test_nomor_nota_terakhir_null_bila_belum_ada(): void
    {
        $this->assertNull($this->repo->nomorNotaTerakhir('SEW-20261121-'));
    }

    public function test_nomor_nota_terakhir_hanya_untuk_hari_yang_diminta(): void
    {
        Rental::factory()->create(['nomor_nota' => 'SEW-20261121-0002']);
        Rental::factory()->create(['nomor_nota' => 'SEW-20261121-0010']);
        Rental::factory()->create(['nomor_nota' => 'SEW-20261122-0001']);

        $this->assertSame('SEW-20261121-0010', $this->repo->nomorNotaTerakhir('SEW-20261121-'));
        $this->assertSame('SEW-20261122-0001', $this->repo->nomorNotaTerakhir('SEW-20261122-'));
        $this->assertNull($this->repo->nomorNotaTerakhir('SEW-20261123-'));
    }

    public function test_nomor_perpanjang_terakhir(): void
    {
        $sewa = Rental::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach (['PJG-20261121-0001', 'PJG-20261121-0003'] as $nomor) {
            RentalExtension::create([
                'rental_id' => $sewa->id,
                'nomor_perpanjang' => $nomor,
                'tanggal_selesai_lama' => '2026-11-23',
                'tanggal_selesai_baru' => '2026-11-24',
                'tambahan_hari' => 1,
                'biaya_tambahan' => 25000,
                'diproses_oleh' => $admin->id,
            ]);
        }

        $this->assertSame('PJG-20261121-0003', $this->repo->nomorPerpanjangTerakhir('PJG-20261121-'));
        $this->assertNull($this->repo->nomorPerpanjangTerakhir('PJG-20261122-'));
    }

    public function test_aktif_pada_mencakup_batas_inklusif_dan_mengabaikan_selesai(): void
    {
        $mulaiHariIni = Rental::factory()->periode('2026-11-21', '2026-11-23')->create();
        $berakhirHariIni = Rental::factory()->periode('2026-11-19', '2026-11-21')->create();
        Rental::factory()->periode('2026-11-19', '2026-11-20')->create();
        Rental::factory()->periode('2026-11-21', '2026-11-23')->selesai()->create();

        $hasil = $this->repo->aktifPada($this->tanggal('2026-11-21'));

        $this->assertEqualsCanonicalizing(
            [$mulaiHariIni->id, $berakhirHariIni->id],
            $hasil->pluck('id')->all(),
        );
    }

    /**
     * UT-GS-01 s/d UT-GS-03 dan UT-GS-05 terhadap basis data sungguhan.
     */
    public function test_selesaikan_sewa_lewat(): void
    {
        $lewat = Rental::factory()->periode('2026-11-18', '2026-11-20')->create();
        $berakhirHariIni = Rental::factory()->periode('2026-11-19', '2026-11-21')->create();
        $masihJalan = Rental::factory()->periode('2026-11-21', '2026-11-25')->create();

        $hasil = $this->repo->selesaikanSewaLewat($this->tanggal('2026-11-21'));

        $this->assertSame([$lewat->id], $hasil->pluck('id')->all());
        $this->assertSame(RentalStatus::SELESAI, $hasil->first()->status);
        $this->assertSame(RentalStatus::SELESAI, $lewat->fresh()->status);
        $this->assertSame(RentalStatus::AKTIF, $berakhirHariIni->fresh()->status);
        $this->assertSame(RentalStatus::AKTIF, $masihJalan->fresh()->status);

        // Idempoten: pemanggilan kedua tidak menemukan apa pun.
        $this->assertCount(0, $this->repo->selesaikanSewaLewat($this->tanggal('2026-11-21')));
    }

    public function test_kendaraan_sedang_disewa_bila_ada_sewa_aktif_beririsan(): void
    {
        $sewa = Rental::factory()->periode('2026-11-21', '2026-11-23')->create();

        $this->assertTrue($this->repo->kendaraanSedangDisewa($sewa->vehicle_id, $this->tanggal('2026-11-23'), $this->tanggal('2026-11-25')));
        $this->assertFalse($this->repo->kendaraanSedangDisewa($sewa->vehicle_id, $this->tanggal('2026-11-24'), $this->tanggal('2026-11-25')));
    }

    public function test_kendaraan_dengan_sewa_selesai_tidak_dianggap_disewa(): void
    {
        $sewa = Rental::factory()->periode('2026-11-21', '2026-11-23')->selesai()->create();

        $this->assertFalse($this->repo->kendaraanSedangDisewa($sewa->vehicle_id, $this->tanggal('2026-11-21'), $this->tanggal('2026-11-23')));
    }

    public function test_cari_id_dan_simpan(): void
    {
        $sewa = Rental::factory()->create();

        $this->assertSame($sewa->id, $this->repo->cariId($sewa->id)->id);
        $this->assertNull($this->repo->cariId(999999));

        $sewa->tanggal_selesai = '2026-11-30';
        $this->repo->simpan($sewa);

        $this->assertSame('2026-11-30', $sewa->fresh()->tanggal_selesai->toDateString());
    }

    /**
     * Perpanjangan dipilih menurut tanggal diproses (created_at), inklusif di
     * kedua ujung, beserta sewa, garasi, dan penyewanya.
     */
    public function test_perpanjangan_dalam_rentang_menurut_tanggal_diproses(): void
    {
        $sewa = Rental::factory()->periode('2026-10-28', '2026-10-31')->create(['nomor_nota' => 'SEW-20261028-0001']);
        $admin = User::factory()->admin()->create();
        $buat = fn (string $nomor, string $diproses) => RentalExtension::query()->forceCreate([
            'rental_id' => $sewa->id,
            'nomor_perpanjang' => $nomor,
            'tanggal_selesai_lama' => '2026-10-31',
            'tanggal_selesai_baru' => '2026-11-02',
            'tambahan_hari' => 2,
            'biaya_tambahan' => 50000,
            'diproses_oleh' => $admin->id,
            'created_at' => $diproses,
        ]);
        $buat('PJG-20261031-0001', '2026-10-31 23:59:00');
        $awal = $buat('PJG-20261101-0001', '2026-11-01 00:00:00');
        $akhir = $buat('PJG-20261130-0001', '2026-11-30 23:59:00');

        $hasil = $this->repo->perpanjanganDalamRentang($this->tanggal('2026-11-01'), $this->tanggal('2026-11-30'));

        $this->assertEqualsCanonicalizing([$awal->id, $akhir->id], $hasil->pluck('id')->all());
        $this->assertTrue($hasil->first()->relationLoaded('rental'));
        $this->assertTrue($hasil->first()->rental->relationLoaded('garage'));
        $this->assertTrue($hasil->first()->rental->relationLoaded('penyewa'));
    }

    public function test_simpan_perpanjangan_dan_pembayaran(): void
    {
        $sewa = Rental::factory()->create();
        $admin = User::factory()->admin()->create();

        $perpanjangan = $this->repo->simpanPerpanjangan((new RentalExtension())->forceFill([
            'rental_id' => $sewa->id,
            'nomor_perpanjang' => 'PJG-20261121-0001',
            'tanggal_selesai_lama' => '2026-11-23',
            'tanggal_selesai_baru' => '2026-11-25',
            'tambahan_hari' => 2,
            'biaya_tambahan' => 50000,
            'diproses_oleh' => $admin->id,
        ]));

        $pembayaran = $this->repo->simpanPembayaran((new Payment())->forceFill([
            'rental_id' => $sewa->id,
            'rental_extension_id' => $perpanjangan->id,
            'jumlah' => 50000,
            'path_bukti_bayar' => 'bukti-bayar/2026/11/x.jpg',
            'diunggah_oleh' => $admin->id,
            'diunggah_pada' => now(),
        ]));

        $this->assertNotNull($perpanjangan->id);
        $this->assertNotNull($pembayaran->id);
        $this->assertSame($pembayaran->id, $perpanjangan->payment->id);
    }

    public function test_riwayat_penyewa_hanya_miliknya_terbaru_lebih_dulu_dengan_relasi(): void
    {
        $lama = Rental::factory()->periode('2026-10-01', '2026-10-03')->create(['nomor_nota' => 'SEW-20261001-0001']);
        $baru = Rental::factory()->periode('2026-11-21', '2026-11-23')
            ->create(['penyewa_id' => $lama->penyewa_id, 'nomor_nota' => 'SEW-20261121-0001']);
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['nomor_nota' => 'SEW-20261121-0002']);

        $hasil = $this->repo->riwayatPenyewa($lama->penyewa_id);

        $this->assertSame([$baru->id, $lama->id], $hasil->pluck('id')->all());
        foreach (['garage', 'vehicle', 'payments', 'extensions'] as $relasi) {
            $this->assertTrue($hasil->first()->relationLoaded($relasi), "Relasi {$relasi} belum dimuat.");
        }
    }
}
