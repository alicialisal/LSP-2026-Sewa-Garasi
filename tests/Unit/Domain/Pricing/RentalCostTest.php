<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Pricing\RentalCost;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RentalCostTest extends TestCase
{
    /**
     * UT-PR-21 - total = biayaSewa + biayaListrik pada lima kombinasi.
     */
    #[DataProvider('kombinasiProvider')]
    public function test_total_adalah_biaya_sewa_ditambah_biaya_listrik(
        float $biayaSewa,
        float $kwh,
        ?float $hargaPerKwh,
        float $biayaListrik,
        float $totalDiharapkan,
    ): void {
        $biaya = new RentalCost($biayaSewa, $kwh, $hargaPerKwh, $biayaListrik);

        $this->assertEqualsWithDelta($totalDiharapkan, $biaya->total, 0.001);
        $this->assertEqualsWithDelta($biaya->biayaSewa + $biaya->biayaListrik, $biaya->total, 0.001);
    }

    /**
     * @return array<string, array{float, float, ?float, float, float}>
     */
    public static function kombinasiProvider(): array
    {
        return [
            'harian non-listrik' => [60000, 0, null, 0, 60000],
            'bulanan non-listrik' => [500000, 0, null, 0, 500000],
            'harian listrik' => [90000, 40, 2500, 100000, 190000],
            'bulanan listrik' => [750000, 40, 2500, 100000, 850000],
            'listrik sisa baterai penuh' => [90000, 0, 2500, 0, 90000],
        ];
    }
}
