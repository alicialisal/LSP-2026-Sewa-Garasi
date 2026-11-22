<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Pricing\PricingContext;
use App\Exceptions\JumlahHariTidakValidException;
use App\Repositories\GarageRepository;
use App\Repositories\RentalRepository;
use App\Services\ExtensionService;
use App\Services\GarageProvisioningService;
use App\Support\GarageCodeFormatter;
use App\Support\GarageIdGenerator;
use App\Support\DateCalculator;
use App\Support\DocumentNumberGenerator;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Waktu dibekukan pada 21 Nov 2026. Perpanjangan dihitung dengan tarif harian
 * dan tidak dikonversi menjadi bulanan (asumsi nomor 17 dan 29).
 */
class ExtensionServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PATH_BUKTI = 'bukti-bayar/2026/11/perpanjang.jpg';

    private const ID_ADMIN = 2;

    private MockInterface&RentalRepository $rentalRepo;

    private MockInterface&GarageRepository $garageRepo;

    private MockInterface&PaymentProofStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');

        DB::partialMock()->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->rentalRepo = Mockery::mock(RentalRepository::class);
        $this->garageRepo = Mockery::mock(GarageRepository::class);
        $this->storage = Mockery::mock(PaymentProofStorage::class);

        $this->rentalRepo->shouldReceive('nomorPerpanjangTerakhir')->andReturn(null)->byDefault();
        $this->rentalRepo->shouldReceive('simpanPerpanjangan')->andReturnUsing(function (RentalExtension $perpanjangan) {
            $perpanjangan->id = 50;

            return $perpanjangan;
        })->byDefault();
        $this->rentalRepo->shouldReceive('simpanPembayaran')->andReturnArg(0)->byDefault();
        $this->rentalRepo->shouldReceive('simpan')->andReturnArg(0)->byDefault();

        $this->storage->shouldReceive('simpan')->andReturn(self::PATH_BUKTI)->byDefault();
        $this->storage->shouldReceive('hapus')->byDefault();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function buatService(): ExtensionService
    {
        return new ExtensionService(
            $this->rentalRepo,
            new PricingContext(),
            new DateCalculator(),
            new DocumentNumberGenerator($this->rentalRepo),
            new GarageProvisioningService($this->garageRepo, $this->rentalRepo, new GarageIdGenerator($this->garageRepo, new GarageCodeFormatter())),
            $this->storage,
        );
    }

    private function sewa(
        string $mulai = '2026-11-21',
        string $selesai = '2026-11-23',
        RentalStatus $status = RentalStatus::AKTIF,
        RentalType $tipe = RentalType::HARIAN,
        float $harga = 20000,
    ): Rental {
        $sewa = new Rental();
        $sewa->setRawAttributes([
            'id' => 5,
            'garage_id' => 'C001',
            'tipe_sewa' => $tipe->value,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'status' => $status->value,
        ], true);
        $sewa->setRelation('garage', (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => $harga]));

        return $sewa;
    }

    private function tanggal(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal);
    }

    private function bukti(): UploadedFile
    {
        return UploadedFile::fake()->image('perpanjang.jpg');
    }

    private function temukan(Rental $sewa): void
    {
        $this->rentalRepo->shouldReceive('cariId')->with(5)->andReturn($sewa);
    }

    /**
     * UT-ES-01 - Harga Rp20.000, selesai lama 23 Nov, baru 26 Nov: Rp60.000.
     */
    public function test_hitung_sisa_biaya_tiga_hari(): void
    {
        $biaya = $this->buatService()->hitungSisaBiaya($this->sewa(), $this->tanggal('2026-11-26'));

        $this->assertSame(60000.0, $biaya);
    }

    /**
     * UT-ES-02 - Tanggal baru sama dengan tanggal lama ditolak.
     */
    public function test_tanggal_baru_sama_dengan_lama_ditolak(): void
    {
        $this->expectException(JumlahHariTidakValidException::class);

        $this->buatService()->hitungSisaBiaya($this->sewa(), $this->tanggal('2026-11-23'));
    }

    /**
     * UT-ES-03 - Tanggal baru sebelum tanggal lama ditolak.
     */
    public function test_tanggal_baru_sebelum_lama_ditolak(): void
    {
        $this->expectException(JumlahHariTidakValidException::class);

        $this->buatService()->hitungSisaBiaya($this->sewa(), $this->tanggal('2026-11-22'));
    }

    /**
     * UT-ES-04 - Sewa bulanan diperpanjang 2 hari dihitung harian: Rp40.000.
     */
    public function test_sewa_bulanan_diperpanjang_dihitung_harian(): void
    {
        $sewa = $this->sewa('2026-11-21', '2026-12-20', tipe: RentalType::BULANAN);

        $biaya = $this->buatService()->hitungSisaBiaya($sewa, $this->tanggal('2026-12-22'));

        $this->assertSame(40000.0, $biaya);
    }

    /**
     * UT-ES-05 - Data valid: perpanjangan, pembayaran, dan tanggal selesai baru tersimpan.
     */
    public function test_perpanjang_menyimpan_perpanjangan_pembayaran_dan_tanggal_baru(): void
    {
        $sewa = $this->sewa();
        $this->temukan($sewa);
        $dibayar = null;
        $disimpan = null;
        $this->rentalRepo->shouldReceive('simpanPembayaran')->once()->andReturnUsing(function (Payment $pembayaran) use (&$dibayar) {
            $dibayar = $pembayaran;

            return $pembayaran;
        });
        $this->rentalRepo->shouldReceive('simpan')->once()->andReturnUsing(function (Rental $s) use (&$disimpan) {
            $disimpan = $s;

            return $s;
        });

        $perpanjangan = $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);

        $this->assertSame('PJG-20261121-0001', $perpanjangan->nomor_perpanjang);
        $this->assertSame('2026-11-23', $perpanjangan->tanggal_selesai_lama->toDateString());
        $this->assertSame('2026-11-26', $perpanjangan->tanggal_selesai_baru->toDateString());
        $this->assertSame(3, $perpanjangan->tambahan_hari);
        $this->assertSame(60000.0, $perpanjangan->biaya_tambahan);
        $this->assertSame(self::ID_ADMIN, $perpanjangan->diproses_oleh);

        $this->assertSame(50, $dibayar->rental_extension_id);
        $this->assertSame(5, $dibayar->rental_id);
        $this->assertSame(60000.0, $dibayar->jumlah);
        $this->assertSame(self::PATH_BUKTI, $dibayar->path_bukti_bayar);

        $this->assertSame('2026-11-26', $disimpan->tanggal_selesai->toDateString());
    }

    /**
     * UT-ES-06 - Daftar sewa aktif memakai tanggal hari ini (21 Nov 2026).
     */
    public function test_sewa_aktif_hari_ini_memakai_tanggal_hari_ini(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('aktifPada')->once()
            ->with(Mockery::on(fn ($tanggal) => $tanggal->toDateString() === '2026-11-21'))
            ->andReturn(new Collection([$this->sewa()]));

        $hasil = $this->buatService()->sewaAktifHariIni();

        $this->assertCount(1, $hasil);
    }

    /**
     * UT-ES-07 - Perpanjangan yang tercatat atas sewa lain: DomainException, tidak ada yang disimpan.
     */
    public function test_perpanjangan_milik_sewa_lain_ditolak(): void
    {
        $this->temukan($this->sewa());
        $this->rentalRepo->shouldReceive('simpanPerpanjangan')->andReturnUsing(function (RentalExtension $perpanjangan) {
            $perpanjangan->id = 50;
            $perpanjangan->rental_id = 999;

            return $perpanjangan;
        });
        $this->rentalRepo->shouldNotReceive('simpanPembayaran');
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->storage->shouldReceive('hapus')->once()->with(self::PATH_BUKTI);

        $this->expectException(DomainException::class);

        $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);
    }

    /**
     * UT-ES-08 - Dua perpanjangan berurutan mendapat nomor berbeda dan berurutan.
     */
    public function test_dua_perpanjangan_bernomor_berbeda(): void
    {
        $this->temukan($this->sewa());
        $this->rentalRepo->shouldReceive('nomorPerpanjangTerakhir')->with('PJG-20261121-')
            ->andReturn(null, 'PJG-20261121-0001');

        $service = $this->buatService();
        $pertama = $service->perpanjang(5, $this->tanggal('2026-11-24'), $this->bukti(), self::ID_ADMIN);
        $kedua = $service->perpanjang(5, $this->tanggal('2026-11-25'), $this->bukti(), self::ID_ADMIN);

        $this->assertSame('PJG-20261121-0001', $pertama->nomor_perpanjang);
        $this->assertSame('PJG-20261121-0002', $kedua->nomor_perpanjang);
    }

    /**
     * UT-ES-09 - Sewa berstatus SELESAI tidak dapat diperpanjang.
     */
    public function test_sewa_selesai_tidak_dapat_diperpanjang(): void
    {
        $this->temukan($this->sewa(status: RentalStatus::SELESAI));
        $this->rentalRepo->shouldNotReceive('simpanPerpanjangan');
        $this->storage->shouldNotReceive('simpan');

        $this->expectException(DomainException::class);

        $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);
    }

    /**
     * UT-ES-10 - Sewa AKTIF yang tanggal selesainya kemarin (belum disinkronkan) ditolak.
     */
    public function test_sewa_aktif_yang_sudah_lewat_tidak_dapat_diperpanjang(): void
    {
        $this->temukan($this->sewa('2026-11-18', '2026-11-20'));
        $this->rentalRepo->shouldNotReceive('simpanPerpanjangan');

        $this->expectException(DomainException::class);

        $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);
    }

    /**
     * UT-ES-11 - Synchronizer dipanggil lebih dulu; sewa yang berakhir kemarin tidak ikut daftar.
     */
    public function test_sinkronisasi_dijalankan_sebelum_daftar_diambil(): void
    {
        $kemarin = $this->sewa('2026-11-18', '2026-11-20');
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()->ordered()
            ->andReturn(new Collection([$kemarin]));
        $this->rentalRepo->shouldReceive('aktifPada')->twice()->ordered()->andReturn(new Collection());
        $this->garageRepo->shouldReceive('ubahStatus')->once();

        $hasil = $this->buatService()->sewaAktifHariIni();

        $this->assertCount(0, $hasil);
    }

    /**
     * Kegagalan penyimpanan setelah berkas terunggah: berkas dihapus kembali.
     */
    public function test_berkas_dihapus_bila_penyimpanan_gagal(): void
    {
        $this->temukan($this->sewa());
        $this->rentalRepo->shouldReceive('simpanPerpanjangan')->andThrow(new RuntimeException('simpan gagal'));
        $this->storage->shouldReceive('hapus')->once()->with(self::PATH_BUKTI);

        $this->expectException(RuntimeException::class);

        $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);
    }

    public function test_sewa_tidak_ditemukan_ditolak(): void
    {
        $this->rentalRepo->shouldReceive('cariId')->with(5)->andReturn(null);

        $this->expectException(DomainException::class);

        $this->buatService()->perpanjang(5, $this->tanggal('2026-11-26'), $this->bukti(), self::ID_ADMIN);
    }
}
