<?php

namespace Tests\Unit\Domain\Models;

use App\Domain\Enums\GarageType;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\BikeGarage;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GarageTest extends TestCase
{
    private function mobilListrik(): Vehicle
    {
        return (new Vehicle())->forceFill([
            'type' => VehicleType::MOBIL_LISTRIK,
            'kapasitas_baterai_kwh' => 60,
        ]);
    }

    private function sewa(string $mulai, string $selesai, RentalStatus $status): Rental
    {
        // Atribut mentah seperti baris dari basis data; menghindari resolver koneksi.
        $sewa = new Rental();
        $sewa->setRawAttributes([
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'status' => $status->value,
        ], true);

        return $sewa;
    }

    /**
     * UT-PR-06 - Harga sewa bulanan = 25 x harga harian.
     */
    public function test_harga_sewa_bulanan(): void
    {
        $garasi = (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => 20000]);

        $this->assertEqualsWithDelta(500000, $garasi->hargaSewaBulanan(), 0.001);
    }

    /**
     * UT-PR-11 - Garasi mobil tidak mengenakan biaya listrik.
     */
    public function test_car_garage_tanpa_biaya_tambahan(): void
    {
        $garasi = (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => 25000]);

        $this->assertEqualsWithDelta(0, $garasi->biayaTambahan($this->mobilListrik(), 20), 0.001);
    }

    /**
     * UT-PR-12 - Garasi sepeda tidak mengenakan biaya tambahan.
     */
    public function test_bike_garage_tanpa_biaya_tambahan(): void
    {
        $sepeda = (new Vehicle())->forceFill(['type' => VehicleType::SEPEDA]);
        $garasi = (new BikeGarage())->forceFill(['id' => 'S001', 'harga_sewa_harian' => 5000]);

        $this->assertEqualsWithDelta(0, $garasi->biayaTambahan($sepeda, 0), 0.001);
    }

    /**
     * UT-PR-13 - Garasi mobil listrik menolak mobil bensin.
     */
    public function test_garasi_listrik_menolak_mobil_bensin(): void
    {
        $mobil = (new Vehicle())->forceFill(['type' => VehicleType::MOBIL]);
        $garasi = (new ElectricCarGarage())->forceFill(['id' => 'E001']);

        $this->assertFalse($garasi->menerima($mobil));
    }

    public function test_garasi_listrik_dan_mobil_menerima_mobil_listrik(): void
    {
        $this->assertTrue((new ElectricCarGarage())->forceFill(['id' => 'E001'])->menerima($this->mobilListrik()));
        $this->assertTrue((new CarGarage())->forceFill(['id' => 'C001'])->menerima($this->mobilListrik()));
    }

    /**
     * Single Table Inheritance: kolom type memilih kelas turunan saat dimuat.
     */
    #[DataProvider('tipeProvider')]
    public function test_new_from_builder_memilih_kelas_turunan(string $tipe, string $kelas): void
    {
        $garasi = (new CarGarage())->newFromBuilder(['id' => 'X001', 'type' => $tipe]);

        $this->assertInstanceOf($kelas, $garasi);
        $this->assertInstanceOf(Garage::class, $garasi);
        $this->assertTrue($garasi->exists);
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function tipeProvider(): array
    {
        return [
            'BIKE' => ['BIKE', BikeGarage::class],
            'CAR' => ['CAR', CarGarage::class],
            'ELECTRIC' => ['ELECTRIC', ElectricCarGarage::class],
        ];
    }

    public function test_tipe_garasi_dibaca_sebagai_enum(): void
    {
        $garasi = (new CarGarage())->newFromBuilder(['id' => 'E001', 'type' => 'ELECTRIC']);

        $this->assertSame(GarageType::ELECTRIC, $garasi->type);
    }

    /**
     * Setara UT-AC-07 dan UT-AC-08 pada tingkat satu garasi.
     */
    public function test_tersedia_tanpa_sewa_dan_tidak_tersedia_bila_beririsan(): void
    {
        $mulai = CarbonImmutable::parse('2026-11-21');
        $selesai = CarbonImmutable::parse('2026-11-21');

        $kosong = (new CarGarage())->forceFill(['id' => 'C001']);
        $kosong->setRelation('rentals', new Collection());
        $this->assertTrue($kosong->tersediaPada($mulai, $selesai));

        $terpakai = (new CarGarage())->forceFill(['id' => 'C001']);
        $terpakai->setRelation('rentals', new Collection([$this->sewa('2026-11-21', '2026-11-23', RentalStatus::AKTIF)]));
        $this->assertFalse($terpakai->tersediaPada($mulai, $selesai));
    }

    /**
     * Setara UT-AC-09 dan UT-AC-10: sewa SELESAI dan sewa yang sudah lewat diabaikan.
     */
    public function test_sewa_selesai_atau_sudah_lewat_tidak_menghalangi(): void
    {
        $mulai = CarbonImmutable::parse('2026-11-21');
        $selesai = CarbonImmutable::parse('2026-11-21');

        $garasi = (new CarGarage())->forceFill(['id' => 'C001']);
        $garasi->setRelation('rentals', new Collection([
            $this->sewa('2026-11-21', '2026-11-23', RentalStatus::SELESAI),
            $this->sewa('2026-11-01', '2026-11-20', RentalStatus::AKTIF),
        ]));

        $this->assertTrue($garasi->tersediaPada($mulai, $selesai));
    }
}
