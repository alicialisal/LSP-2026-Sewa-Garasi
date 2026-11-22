<?php

namespace Tests\Unit\Support;

use App\Support\CurrencyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    /**
     * UT-CF-01 s/d UT-CF-03 - Format rupiah tanpa pecahan.
     */
    #[DataProvider('rupiahProvider')]
    public function test_ke_rupiah(int|float|string $nilai, string $diharapkan): void
    {
        $this->assertSame($diharapkan, (new CurrencyFormatter())->keRupiah($nilai));
    }

    /**
     * @return array<string, array{int|float|string, string}>
     */
    public static function rupiahProvider(): array
    {
        return [
            'UT-CF-01 satu setengah juta' => [1500000, 'Rp1.500.000'],
            'UT-CF-02 nol' => [0, 'Rp0'],
            'UT-CF-03 pecahan dibulatkan' => [111463.20, 'Rp111.463'],
            'string desimal dari basis data' => ['100000.00', 'Rp100.000'],
        ];
    }
}
