<?php

namespace Tests\Unit\Services;

use App\Domain\Models\CarGarage;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Repositories\RentalRepository;
use App\Services\ReportService;
use App\Support\ReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private MockInterface&RentalRepository $rentalRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rentalRepo = Mockery::mock(RentalRepository::class);
    }

    private function service(): ReportService
    {
        return new ReportService($this->rentalRepo, new ReportAggregator());
    }

    private function sewa(string $mulai, float $total, string $nota = 'SEW-1'): Rental
    {
        $sewa = new Rental();
        $sewa->setRawAttributes(['nomor_nota' => $nota, 'tanggal_mulai' => $mulai, 'total_biaya' => $total, 'garage_id' => 'C001'], true);
        $sewa->setRelation('penyewa', null);
        $sewa->setRelation('payments', new Collection());

        return $sewa;
    }

    private function perpanjangan(string $diproses, float $biaya): RentalExtension
    {
        $perpanjangan = new RentalExtension();
        $perpanjangan->setRawAttributes([
            'nomor_perpanjang' => 'PJG-1',
            'created_at' => CarbonImmutable::parse($diproses),
            'biaya_tambahan' => $biaya,
        ], true);
        $perpanjangan->setRelation('rental', $this->sewa('2026-01-05', 100000));
        $perpanjangan->setRelation('payment', null);

        return $perpanjangan;
    }

    /**
     * @return \Mockery\Matcher\Closure
     */
    private function tanggal(string $diharapkan)
    {
        return Mockery::on(fn ($t) => $t->toDateString() === $diharapkan);
    }

    public function test_per_bulan_mengambil_sewa_dan_perpanjangan_setahun(): void
    {
        $this->rentalRepo->shouldReceive('rekapPerBulan')->once()->with(2026)
            ->andReturn(new Collection([$this->sewa('2026-01-05', 100000)]));
        $this->rentalRepo->shouldReceive('perpanjanganDalamRentang')->once()
            ->with($this->tanggal('2026-01-01'), $this->tanggal('2026-12-31'))
            ->andReturn(new Collection([$this->perpanjangan('2026-02-01 10:00', 20000)]));

        $hasil = $this->service()->perBulan(2026);

        $this->assertCount(12, $hasil);
        $this->assertSame(100000.0, $hasil[0]['pendapatan']);
        $this->assertSame(20000.0, $hasil[1]['pendapatan']);

        // Rincian ikut di baris bulan yang sama dengan pendapatannya.
        $this->assertSame(['SEW-1'], array_column($hasil[0]['rincian'], 'nomor'));
        $this->assertSame(['PJG-1'], array_column($hasil[1]['rincian'], 'nomor'));
        $this->assertSame([], $hasil[2]['rincian']);
    }

    public function test_rincian_per_hari_tipe_garasi_dan_penyewa_ikut_barisnya(): void
    {
        $dari = CarbonImmutable::parse('2026-11-01');
        $sampai = CarbonImmutable::parse('2026-11-30');

        $budi = new Penyewa();
        $budi->setRawAttributes(['id' => 3, 'nama_lengkap' => 'Budi'], true);
        $sewa = $this->sewa('2026-11-10', 50000);
        $sewa->setRawAttributes(array_merge($sewa->getAttributes(), ['penyewa_id' => 3]), true);
        $sewa->setRelation('penyewa', $budi);
        $sewa->setRelation('garage', new CarGarage());

        $this->rentalRepo->shouldReceive('rekapPerHari')->andReturn(new Collection([$sewa]));
        $this->rentalRepo->shouldReceive('rekapPerTipeGarasi')->andReturn(new Collection([$sewa]));
        $this->rentalRepo->shouldReceive('rekapPerPenyewa')->andReturn(new Collection([$sewa]));
        $this->rentalRepo->shouldReceive('perpanjanganDalamRentang')->andReturn(new Collection());

        $this->assertSame(['SEW-1'], array_column($this->service()->perHari(2026, 11)[9]['rincian'], 'nomor'));
        $this->assertSame(['SEW-1'], array_column($this->service()->perTipeGarasi($dari, $sampai)[1]['rincian'], 'nomor'));
        $this->assertSame([], $this->service()->perTipeGarasi($dari, $sampai)[0]['rincian']);
        $this->assertSame(['SEW-1'], array_column($this->service()->perPenyewa($dari, $sampai)[0]['rincian'], 'nomor'));
    }

    public function test_per_hari_mengambil_sewa_dan_perpanjangan_sebulan(): void
    {
        $this->rentalRepo->shouldReceive('rekapPerHari')->once()->with(2028, 2)->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('perpanjanganDalamRentang')->once()
            ->with($this->tanggal('2028-02-01'), $this->tanggal('2028-02-29'))
            ->andReturn(new Collection());

        $this->assertCount(29, $this->service()->perHari(2028, 2));
    }

    public function test_per_tipe_garasi_dan_per_penyewa_memakai_rentang_tanggal(): void
    {
        $dari = CarbonImmutable::parse('2026-11-01');
        $sampai = CarbonImmutable::parse('2026-11-30');
        $this->rentalRepo->shouldReceive('rekapPerTipeGarasi')->once()->with($dari, $sampai)->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('rekapPerPenyewa')->once()->with($dari, $sampai)->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('perpanjanganDalamRentang')->twice()->with($dari, $sampai)->andReturn(new Collection());

        $this->assertCount(3, $this->service()->perTipeGarasi($dari, $sampai));
        $this->assertCount(0, $this->service()->perPenyewa($dari, $sampai));
    }

    public function test_total_pendapatan_menjumlahkan_baris_laporan(): void
    {
        $total = $this->service()->totalPendapatan(collect([
            ['pendapatan' => 100000.0],
            ['pendapatan' => 0.0],
            ['pendapatan' => 50000.0],
        ]));

        $this->assertSame(150000.0, $total);
    }
}
