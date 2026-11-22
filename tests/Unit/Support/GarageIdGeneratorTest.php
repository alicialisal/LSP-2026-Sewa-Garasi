<?php

namespace Tests\Unit\Support;

use App\Domain\Enums\GarageType;
use App\Exceptions\KuotaGarasiHabisException;
use App\Repositories\GarageRepository;
use App\Support\GarageCodeFormatter;
use App\Support\GarageIdGenerator;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GarageIdGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function generator(GarageType $tipe, int $terakhir, int $kali = 1): GarageIdGenerator
    {
        $repo = Mockery::mock(GarageRepository::class);
        $repo->shouldReceive('nomorTerakhir')->with($tipe)->times($kali)->andReturn($terakhir);

        return new GarageIdGenerator($repo, new GarageCodeFormatter());
    }

    /**
     * UT-GI-01 - Repositori kosong, tiga garasi baru.
     */
    public function test_berikutnya_dari_kosong(): void
    {
        $hasil = $this->generator(GarageType::BIKE, 0)->berikutnya(GarageType::BIKE, 3);

        $this->assertSame(['S001', 'S002', 'S003'], $hasil);
    }

    /**
     * UT-GI-02 - Tepat mengisi sisa kuota.
     */
    public function test_berikutnya_mengisi_sisa_kuota(): void
    {
        $hasil = $this->generator(GarageType::BIKE, 997)->berikutnya(GarageType::BIKE, 2);

        $this->assertSame(['S998', 'S999'], $hasil);
    }

    /**
     * UT-GI-03 - Melebihi sisa kuota ditolak.
     */
    public function test_berikutnya_menolak_melebihi_kuota(): void
    {
        $this->expectException(KuotaGarasiHabisException::class);

        $this->generator(GarageType::BIKE, 998)->berikutnya(GarageType::BIKE, 2);
    }

    /**
     * UT-GI-04 - Kuota dihitung per tipe, bukan total.
     */
    public function test_kuota_dihitung_per_tipe(): void
    {
        $hasil = $this->generator(GarageType::CAR, 0)->berikutnya(GarageType::CAR, 1);

        $this->assertSame(['C001'], $hasil);
    }

    /**
     * UT-GI-05 - Jumlah 0 ditolak sebelum repositori dipanggil.
     */
    public function test_berikutnya_menolak_jumlah_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->generator(GarageType::BIKE, 0, 0)->berikutnya(GarageType::BIKE, 0);
    }

    /**
     * UT-GI-06 - Repositori dipanggil dengan tipe yang diminta.
     */
    public function test_repositori_dipanggil_dengan_tipe_yang_diminta(): void
    {
        $hasil = $this->generator(GarageType::ELECTRIC, 0)->berikutnya(GarageType::ELECTRIC, 1);

        $this->assertSame(['E001'], $hasil);
    }
}
