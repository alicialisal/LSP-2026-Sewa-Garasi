<?php

namespace Tests\Unit\Services;

use App\Exceptions\NomorPlatDipakaiPenyewaLainException;
use App\Domain\Models\BikeGarage;
use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Enums\RegistrationStatus;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\RentalTypeResolver;
use App\Exceptions\BuktiBayarTidakValidException;
use App\Exceptions\GarasiTidakTersediaException;
use App\Exceptions\KendaraanSedangDisewaException;
use App\Exceptions\KendaraanTidakCocokException;
use App\Exceptions\PenyewaBelumTerverifikasiException;
use App\Repositories\GarageRepository;
use App\Repositories\PenyewaRepository;
use App\Repositories\RentalRepository;
use App\Repositories\VehicleRepository;
use App\Services\GarageProvisioningService;
use App\Services\RentalService;
use App\Support\GarageCodeFormatter;
use App\Support\GarageIdGenerator;
use App\Support\NomorPlatFormatter;
use App\Support\DateCalculator;
use App\Support\DocumentNumberGenerator;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Waktu dibekukan pada 21 Nov 2026 (hari sebulan = 30). Repository dan storage
 * memakai mock; transaksi basis data di-mock agar tidak membuka koneksi.
 */
class RentalServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PATH_BUKTI = 'bukti-bayar/2026/11/bukti.jpg';

    private MockInterface&RentalRepository $rentalRepo;

    private MockInterface&GarageRepository $garageRepo;

    private MockInterface&PenyewaRepository $penyewaRepo;

    private MockInterface&VehicleRepository $vehicleRepo;

    private MockInterface&PaymentProofStorage $storage;

    private int $idKendaraan = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');

        DB::partialMock()->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->rentalRepo = Mockery::mock(RentalRepository::class);
        $this->garageRepo = Mockery::mock(GarageRepository::class);
        $this->penyewaRepo = Mockery::mock(PenyewaRepository::class);
        $this->vehicleRepo = Mockery::mock(VehicleRepository::class);
        $this->storage = Mockery::mock(PaymentProofStorage::class);

        $this->penyewaRepo->shouldReceive('cariId')->with(1)
            ->andReturn($this->penyewa(RegistrationStatus::APPROVED))->byDefault();

        $this->vehicleRepo->shouldReceive('cariNomorPlat')->andReturn(null)->byDefault();
        $this->vehicleRepo->shouldReceive('simpan')->andReturnUsing(function (Vehicle $kendaraan) {
            if ($kendaraan->id === null) {
                $kendaraan->id = 7 + $this->idKendaraan++;
            }

            return $kendaraan;
        })->byDefault();

        $this->rentalRepo->shouldReceive('kendaraanSedangDisewa')->andReturn(false)->byDefault();
        $this->rentalRepo->shouldReceive('nomorNotaTerakhir')->andReturn(null)->byDefault();
        $this->rentalRepo->shouldReceive('simpan')->andReturnUsing(function (Rental $sewa) {
            $sewa->id = 100;

            return $sewa;
        })->byDefault();
        $this->rentalRepo->shouldReceive('simpanPembayaran')->andReturnArg(0)->byDefault();

        $this->garageRepo->shouldReceive('ubahStatus')->byDefault();

        $this->storage->shouldReceive('simpan')->andReturn(self::PATH_BUKTI)->byDefault();
        $this->storage->shouldReceive('hapus')->byDefault();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function buatService(): RentalService
    {
        return new RentalService(
            $this->rentalRepo,
            $this->garageRepo,
            $this->penyewaRepo,
            new PricingContext(),
            new RentalTypeResolver(new DateCalculator()),
            new DocumentNumberGenerator($this->rentalRepo),
            $this->vehicleRepo,
            new GarageProvisioningService($this->garageRepo, $this->rentalRepo, new GarageIdGenerator($this->garageRepo, new GarageCodeFormatter())),
            $this->storage,
        );
    }

    private function penyewa(RegistrationStatus $status): Penyewa
    {
        $penyewa = (new Penyewa())->forceFill(['user_id' => 10, 'status' => $status]);
        $penyewa->id = 1;

        return $penyewa;
    }

    /**
     * @param  array<int, array{string, string, RentalStatus}>  $sewaAda
     */
    private function garasiMobil(string $id = 'C001', float $harga = 25000, array $sewaAda = []): CarGarage
    {
        $garasi = (new CarGarage())->forceFill(['id' => $id, 'harga_sewa_harian' => $harga]);
        $this->isiSewa($garasi, $sewaAda);

        return $garasi;
    }

    /**
     * @param  array<int, array{string, string, RentalStatus}>  $sewaAda
     */
    private function isiSewa(Garage $garasi, array $sewaAda): void
    {
        $daftar = array_map(function (array $baris) {
            $sewa = new Rental();
            $sewa->setRawAttributes([
                'tanggal_mulai' => $baris[0],
                'tanggal_selesai' => $baris[1],
                'status' => $baris[2]->value,
            ], true);

            return $sewa;
        }, $sewaAda);

        $garasi->setRelation('rentals', new Collection($daftar));
    }

    private function garasiListrik(): ElectricCarGarage
    {
        $garasi = (new ElectricCarGarage())->forceFill([
            'id' => 'E001',
            'harga_sewa_harian' => 30000,
            'harga_listrik_per_kwh' => 2500,
        ]);
        $garasi->setRelation('rentals', new Collection());

        return $garasi;
    }

    private function garasiDikunci(Garage $garasi): void
    {
        $this->garageRepo->shouldReceive('cariId')->with($garasi->id, true)->andReturn($garasi);
    }

    private function dataSewa(
        VehicleType $kendaraan = VehicleType::MOBIL,
        ?string $plat = 'DD 1234 AB',
        ?float $kapasitas = null,
        ?float $sisa = null,
        RentalType $tipeSewa = RentalType::HARIAN,
        int $jumlahHari = 3,
        string $garageId = 'C001',
    ): array {
        return [
            'penyewaId' => 1,
            'tipeKendaraan' => $kendaraan,
            'nomorPlat' => $plat,
            'merek' => 'Toyota',
            'tipe' => 'Avanza',
            'kapasitasBateraiKwh' => $kapasitas,
            'sisaBateraiKwh' => $sisa,
            'tipeSewa' => $tipeSewa,
            'jumlahHari' => $jumlahHari,
            'garageId' => $garageId,
        ];
    }

    private function bukti(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg');
    }

    /**
     * UT-RS-01 - Penyewa PENDING tidak dapat menyewa; tidak ada yang disimpan.
     */
    public function test_penyewa_belum_terverifikasi_tidak_dapat_menyewa(): void
    {
        $this->penyewaRepo->shouldReceive('cariId')->with(1)
            ->andReturn($this->penyewa(RegistrationStatus::PENDING));
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->storage->shouldNotReceive('simpan');

        $this->expectException(PenyewaBelumTerverifikasiException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-RS-02 - Sepeda motor ke garasi mobil ditolak.
     */
    public function test_sepeda_motor_ke_garasi_mobil_ditolak(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $this->rentalRepo->shouldNotReceive('simpan');

        $this->expectException(KendaraanTidakCocokException::class);

        $this->buatService()->buatSewa($this->dataSewa(VehicleType::SEPEDA_MOTOR), $this->bukti());
    }

    /**
     * UT-RS-03 - Garasi yang sedang disewa aktif 20 - 25 Nov ditolak.
     */
    public function test_garasi_sedang_disewa_ditolak(): void
    {
        $this->garasiDikunci($this->garasiMobil(sewaAda: [['2026-11-20', '2026-11-25', RentalStatus::AKTIF]]));
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->expectException(GarasiTidakTersediaException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-RS-04 - Mobil bensin harian 3 hari di C001 (Rp25.000): total Rp75.000.
     */
    public function test_buat_sewa_harian_mobil_bensin(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $this->storage->shouldReceive('simpan')->once()->andReturn(self::PATH_BUKTI);
        $this->rentalRepo->shouldReceive('simpan')->once()->andReturnUsing(function (Rental $sewa) {
            $sewa->id = 100;

            return $sewa;
        });
        $this->garageRepo->shouldReceive('ubahStatus')->once()->with('C001', GarageStatus::USED);

        $sewa = $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());

        $this->assertSame(75000.0, $sewa->total_biaya);
        $this->assertSame(RentalType::HARIAN, $sewa->tipe_sewa);
        $this->assertSame(3, $sewa->jumlah_hari);
        $this->assertSame('2026-11-21', $sewa->tanggal_mulai->toDateString());
        $this->assertSame('2026-11-23', $sewa->tanggal_selesai->toDateString());
        $this->assertSame(RentalStatus::AKTIF, $sewa->status);
    }

    /**
     * UT-RS-05 - Harian 30 hari tersimpan sebagai BULANAN, jumlah_hari 0, total Rp625.000.
     */
    public function test_harian_30_hari_disimpan_sebagai_bulanan(): void
    {
        $this->garasiDikunci($this->garasiMobil());

        $sewa = $this->buatService()->buatSewa($this->dataSewa(jumlahHari: 30), $this->bukti());

        $this->assertSame(RentalType::BULANAN, $sewa->tipe_sewa);
        $this->assertSame(0, $sewa->jumlah_hari);
        $this->assertSame('2026-12-20', $sewa->tanggal_selesai->toDateString());
        $this->assertSame(625000.0, $sewa->total_biaya);
    }

    /**
     * UT-RS-06 - Mobil listrik: repository diminta tipe CAR dan ELECTRIC.
     */
    public function test_garasi_tersedia_untuk_mobil_listrik(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('tersediaPada')->once()
            ->with(GarageType::CAR, Mockery::any(), Mockery::any())
            ->andReturn(new Collection([$this->garasiMobil('C001')]));
        $this->garageRepo->shouldReceive('tersediaPada')->once()
            ->with(GarageType::ELECTRIC, Mockery::any(), Mockery::any())
            ->andReturn(new Collection([$this->garasiListrik()]));

        $hasil = $this->buatService()->garasiTersedia(VehicleType::MOBIL_LISTRIK, CarbonImmutable::parse('2026-11-21'));

        $this->assertSame(['C001', 'E001'], $hasil->pluck('id')->all());
    }

    /**
     * UT-RS-07 - Sepeda motor: repository diminta tipe BIKE saja.
     */
    public function test_garasi_tersedia_untuk_sepeda_motor(): void
    {
        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->andReturn(new Collection());
        $this->garageRepo->shouldReceive('tersediaPada')->once()
            ->with(GarageType::BIKE, Mockery::any(), Mockery::any())
            ->andReturn(new Collection());

        $hasil = $this->buatService()->garasiTersedia(VehicleType::SEPEDA_MOTOR, CarbonImmutable::parse('2026-11-21'));

        $this->assertCount(0, $hasil);
    }

    /**
     * UT-RS-08 - Estimasi harian 31 hari: bulanan 30 hari, melebihi batas.
     */
    public function test_hitung_estimasi_harian_31_hari(): void
    {
        $this->garageRepo->shouldReceive('cariId')->with('C001')->andReturn($this->garasiMobil());

        $estimasi = $this->buatService()->hitungEstimasi($this->dataSewa(jumlahHari: 31));

        $this->assertSame(RentalType::BULANAN, $estimasi->periode->tipeSewa);
        $this->assertSame(30, $estimasi->periode->jumlahHari);
        $this->assertSame(0, $estimasi->periode->jumlahHariTersimpan());
        $this->assertTrue($estimasi->periode->melebihiBatas);
        $this->assertSame(625000.0, $estimasi->biaya->biayaSewa);
    }

    /**
     * UT-RS-09 (diubah atas keputusan pengguna) - Mobil listrik 60/20 kWh di E001:
     * jumlah kWh dan biaya listrik tersimpan; harga per kWh diambil dari master
     * data garasi dan tidak disalin ke sewa.
     */
    public function test_mobil_listrik_di_garasi_listrik_menyimpan_kwh_dan_biaya_listrik(): void
    {
        $this->garasiDikunci($this->garasiListrik());

        $sewa = $this->buatService()->buatSewa(
            $this->dataSewa(VehicleType::MOBIL_LISTRIK, 'DD 9999 XX', 60, 20, garageId: 'E001'),
            $this->bukti(),
        );

        $this->assertSame(20.0, $sewa->sisa_baterai_snapshot);
        $this->assertSame(40.0, $sewa->jumlah_listrik_kwh);
        $this->assertSame(100000.0, $sewa->biaya_listrik);
        $this->assertSame(190000.0, $sewa->total_biaya);
        $this->assertArrayNotHasKey('harga_listrik_per_kwh_snapshot', $sewa->getAttributes());
    }

    /**
     * UT-RS-10 (diubah atas keputusan pengguna) - Mobil listrik di garasi mobil
     * biasa: tanpa biaya listrik.
     */
    public function test_mobil_listrik_di_garasi_mobil_tanpa_biaya_listrik(): void
    {
        $this->garasiDikunci($this->garasiMobil());

        $sewa = $this->buatService()->buatSewa(
            $this->dataSewa(VehicleType::MOBIL_LISTRIK, 'DD 9999 XX', 60, 20),
            $this->bukti(),
        );

        $this->assertSame(20.0, $sewa->sisa_baterai_snapshot);
        $this->assertSame(0.0, $sewa->jumlah_listrik_kwh);
        $this->assertSame(0.0, $sewa->biaya_listrik);
        $this->assertArrayNotHasKey('harga_listrik_per_kwh_snapshot', $sewa->getAttributes());
    }

    /**
     * UT-RS-11 - Sisa baterai melebihi kapasitas: ValidationException, tidak ada yang disimpan.
     */
    public function test_sisa_baterai_melebihi_kapasitas_ditolak(): void
    {
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->storage->shouldNotReceive('simpan');

        $this->expectException(ValidationException::class);

        $this->buatService()->buatSewa(
            $this->dataSewa(VehicleType::MOBIL_LISTRIK, 'DD 9999 XX', 60, 70, garageId: 'E001'),
            $this->bukti(),
        );
    }

    /**
     * UT-RS-12 - Dua sewa di hari yang sama mendapat nomor nota berbeda dan berurutan.
     */
    public function test_dua_sewa_sehari_bernomor_nota_berbeda(): void
    {
        $this->garasiDikunci($this->garasiMobil('C001'));
        $this->garasiDikunci($this->garasiMobil('C002'));
        $this->rentalRepo->shouldReceive('nomorNotaTerakhir')->with('SEW-20261121-')
            ->andReturn(null, 'SEW-20261121-0001');

        $service = $this->buatService();
        $pertama = $service->buatSewa($this->dataSewa(plat: 'DD 1111 AA', garageId: 'C001'), $this->bukti());
        $kedua = $service->buatSewa($this->dataSewa(plat: 'DD 2222 BB', garageId: 'C002'), $this->bukti());

        $this->assertSame('SEW-20261121-0001', $pertama->nomor_nota);
        $this->assertSame('SEW-20261121-0002', $kedua->nomor_nota);
    }

    /**
     * UT-RS-13 - Kendaraan yang sedang disewa aktif di garasi lain ditolak.
     */
    public function test_kendaraan_sedang_disewa_ditolak(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $milikSendiri = (new Vehicle())->forceFill(['penyewa_id' => 1, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB']);
        $milikSendiri->id = 9;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->andReturn($milikSendiri);
        $this->rentalRepo->shouldReceive('kendaraanSedangDisewa')->with(9, Mockery::any(), Mockery::any())->andReturn(true);
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->expectException(KendaraanSedangDisewaException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-RS-14 - Kendaraan yang sama dengan sewa lama SELESAI: berhasil, record lama dipakai ulang.
     */
    public function test_kendaraan_lama_dipakai_ulang_setelah_sewa_selesai(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $lama = (new Vehicle())->forceFill(['penyewa_id' => 1, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB']);
        $lama->id = 9;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->andReturn($lama);

        $sewa = $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());

        $this->assertSame(9, $sewa->vehicle_id);
    }

    /**
     * UT-RS-15 - Satu penyewa menyewa dua garasi dengan dua kendaraan berbeda.
     */
    public function test_satu_penyewa_dua_garasi_dua_kendaraan(): void
    {
        $this->garasiDikunci($this->garasiMobil('C001'));
        $this->garasiDikunci($this->garasiMobil('C002'));

        $service = $this->buatService();
        $pertama = $service->buatSewa($this->dataSewa(plat: 'DD 1111 AA', garageId: 'C001'), $this->bukti());
        $kedua = $service->buatSewa($this->dataSewa(plat: 'DD 2222 BB', garageId: 'C002'), $this->bukti());

        $this->assertNotSame($pertama->vehicle_id, $kedua->vehicle_id);
        $this->assertSame('C001', $pertama->garage_id);
        $this->assertSame('C002', $kedua->garage_id);
    }

    /**
     * UT-RS-16 - Penyimpanan sewa gagal setelah berkas terunggah: berkas dihapus, status garasi tetap.
     */
    public function test_berkas_dihapus_bila_penyimpanan_sewa_gagal(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $this->rentalRepo->shouldReceive('simpan')->andThrow(new RuntimeException('simpan gagal'));
        $this->storage->shouldReceive('hapus')->once()->with(self::PATH_BUKTI);
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->expectException(RuntimeException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-RS-17 - Bukti bayar tidak valid: tidak ada sewa tersimpan, status garasi tetap.
     */
    public function test_bukti_bayar_tidak_valid_membatalkan_sewa(): void
    {
        $this->storage->shouldReceive('simpan')->andThrow(new BuktiBayarTidakValidException('bukan gambar'));
        $this->storage->shouldNotReceive('hapus');
        $this->rentalRepo->shouldNotReceive('simpan');
        $this->garageRepo->shouldNotReceive('ubahStatus');

        $this->expectException(BuktiBayarTidakValidException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-RS-18 - Pembayaran: jumlah = total_biaya, tanpa perpanjangan, tanpa status pembayaran.
     */
    public function test_pembayaran_awal_sesuai_total_biaya(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $dibayar = null;
        $this->rentalRepo->shouldReceive('simpanPembayaran')->once()->andReturnUsing(function (Payment $pembayaran) use (&$dibayar) {
            $dibayar = $pembayaran;

            return $pembayaran;
        });

        $sewa = $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());

        $this->assertInstanceOf(Payment::class, $dibayar);
        $this->assertSame($sewa->total_biaya, $dibayar->jumlah);
        $this->assertNull($dibayar->rental_extension_id);
        $this->assertSame(100, $dibayar->rental_id);
        $this->assertSame(self::PATH_BUKTI, $dibayar->path_bukti_bayar);
        $this->assertSame(10, $dibayar->diunggah_oleh);
        $this->assertArrayNotHasKey('status', $dibayar->getAttributes());
    }

    /**
     * Riwayat sewa penyewa: status disinkronkan lebih dulu agar sewa yang
     * sudah lewat tampil Selesai, baru riwayat diambil dari repository.
     */
    public function test_riwayat_menyinkronkan_status_lalu_mengambil_sewa_penyewa(): void
    {
        $urutan = [];
        $riwayat = new Collection([new Rental()]);

        $this->rentalRepo->shouldReceive('selesaikanSewaLewat')->once()
            ->with(Mockery::on(fn ($t) => $t->toDateString() === '2026-11-21'))
            ->andReturnUsing(function () use (&$urutan) {
                $urutan[] = 'sinkron';

                return new Collection();
            });
        $this->rentalRepo->shouldReceive('aktifPada')->andReturn(new Collection());
        $this->rentalRepo->shouldReceive('riwayatPenyewa')->once()->with(1)
            ->andReturnUsing(function () use (&$urutan, $riwayat) {
                $urutan[] = 'riwayat';

                return $riwayat;
            });

        $this->assertSame($riwayat, $this->buatService()->riwayat(1));
        $this->assertSame(['sinkron', 'riwayat'], $urutan);
    }

    /**
     * Pilihan kendaraan di form sewa: kendaraan yang pernah diparkir penyewa,
     * dengan penanda sedang disewa hari ini.
     */
    public function test_kendaraan_tersimpan_diambil_untuk_hari_ini(): void
    {
        $mobil = (new Vehicle())->forceFill(['id' => 5, 'penyewa_id' => 1, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB']);
        $this->vehicleRepo->shouldReceive('pernahDiparkirPenyewa')->once()
            ->with(1, Mockery::on(fn ($t) => $t->toDateString() === '2026-11-21'))
            ->andReturn(new Collection([$mobil]));

        $this->assertSame([5], $this->buatService()->kendaraanTersimpan(1)->pluck('id')->all());
    }

    // ---------- Penentuan kendaraan (G.3.13, dulu VehicleResolver) ----------

    private function garasiSepeda(): BikeGarage
    {
        $garasi = (new BikeGarage())->forceFill(['id' => 'S001', 'harga_sewa_harian' => 5000]);
        $garasi->setRelation('rentals', new Collection());

        return $garasi;
    }

    /**
     * Menangkap kendaraan yang disimpan repository.
     *
     * @param  array<int, Vehicle>  $disimpan
     */
    private function tangkapKendaraan(array &$disimpan): void
    {
        $this->vehicleRepo->shouldReceive('simpan')->andReturnUsing(function (Vehicle $kendaraan) use (&$disimpan) {
            $kendaraan->id ??= 7;
            $disimpan[] = $kendaraan;

            return $kendaraan;
        });
    }

    /**
     * UT-VR-01 - Plat baru: Vehicle baru atas nama penyewa, disimpan sekali.
     */
    public function test_vr_plat_baru_membuat_kendaraan_baru(): void
    {
        $disimpan = [];
        $this->garasiDikunci($this->garasiMobil());
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->once()->andReturn(null);
        $this->tangkapKendaraan($disimpan);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());

        $this->assertCount(1, $disimpan);
        $this->assertSame(1, $disimpan[0]->penyewa_id);
        $this->assertSame('DD 1234 AB', $disimpan[0]->nomor_plat);
        $this->assertSame(VehicleType::MOBIL, $disimpan[0]->type);
        $this->assertSame('Toyota', $disimpan[0]->merek);
    }

    /**
     * UT-VR-02 - Plat milik penyewa yang sama: record lama dipakai ulang dan diperbarui.
     */
    public function test_vr_plat_milik_sendiri_dipakai_ulang_dan_diperbarui(): void
    {
        $disimpan = [];
        $this->garasiDikunci($this->garasiMobil());
        $lama = (new Vehicle())->forceFill(['penyewa_id' => 1, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB', 'merek' => 'Lama', 'tipe' => 'Lama']);
        $lama->id = 5;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->once()->andReturn($lama);
        $this->tangkapKendaraan($disimpan);

        $sewa = $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());

        $this->assertSame(5, $sewa->vehicle_id);
        $this->assertSame($lama, $disimpan[0]);
        $this->assertSame('Toyota', $lama->merek);
        $this->assertSame('Avanza', $lama->tipe);
    }

    /**
     * UT-VR-03 - Plat milik penyewa lain ditolak; kendaraan tidak disimpan.
     */
    public function test_vr_plat_milik_penyewa_lain_ditolak(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $milikLain = (new Vehicle())->forceFill(['penyewa_id' => 99, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB']);
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->once()->andReturn($milikLain);
        $this->vehicleRepo->shouldNotReceive('simpan');
        $this->rentalRepo->shouldNotReceive('simpan');

        $this->expectException(NomorPlatDipakaiPenyewaLainException::class);

        $this->buatService()->buatSewa($this->dataSewa(), $this->bukti());
    }

    /**
     * UT-VR-04 - Sepeda tanpa plat selalu dibuat baru; plat tidak dicari.
     */
    public function test_vr_sepeda_selalu_dibuat_baru(): void
    {
        $disimpan = [];
        $this->garasiDikunci($this->garasiSepeda());
        $this->vehicleRepo->shouldNotReceive('cariNomorPlat');
        $this->tangkapKendaraan($disimpan);

        $this->buatService()->buatSewa($this->dataSewa(VehicleType::SEPEDA, null, garageId: 'S001'), $this->bukti());

        $this->assertNull($disimpan[0]->nomor_plat);
        $this->assertSame(VehicleType::SEPEDA, $disimpan[0]->type);
    }

    /**
     * UT-VR-05 - Sepeda motor tanpa plat ditolak.
     */
    public function test_vr_sepeda_motor_tanpa_plat_ditolak(): void
    {
        $this->garasiDikunci($this->garasiSepeda());
        $this->vehicleRepo->shouldNotReceive('simpan');
        $this->rentalRepo->shouldNotReceive('simpan');

        $this->expectException(ValidationException::class);

        $this->buatService()->buatSewa($this->dataSewa(VehicleType::SEPEDA_MOTOR, null, garageId: 'S001'), $this->bukti());
    }

    /**
     * UT-VR-06 - Mobil listrik tanpa kapasitas baterai ditolak.
     */
    public function test_vr_mobil_listrik_tanpa_kapasitas_ditolak(): void
    {
        $this->vehicleRepo->shouldNotReceive('simpan');
        $this->rentalRepo->shouldNotReceive('simpan');

        $this->expectException(ValidationException::class);

        $this->buatService()->buatSewa($this->dataSewa(VehicleType::MOBIL_LISTRIK, 'DD 1234 AB', null, 20), $this->bukti());
    }

    /**
     * UT-VR-07 - "dd 1234 ab" dan "DD1234AB" dicari sebagai plat yang sama, yaitu
     * dalam format simpan "DD 1234 AB".
     */
    public function test_vr_plat_dinormalisasi_sebelum_dicari(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->twice()->andReturn(null);

        $service = $this->buatService();
        $service->buatSewa($this->dataSewa(plat: 'dd 1234 ab'), $this->bukti());
        $service->buatSewa($this->dataSewa(plat: 'DD1234AB'), $this->bukti());

        $this->addToAssertionCount(1);
    }

    /**
     * UT-VR-08 - Pemakaian ulang dengan kapasitas baterai berubah memperbarui record.
     */
    public function test_vr_kapasitas_baterai_diperbarui_saat_dipakai_ulang(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $lama = (new Vehicle())->forceFill(['penyewa_id' => 1, 'type' => VehicleType::MOBIL_LISTRIK, 'nomor_plat' => 'DD 1234 AB', 'kapasitas_baterai_kwh' => 50]);
        $lama->id = 5;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->andReturn($lama);

        $this->buatService()->buatSewa($this->dataSewa(VehicleType::MOBIL_LISTRIK, 'DD 1234 AB', 72.5, 20), $this->bukti());

        $this->assertSame(72.5, $lama->kapasitas_baterai_kwh);
    }

    /**
     * Plat yang tidak sesuai struktur Indonesia ditolak juga di service (lapis
     * pengaman bila service dipanggil tanpa FormRequest).
     */
    public function test_vr_plat_tidak_sesuai_struktur_ditolak(): void
    {
        $this->garasiDikunci($this->garasiMobil());
        $this->vehicleRepo->shouldNotReceive('cariNomorPlat');
        $this->vehicleRepo->shouldNotReceive('simpan');

        $this->expectException(ValidationException::class);

        $this->buatService()->buatSewa($this->dataSewa(plat: 'TEST12'), $this->bukti());
    }

    /**
     * Pilihan kendaraan: sepeda tanpa plat dengan merek dan tipe sama hanya
     * muncul sekali, karena setiap sewa sepeda membuat record baru.
     */
    public function test_kendaraan_tersimpan_menyaring_sepeda_ganda(): void
    {
        $sepeda = fn (int $id, string $merek) => (new Vehicle())->forceFill([
            'id' => $id, 'penyewa_id' => 1, 'type' => VehicleType::SEPEDA, 'nomor_plat' => null, 'merek' => $merek, 'tipe' => 'MTB',
        ]);
        $mobil = (new Vehicle())->forceFill(['id' => 5, 'penyewa_id' => 1, 'type' => VehicleType::MOBIL, 'nomor_plat' => 'DD 1234 AB']);
        $this->vehicleRepo->shouldReceive('pernahDiparkirPenyewa')->once()
            ->andReturn(new Collection([$sepeda(9, 'Polygon'), $mobil, $sepeda(7, 'Polygon'), $sepeda(6, 'United')]));

        $this->assertSame([9, 5, 6], $this->buatService()->kendaraanTersimpan(1)->pluck('id')->all());
    }

    public function test_cek_nomor_plat_merapikan_plat_baru(): void
    {
        $hasil = $this->buatService()->cekNomorPlat(1, 'dd1234ab', CarbonImmutable::today());

        $this->assertSame(['nomor_plat' => 'DD 1234 AB', 'pesan' => null], $hasil);
    }

    public function test_cek_nomor_plat_menolak_struktur_salah_tanpa_mencari(): void
    {
        $this->vehicleRepo->shouldNotReceive('cariNomorPlat');

        $hasil = $this->buatService()->cekNomorPlat(1, 'DD 0123', CarbonImmutable::today());

        $this->assertSame(['nomor_plat' => null, 'pesan' => NomorPlatFormatter::PESAN], $hasil);
    }

    public function test_cek_nomor_plat_milik_penyewa_lain(): void
    {
        $milikLain = (new Vehicle())->forceFill(['penyewa_id' => 2, 'nomor_plat' => 'DD 1234 AB']);
        $milikLain->id = 5;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->with('DD 1234 AB')->andReturn($milikLain);

        $hasil = $this->buatService()->cekNomorPlat(1, 'DD 1234 AB', CarbonImmutable::today());

        $this->assertSame('Nomor plat sudah terdaftar atas penyewa lain.', $hasil['pesan']);
    }

    public function test_cek_nomor_plat_kendaraan_sendiri_yang_masih_disewa(): void
    {
        $milikSendiri = (new Vehicle())->forceFill(['penyewa_id' => 1, 'nomor_plat' => 'DD 1234 AB']);
        $milikSendiri->id = 5;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->andReturn($milikSendiri);
        $this->rentalRepo->shouldReceive('kendaraanSedangDisewa')->once()->with(5, Mockery::any(), Mockery::any())->andReturn(true);

        $hasil = $this->buatService()->cekNomorPlat(1, 'DD 1234 AB', CarbonImmutable::today());

        $this->assertSame('Kendaraan sedang disewa di garasi lain.', $hasil['pesan']);
    }

    public function test_cek_nomor_plat_kendaraan_sendiri_yang_tidak_disewa_aman(): void
    {
        $milikSendiri = (new Vehicle())->forceFill(['penyewa_id' => 1, 'nomor_plat' => 'DD 1234 AB']);
        $milikSendiri->id = 5;
        $this->vehicleRepo->shouldReceive('cariNomorPlat')->andReturn($milikSendiri);

        $this->assertNull($this->buatService()->cekNomorPlat(1, 'DD 1234 AB', CarbonImmutable::today())['pesan']);
    }
}
