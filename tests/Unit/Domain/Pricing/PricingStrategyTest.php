<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Enums\RentalType;
use App\Domain\Models\CarGarage;
use App\Domain\Pricing\DailyPricingStrategy;
use App\Domain\Pricing\MonthlyPricingStrategy;
use App\Domain\Pricing\RentalPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingStrategyTest extends TestCase
{
    private function garasi(float $harga): CarGarage
    {
        return (new CarGarage())->forceFill(['id' => 'C001', 'harga_sewa_harian' => $harga]);
    }

    private function periode(RentalType $tipe, int $hari): RentalPeriod
    {
        $mulai = CarbonImmutable::parse('2026-11-21');

        return new RentalPeriod($tipe, $hari, $mulai, $mulai->addDays($hari - 1));
    }

    /**
     * UT-PR-01 s/d UT-PR-03 - Sewa harian = harga harian x jumlah hari.
     */
    #[DataProvider('harianProvider')]
    public function test_daily_pricing(int $hari, float $diharapkan): void
    {
        $biaya = (new DailyPricingStrategy())->hitungBiayaSewa(
            $this->garasi(20000),
            $this->periode(RentalType::HARIAN, $hari),
        );

        $this->assertEqualsWithDelta($diharapkan, $biaya, 0.001);
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function harianProvider(): array
    {
        return [
            'UT-PR-01 3 hari' => [3, 60000],
            'UT-PR-02 1 hari' => [1, 20000],
            'UT-PR-03 27 hari' => [27, 540000],
        ];
    }

    /**
     * UT-PR-04 dan UT-PR-05 - Sewa bulanan = 25 x harga harian, tidak bergantung jumlah hari.
     */
    #[DataProvider('bulananProvider')]
    public function test_monthly_pricing(int $hari, float $diharapkan): void
    {
        $biaya = (new MonthlyPricingStrategy())->hitungBiayaSewa(
            $this->garasi(20000),
            $this->periode(RentalType::BULANAN, $hari),
        );

        $this->assertEqualsWithDelta($diharapkan, $biaya, 0.001);
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function bulananProvider(): array
    {
        return [
            'UT-PR-04 periode 28 hari' => [28, 500000],
            'UT-PR-05 periode 31 hari' => [31, 500000],
        ];
    }

    public function test_faktor_bulanan_adalah_25(): void
    {
        $this->assertSame(25, MonthlyPricingStrategy::FAKTOR_BULANAN);
    }
}
