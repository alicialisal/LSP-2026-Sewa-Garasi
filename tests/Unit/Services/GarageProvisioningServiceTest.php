<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Models\BikeGarage;
use App\Exceptions\KuotaGarasiHabisException;
use App\Repositories\GarageRepository;
use App\Repositories\RentalRepository;
use App\Services\GarageProvisioningService;
use App\Domain\Models\Rental;
use Carbon\CarbonImmutable;
use App\Support\GarageCodeFormatter;
use App\Support\GarageIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pembuatan garasi berkelompok (asumsi nomor 13 dan 14): kode dibangkitkan
 * dengan MAX + 1 per tipe, kuota 999 per tipe, dan seluruhnya dalam satu transaksi.
 */
class GarageProvisioningServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private MockInterface&GarageRepository $garageRepo;

    private MockInterface&RentalRepository $rentalRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garageRepo = Mockery::mock(GarageRepository::class);
        $this->rentalRepo = Mockery::mock(RentalRepository::class);
    }

    private function service(): GarageProvisioningService
    {
        return new GarageProvisioningService(
            $this->garageRepo,
            $this->rentalRepo,
            new GarageIdGenerator($this->garageRepo, new GarageCodeFormatter()),
        );
    }

    private function transaksiSekali(): void
    {
        DB::partialMock()->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    }

    private function data(GarageType $tipe, int $jumlah, float $harian = 5000, ?float $listrik = null): array
    {
        return ['tipeGarasi' => $tipe, 'jumlah' => $jumlah, 'hargaSewaHarian' => $harian, 'hargaListrikPerKwh' => $listrik];
    }

    /**
     * Tiga garasi sepeda dari repositori kosong: S001, S002, S003, status AVAILABLE.
     */
    public function test_buat_berkelompok_membangkitkan_kode_berurutan(): void
    {
        $this->transaksiSekali();
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::BIKE)->once()->andReturn(0);
        $disimpan = new Collection([new BikeGarage()]);
        $this->garageRepo->shouldReceive('simpanBerkelompok')->once()
            ->with(Mockery::on(function (array $baris) {
                return array_column($baris, 'id') === ['S001', 'S002', 'S003']
                    && collect($baris)->every(fn ($b) => $b['type'] === GarageType::BIKE
                        && $b['harga_sewa_harian'] === 5000.0
                        && $b['harga_listrik_per_kwh'] === null
                        && $b['status'] === GarageStatus::AVAILABLE);
            }))
            ->andReturn($disimpan);

        $hasil = $this->service()->buatBerkelompok($this->data(GarageType::BIKE, 3));

        $this->assertSame($disimpan, $hasil);
    }

    public function test_garasi_listrik_menyimpan_harga_listrik(): void
    {
        $this->transaksiSekali();
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::ELECTRIC)->andReturn(1);
        $this->garageRepo->shouldReceive('simpanBerkelompok')->once()
            ->with(Mockery::on(fn (array $baris) => array_column($baris, 'id') === ['E002', 'E003']
                && collect($baris)->every(fn ($b) => $b['harga_listrik_per_kwh'] === 2500.0)))
            ->andReturn(new Collection());

        $this->service()->buatBerkelompok($this->data(GarageType::ELECTRIC, 2, 30000, 2500));
    }

    /**
     * FT-03 pada tingkat service: garasi listrik tanpa harga listrik ditolak
     * sebelum apa pun dibangkitkan atau disimpan.
     */
    public function test_garasi_listrik_tanpa_harga_listrik_ditolak(): void
    {
        $this->garageRepo->shouldNotReceive('nomorTerakhir');
        $this->garageRepo->shouldNotReceive('simpanBerkelompok');

        try {
            $this->service()->buatBerkelompok($this->data(GarageType::ELECTRIC, 5, 30000, null));
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('harga_listrik_per_kwh', $e->errors());
        }
    }

    /**
     * Harga listrik hanya untuk ELECTRIC; untuk tipe lain disimpan NULL (erd.dbml).
     */
    public function test_harga_listrik_diabaikan_untuk_tipe_non_listrik(): void
    {
        $this->transaksiSekali();
        $this->garageRepo->shouldReceive('nomorTerakhir')->andReturn(0);
        $this->garageRepo->shouldReceive('simpanBerkelompok')->once()
            ->with(Mockery::on(fn (array $baris) => $baris[0]['harga_listrik_per_kwh'] === null))
            ->andReturn(new Collection());

        $this->service()->buatBerkelompok($this->data(GarageType::CAR, 1, 25000, 2500));
    }

    /**
     * FT-04 pada tingkat service: melebihi sisa kuota ditolak dan tidak ada yang disimpan.
     */
    public function test_melebihi_sisa_kuota_ditolak(): void
    {
        DB::partialMock()->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::BIKE)->andReturn(998);
        $this->garageRepo->shouldNotReceive('simpanBerkelompok');

        $this->expectException(KuotaGarasiHabisException::class);

        $this->service()->buatBerkelompok($this->data(GarageType::BIKE, 3));
    }

    public function test_jumlah_nol_ditolak(): void
    {
        DB::partialMock()->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
        $this->garageRepo->shouldReceive('nomorTerakhir')->andReturn(0)->byDefault();
        $this->garageRepo->shouldNotReceive('simpanBerkelompok');

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->buatBerkelompok($this->data(GarageType::BIKE, 0));
    }

    /**
     * Sisa kuota dipakai form untuk membatasi isian (FT-04: S998 menyisakan 1).
     */
    public function test_sisa_kuota_per_tipe(): void
    {
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::BIKE)->andReturn(998);
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::CAR)->andReturn(0);

        $this->assertSame(1, $this->service()->sisaKuota(GarageType::BIKE));
        $this->assertSame(999, $this->service()->sisaKuota(GarageType::CAR));
    }

    /**
     * Daftar garasi: status disinkronkan lebih dulu (asumsi nomor 25), lalu
     * garasi diambil sesuai filter tipe.
     */
    public function test_daftar_garasi_menyinkronkan_status_lebih_dulu(): void
    {
        $daftar = new Collection([new BikeGarage()]);
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()->ordered()->andReturn(new Collection());
        $this->garageRepo->shouldReceive('semua')->once()->ordered()->with(GarageType::BIKE)->andReturn($daftar);

        $hasil = $this->service()->daftarGarasi(GarageType::BIKE, \Carbon\CarbonImmutable::parse('2026-11-21'));

        $this->assertSame($daftar, $hasil);
    }

    public function test_daftar_garasi_tanpa_filter(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('semua')->once()->with(null)->andReturn(new Collection());

        $this->assertCount(0, $this->service()->daftarGarasi(null, \Carbon\CarbonImmutable::parse('2026-11-21')));
    }

    /**
     * Ringkasan per tipe: total, terpakai, kosong, dan sisa kuota; tipe tanpa garasi tetap tampil.
     */
    public function test_ringkasan_per_tipe(): void
    {
        $this->garageRepo->shouldReceive('jumlahPerTipeDanStatus')->once()->andReturn([
            'CAR' => ['AVAILABLE' => 3, 'USED' => 2],
            'ELECTRIC' => ['USED' => 1],
        ]);
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::BIKE)->andReturn(0);
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::CAR)->andReturn(5);
        $this->garageRepo->shouldReceive('nomorTerakhir')->with(GarageType::ELECTRIC)->andReturn(1);

        $this->assertSame([
            ['tipe' => GarageType::BIKE, 'total' => 0, 'terpakai' => 0, 'kosong' => 0, 'sisa_kuota' => 999],
            ['tipe' => GarageType::CAR, 'total' => 5, 'terpakai' => 2, 'kosong' => 3, 'sisa_kuota' => 994],
            ['tipe' => GarageType::ELECTRIC, 'total' => 1, 'terpakai' => 1, 'kosong' => 0, 'sisa_kuota' => 998],
        ], $this->service()->ringkasan());
    }

    // ---------- Sinkronisasi status (G.3.14, asumsi nomor 25) ----------
    // Tanggal dibekukan pada 21 Nov 2026. Hari terakhir sewa inklusif, sehingga
    // "sudah lewat" berarti tanggal selesai < hari ini. Penentuan sewa mana yang
    // sudah lewat dilakukan repository (diuji di Feature test); di sini diuji
    // bagaimana hasilnya diterjemahkan menjadi status garasi.

    private function sewaDiGarasi(string $garageId): Rental
    {
        return (new Rental())->forceFill(['garage_id' => $garageId]);
    }

    private function sinkronkan(): int
    {
        return $this->service()->sinkronkanStatus(CarbonImmutable::parse('2026-11-21'));
    }

    /**
     * UT-GS-01 - Sewa yang sudah lewat selesai dan garasinya AVAILABLE.
     */
    public function test_sinkron_sewa_lewat_diselesaikan_dan_garasi_available(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')
            ->with(Mockery::on(fn ($tanggal) => $tanggal->toDateString() === '2026-11-21'))->once()
            ->andReturn(new Collection([$this->sewaDiGarasi('C001')]));
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C001', GarageStatus::AVAILABLE);

        $this->assertSame(1, $this->sinkronkan());
    }

    /**
     * UT-GS-02, UT-GS-03, dan UT-GS-06 - Tidak ada sewa yang lewat (termasuk bila
     * tidak ada sewa sama sekali): tidak ada perubahan, hasil 0.
     */
    public function test_sinkron_tanpa_sewa_lewat_tidak_ada_perubahan(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection());
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->assertSame(0, $this->sinkronkan());
    }

    /**
     * UT-GS-04 - Dua sewa lewat dan satu masih berjalan: dua garasi AVAILABLE.
     */
    public function test_sinkron_dua_garasi_kembali_available_satu_tetap_used(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()
            ->andReturn(new Collection([$this->sewaDiGarasi('C001'), $this->sewaDiGarasi('C002')]));
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection([$this->sewaDiGarasi('C003')]));
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C001', GarageStatus::AVAILABLE);
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C002', GarageStatus::AVAILABLE);
        $this->garageRepo->shouldNotReceive('ubahStatus')->with('C003', Mockery::any());

        $this->assertSame(2, $this->sinkronkan());
    }

    /**
     * UT-GS-05 - Dipanggil dua kali: pemanggilan kedua mengembalikan 0.
     */
    public function test_sinkron_idempoten(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->twice()
            ->andReturn(new Collection([$this->sewaDiGarasi('C001')]), new Collection());
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C001', GarageStatus::AVAILABLE);

        $this->assertSame(1, $this->sinkronkan());
        $this->assertSame(0, $this->sinkronkan());
    }

    /**
     * UT-GS-07 - Garasi dengan sewa lama yang lewat dan sewa baru yang aktif tetap USED.
     */
    public function test_sinkron_garasi_dengan_sewa_aktif_lain_tetap_used(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()
            ->andReturn(new Collection([$this->sewaDiGarasi('C001')]));
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection([$this->sewaDiGarasi('C001')]));
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->assertSame(1, $this->sinkronkan());
    }

    /**
     * Dua sewa lewat di garasi yang sama: status diubah sekali.
     */
    public function test_sinkron_garasi_yang_sama_hanya_diubah_sekali(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()
            ->andReturn(new Collection([$this->sewaDiGarasi('C001'), $this->sewaDiGarasi('C001')]));
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C001', GarageStatus::AVAILABLE);

        $this->assertSame(2, $this->sinkronkan());
    }
}
