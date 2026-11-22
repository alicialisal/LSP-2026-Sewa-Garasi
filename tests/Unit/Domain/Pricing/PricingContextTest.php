<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Vehicle;
use App\Domain\Pricing\DailyPricingStrategy;
use App\Domain\Pricing\MonthlyPricingStrategy;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\RentalPeriod;
use Carbon\CarbonImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

class PricingContextTest extends TestCase
{
    private function mobilListrik(): Vehicle
    {
        return (new Vehicle())->forceFill([
            'type' => VehicleType::MOBIL_LISTRIK,
            'kapasitas_baterai_kwh' => 60,
        ]);
    }

    private function harian3Hari(): RentalPeriod
    {
        $mulai = CarbonImmutable::parse('2026-11-21');

        return new RentalPeriod(RentalType::HARIAN, 3, $mulai, $mulai->addDays(2));
    }

    /**
     * UT-PR-14 - HARIAN memakai DailyPricingStrategy.
     */
    public function test_untuk_tipe_sewa_harian(): void
    {
        $konteks = (new PricingContext())->untukTipeSewa(RentalType::HARIAN);

        $this->assertInstanceOf(DailyPricingStrategy::class, $konteks->strategi());
    }

    /**
     * UT-PR-15 - BULANAN memakai MonthlyPricingStrategy.
     */
    public function test_untuk_tipe_sewa_bulanan(): void
    {
        $konteks = (new PricingContext())->untukTipeSewa(RentalType::BULANAN);

        $this->assertInstanceOf(MonthlyPricingStrategy::class, $konteks->strategi());
    }

    public function test_untuk_tipe_sewa_tidak_mengubah_instance_asal(): void
    {
        $asal = new PricingContext();
        $asal->untukTipeSewa(RentalType::HARIAN);

        $this->expectException(LogicException::class);

        $asal->hitung(
            (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => 25000]),
            $this->mobilListrik(),
            20,
            $this->harian3Hari(),
        );
    }

    /**
     * UT-PR-16 - Mobil listrik di garasi listrik: sewa dan biaya isi listrik.
     */
    public function test_hitung_mobil_listrik_di_garasi_listrik(): void
    {
        $garasi = (new ElectricCarGarage())->forceFill([
            'id' => 'E001',
            'harga_sewa_harian' => 30000,
            'harga_listrik_per_kwh' => 2500,
        ]);

        $biaya = (new PricingContext())
            ->untukTipeSewa(RentalType::HARIAN)
            ->hitung($garasi, $this->mobilListrik(), 20, $this->harian3Hari());

        $this->assertEqualsWithDelta(90000, $biaya->biayaSewa, 0.001);
        $this->assertEqualsWithDelta(40, $biaya->jumlahListrikKwh, 0.001);
        $this->assertEqualsWithDelta(2500, $biaya->hargaListrikPerKwh, 0.001);
        $this->assertEqualsWithDelta(100000, $biaya->biayaListrik, 0.001);
        $this->assertEqualsWithDelta(190000, $biaya->total, 0.001);
    }

    /**
     * UT-PR-17 - Mobil listrik di garasi mobil biasa: tanpa biaya listrik.
     */
    public function test_hitung_mobil_listrik_di_garasi_mobil(): void
    {
        $garasi = (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => 25000]);

        $biaya = (new PricingContext())
            ->untukTipeSewa(RentalType::HARIAN)
            ->hitung($garasi, $this->mobilListrik(), 20, $this->harian3Hari());

        $this->assertEqualsWithDelta(75000, $biaya->biayaSewa, 0.001);
        $this->assertEqualsWithDelta(0, $biaya->jumlahListrikKwh, 0.001);
        $this->assertNull($biaya->hargaListrikPerKwh);
        $this->assertEqualsWithDelta(0, $biaya->biayaListrik, 0.001);
        $this->assertEqualsWithDelta(75000, $biaya->total, 0.001);
    }
}
