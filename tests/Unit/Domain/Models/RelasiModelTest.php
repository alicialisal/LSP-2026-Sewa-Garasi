<?php

namespace Tests\Unit\Domain\Models;

use App\Domain\Enums\VehicleType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use App\Domain\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Relasi antarmodel sesuai erd.dbml: jenis relasi, model tujuan, dan kunci
 * asingnya. Tidak menjalankan query ke basis data.
 */
class RelasiModelTest extends TestCase
{
    /**
     * @return array<string, array{callable(): Relation, class-string, class-string, string}>
     */
    public static function relasiProvider(): array
    {
        return [
            'User -> Penyewa' => [fn () => (new User())->penyewa(), HasOne::class, Penyewa::class, 'user_id'],
            'Penyewa -> User' => [fn () => (new Penyewa())->user(), BelongsTo::class, User::class, 'user_id'],
            'Penyewa -> Vehicle' => [fn () => (new Penyewa())->vehicles(), HasMany::class, Vehicle::class, 'penyewa_id'],
            'Penyewa -> Rental' => [fn () => (new Penyewa())->rentals(), HasMany::class, Rental::class, 'penyewa_id'],
            'Vehicle -> Penyewa' => [fn () => (new Vehicle())->penyewa(), BelongsTo::class, Penyewa::class, 'penyewa_id'],
            'Vehicle -> Rental' => [fn () => (new Vehicle())->rentals(), HasMany::class, Rental::class, 'vehicle_id'],
            'Garage -> Rental' => [fn () => (new CarGarage())->rentals(), HasMany::class, Rental::class, 'garage_id'],
            'Rental -> Penyewa' => [fn () => (new Rental())->penyewa(), BelongsTo::class, Penyewa::class, 'penyewa_id'],
            'Rental -> Garage' => [fn () => (new Rental())->garage(), BelongsTo::class, CarGarage::class, 'garage_id'],
            'Rental -> Vehicle' => [fn () => (new Rental())->vehicle(), BelongsTo::class, Vehicle::class, 'vehicle_id'],
            'Rental -> RentalExtension' => [fn () => (new Rental())->extensions(), HasMany::class, RentalExtension::class, 'rental_id'],
            'Rental -> Payment' => [fn () => (new Rental())->payments(), HasMany::class, Payment::class, 'rental_id'],
            'RentalExtension -> Rental' => [fn () => (new RentalExtension())->rental(), BelongsTo::class, Rental::class, 'rental_id'],
            'RentalExtension -> Payment' => [fn () => (new RentalExtension())->payment(), HasOne::class, Payment::class, 'rental_extension_id'],
            'Payment -> Rental' => [fn () => (new Payment())->rental(), BelongsTo::class, Rental::class, 'rental_id'],
            'Payment -> RentalExtension' => [fn () => (new Payment())->extension(), BelongsTo::class, RentalExtension::class, 'rental_extension_id'],
        ];
    }

    /**
     * @param  callable(): Relation  $buatRelasi
     */
    #[DataProvider('relasiProvider')]
    public function test_relasi_sesuai_erd(callable $buatRelasi, string $jenis, string $tujuan, string $kunciAsing): void
    {
        $relasi = $buatRelasi();

        $this->assertInstanceOf($jenis, $relasi);
        $this->assertInstanceOf($tujuan, $relasi->getRelated());
        $this->assertSame($kunciAsing, $relasi->getForeignKeyName());
    }

    public function test_garasi_listrik_tidak_mengisi_kendaraan_non_listrik(): void
    {
        $mobil = (new Vehicle())->forceFill(['type' => VehicleType::MOBIL]);
        $garasi = (new ElectricCarGarage())->forceFill(['id' => 'E001', 'harga_listrik_per_kwh' => 2500]);

        $this->assertSame(0.0, $garasi->kwhDibutuhkan($mobil, 0));
        $this->assertSame(0.0, $garasi->biayaTambahan($mobil, 0));
    }

    /**
     * Baris tanpa kolom type tidak dapat dipetakan ke kelas turunan; Eloquent
     * memakai kelas pemanggil.
     */
    public function test_baris_tanpa_tipe_memakai_kelas_pemanggil(): void
    {
        $garasi = (new CarGarage())->newFromBuilder(['id' => 'C001']);

        $this->assertInstanceOf(CarGarage::class, $garasi);
        $this->assertTrue($garasi->exists);
    }

    public function test_penyewa_terverifikasi_hanya_bila_approved(): void
    {
        $this->assertTrue((new Penyewa())->forceFill(['status' => \App\Domain\Enums\RegistrationStatus::APPROVED])->isVerified());
        $this->assertFalse((new Penyewa())->forceFill(['status' => \App\Domain\Enums\RegistrationStatus::PENDING])->isVerified());
    }
}
