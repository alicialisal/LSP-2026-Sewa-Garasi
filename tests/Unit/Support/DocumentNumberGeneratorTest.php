<?php

namespace Tests\Unit\Support;

use App\Repositories\RentalRepository;
use App\Support\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use LogicException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Format mengikuti asumsi nomor 22: SEW-yyyymmdd-NNNN dan PJG-yyyymmdd-NNNN.
 */
class DocumentNumberGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function repo(): MockInterface&RentalRepository
    {
        return Mockery::mock(RentalRepository::class);
    }

    /**
     * UT-DN-01 - Belum ada nota hari itu.
     */
    public function test_nomor_nota_pertama_hari_itu(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorNotaTerakhir')->with('SEW-20261121-')->once()->andReturn(null);

        $hasil = (new DocumentNumberGenerator($repo))->nomorNota(CarbonImmutable::parse('2026-11-21'));

        $this->assertSame('SEW-20261121-0001', $hasil);
    }

    /**
     * UT-DN-02 - Nomor urut bertambah satu.
     */
    public function test_nomor_nota_berikutnya(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorNotaTerakhir')->with('SEW-20261121-')->once()->andReturn('SEW-20261121-0007');

        $hasil = (new DocumentNumberGenerator($repo))->nomorNota(CarbonImmutable::parse('2026-11-21'));

        $this->assertSame('SEW-20261121-0008', $hasil);
    }

    /**
     * UT-DN-03 - Hari berganti, urutan kembali ke 1.
     */
    public function test_nomor_nota_urut_kembali_ke_satu_saat_hari_berganti(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorNotaTerakhir')->with('SEW-20261122-')->once()->andReturn(null);

        $hasil = (new DocumentNumberGenerator($repo))->nomorNota(CarbonImmutable::parse('2026-11-22'));

        $this->assertSame('SEW-20261122-0001', $hasil);
    }

    /**
     * UT-DN-04 - Panjang hasil tidak melebihi batas kolom (30 karakter).
     */
    public function test_panjang_nomor_nota_tidak_melebihi_batas_kolom(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorNotaTerakhir')->andReturn('SEW-20261121-9998');

        $hasil = (new DocumentNumberGenerator($repo))->nomorNota(CarbonImmutable::parse('2026-11-21'));

        $this->assertLessThanOrEqual(30, strlen($hasil));
    }

    /**
     * Urutan 4 digit habis pada nomor 9999; tidak boleh melebar diam-diam.
     */
    public function test_nomor_urut_habis_ditolak(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorNotaTerakhir')->andReturn('SEW-20261121-9999');

        $this->expectException(LogicException::class);

        (new DocumentNumberGenerator($repo))->nomorNota(CarbonImmutable::parse('2026-11-21'));
    }

    /**
     * UT-DN-05 - Belum ada perpanjangan hari itu.
     */
    public function test_nomor_perpanjang_pertama_hari_itu(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorPerpanjangTerakhir')->with('PJG-20261121-')->once()->andReturn(null);

        $hasil = (new DocumentNumberGenerator($repo))->nomorPerpanjang(CarbonImmutable::parse('2026-11-21'));

        $this->assertSame('PJG-20261121-0001', $hasil);
    }

    /**
     * UT-DN-06 - Terakhir PJG-20261121-0003 menjadi 0004.
     */
    public function test_nomor_perpanjang_berikutnya(): void
    {
        $repo = $this->repo();
        $repo->shouldReceive('nomorPerpanjangTerakhir')->with('PJG-20261121-')->once()->andReturn('PJG-20261121-0003');

        $hasil = (new DocumentNumberGenerator($repo))->nomorPerpanjang(CarbonImmutable::parse('2026-11-21'));

        $this->assertSame('PJG-20261121-0004', $hasil);
    }
}
