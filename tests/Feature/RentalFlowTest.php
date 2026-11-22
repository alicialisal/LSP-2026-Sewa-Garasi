<?php

namespace Tests\Feature;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\Garage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use App\Exceptions\GarasiTidakTersediaException;
use App\Repositories\GarageRepository;
use App\Services\ExtensionService;
use App\Services\RentalService;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur sewa dan perpanjangan dengan repository Eloquent dan MySQL sungguhan
 * (tanpa mock): inti FT-07, FT-13, FT-17, dan FT-18.
 */
class RentalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
        Storage::fake(PaymentProofStorage::DISK);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function dataSewa(Penyewa $penyewa, string $plat, string $garageId = 'C001', int $hari = 3): array
    {
        return [
            'penyewaId' => $penyewa->id,
            'tipeKendaraan' => VehicleType::MOBIL,
            'nomorPlat' => $plat,
            'merek' => 'Toyota',
            'tipe' => 'Avanza',
            'kapasitasBateraiKwh' => null,
            'sisaBateraiKwh' => null,
            'tipeSewa' => RentalType::HARIAN,
            'jumlahHari' => $hari,
            'garageId' => $garageId,
        ];
    }

    private function bukti(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg');
    }

    public function test_buat_sewa_menyimpan_sewa_pembayaran_dan_menandai_garasi_used(): void
    {
        Garage::factory()->car('C001')->create();
        $penyewa = Penyewa::factory()->approved()->create();

        $sewa = $this->app->make(RentalService::class)
            ->buatSewa($this->dataSewa($penyewa, 'dd 1234 ab'), $this->bukti());

        $this->assertSame('SEW-20261121-0001', $sewa->nomor_nota);
        $this->assertSame(75000.0, $sewa->fresh()->total_biaya);
        $this->assertSame('2026-11-23', $sewa->fresh()->tanggal_selesai->toDateString());
        $this->assertDatabaseHas('vehicles', ['nomor_plat' => 'DD 1234 AB', 'penyewa_id' => $penyewa->id]);

        $pembayaran = Payment::query()->where('rental_id', $sewa->id)->sole();
        $this->assertSame(75000.0, $pembayaran->jumlah);
        $this->assertNull($pembayaran->rental_extension_id);
        Storage::disk(PaymentProofStorage::DISK)->assertExists($pembayaran->path_bukti_bayar);

        $this->assertSame(GarageStatus::USED, (new GarageRepository())->cariId('C001')->status);
    }

    /**
     * FT-07: penyewa kedua yang memilih garasi yang sama pada hari yang sama ditolak,
     * tidak ada baris yang tertinggal dan berkas yang terunggah dihapus kembali.
     */
    public function test_penyewa_kedua_pada_garasi_yang_sama_ditolak(): void
    {
        Garage::factory()->car('C001')->create();
        $pertama = Penyewa::factory()->approved()->create();
        $kedua = Penyewa::factory()->approved()->create();
        $service = $this->app->make(RentalService::class);

        $service->buatSewa($this->dataSewa($pertama, 'DD 1111 AA'), $this->bukti());

        try {
            $service->buatSewa($this->dataSewa($kedua, 'DD 2222 BB'), $this->bukti());
            $this->fail('GarasiTidakTersediaException tidak dilempar.');
        } catch (GarasiTidakTersediaException) {
            $this->assertSame(1, Rental::query()->count());
            $this->assertSame(1, Payment::query()->count());
            $this->assertDatabaseMissing('vehicles', ['nomor_plat' => 'DD 2222 BB']);
            $this->assertCount(1, Storage::disk(PaymentProofStorage::DISK)->allFiles());
        }
    }

    /**
     * FT-18: kendaraan yang masih aktif di garasi lain ditolak.
     */
    public function test_kendaraan_aktif_di_garasi_lain_ditolak(): void
    {
        Garage::factory()->car('C001')->create();
        Garage::factory()->car('C002')->create();
        $penyewa = Penyewa::factory()->approved()->create();
        $service = $this->app->make(RentalService::class);

        $service->buatSewa($this->dataSewa($penyewa, 'DD 1111 AA', 'C001'), $this->bukti());

        $this->expectException(\App\Exceptions\KendaraanSedangDisewaException::class);

        $service->buatSewa($this->dataSewa($penyewa, 'DD 1111 AA', 'C002'), $this->bukti());
    }

    /**
     * FT-13: menyewa ulang dengan plat sendiri setelah sewa lama selesai memakai
     * ulang record kendaraan.
     */
    public function test_sewa_ulang_dengan_plat_sendiri_memakai_ulang_kendaraan(): void
    {
        Garage::factory()->car('C001')->create();
        $penyewa = Penyewa::factory()->approved()->create();
        $kendaraan = Vehicle::factory()->create(['penyewa_id' => $penyewa->id, 'nomor_plat' => 'DD 1234 AB']);
        Rental::factory()->selesai()->create([
            'penyewa_id' => $penyewa->id,
            'vehicle_id' => $kendaraan->id,
            'garage_id' => 'C001',
            'nomor_nota' => 'SEW-20261101-0001',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-03',
        ]);

        $sewa = $this->app->make(RentalService::class)->buatSewa($this->dataSewa($penyewa, 'DD 1234 AB'), $this->bukti());

        $this->assertSame($kendaraan->id, $sewa->vehicle_id);
        $this->assertSame(1, Vehicle::query()->count());
    }

    /**
     * FT-17 dan perpanjangan sampai tuntas: daftar hanya sewa aktif hari ini, lalu
     * perpanjangan menyimpan riwayat, pembayaran, dan tanggal selesai baru.
     */
    public function test_perpanjangan_menyimpan_riwayat_pembayaran_dan_tanggal_baru(): void
    {
        Garage::factory()->car('C001')->create();
        $penyewa = Penyewa::factory()->approved()->create();
        $service = $this->app->make(RentalService::class);
        $sewa = $service->buatSewa($this->dataSewa($penyewa, 'DD 1234 AB'), $this->bukti());
        Rental::factory()->selesai()->create(['nomor_nota' => 'SEW-20261101-0009', 'garage_id' => 'C001']);

        $extension = $this->app->make(ExtensionService::class);
        $admin = \App\Domain\Models\User::factory()->admin()->create();

        $this->assertSame([$sewa->id], $extension->sewaAktifHariIni()->pluck('id')->all());
        $this->assertInstanceOf(\App\Domain\Models\CarGarage::class, $sewa->fresh()->garage);

        $perpanjangan = $extension->perpanjang($sewa->id, CarbonImmutable::parse('2026-11-26'), $this->bukti(), $admin->id);

        $this->assertSame('PJG-20261121-0001', $perpanjangan->nomor_perpanjang);
        $this->assertSame(75000.0, $perpanjangan->biaya_tambahan);
        $this->assertSame('2026-11-26', $sewa->fresh()->tanggal_selesai->toDateString());
        $this->assertSame(RentalStatus::AKTIF, $sewa->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $sewa->id,
            'rental_extension_id' => $perpanjangan->id,
        ]);
    }
}
