<?php

namespace Tests\Unit\Domain\Rules;

use App\Domain\Rules\BatteryLevelRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BatteryLevelRuleTest extends TestCase
{
    /**
     * UT-BL-01 s/d UT-BL-06 - Syarat 0 <= sisa <= kapasitas, kapasitas positif.
     */
    #[DataProvider('batteryProvider')]
    public function test_batas_sisa_baterai(float $kapasitas, float $sisa, bool $diharapkan): void
    {
        $this->assertSame($diharapkan, (new BatteryLevelRule())->lolos($kapasitas, $sisa));
    }

    /**
     * @return array<string, array{float, float, bool}>
     */
    public static function batteryProvider(): array
    {
        return [
            'UT-BL-01 nilai tengah' => [60, 20, true],
            'UT-BL-02 batas atas' => [60, 60, true],
            'UT-BL-03 batas bawah' => [60, 0, true],
            'UT-BL-04 sisa melebihi kapasitas' => [60, 60.01, false],
            'UT-BL-05 sisa negatif' => [60, -1, false],
            'UT-BL-06 kapasitas tidak positif' => [0, 0, false],
        ];
    }

    /**
     * Pesan kegagalan tersedia untuk ditampilkan di form.
     */
    public function test_pesan_tidak_kosong(): void
    {
        $this->assertNotSame('', (new BatteryLevelRule())->pesan());
    }
}
