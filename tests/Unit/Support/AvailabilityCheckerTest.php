<?php

namespace Tests\Unit\Support;

use App\Support\AvailabilityChecker;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * adaIrisan() dengan sewa A = 21 sampai 23 Nov 2026. Tanggal selesai inklusif.
 * saringGarasiTersedia() (UT-AC-07..10) menunggu model Garage dan Rental.
 */
class AvailabilityCheckerTest extends TestCase
{
    /**
     * UT-AC-01 s/d UT-AC-06.
     */
    #[DataProvider('irisanProvider')]
    public function test_ada_irisan(string $mulaiB, string $selesaiB, bool $diharapkan): void
    {
        $hasil = (new AvailabilityChecker())->adaIrisan(
            CarbonImmutable::parse('2026-11-21'),
            CarbonImmutable::parse('2026-11-23'),
            CarbonImmutable::parse($mulaiB),
            CarbonImmutable::parse($selesaiB),
        );

        $this->assertSame($diharapkan, $hasil);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function irisanProvider(): array
    {
        return [
            'UT-AC-01 B sesudah A' => ['2026-11-24', '2026-11-26', false],
            'UT-AC-02 B sebelum A' => ['2026-11-18', '2026-11-20', false],
            'UT-AC-03 akhir A = awal B' => ['2026-11-23', '2026-11-25', true],
            'UT-AC-04 akhir B = awal A' => ['2026-11-18', '2026-11-21', true],
            'UT-AC-06 rentang identik' => ['2026-11-21', '2026-11-23', true],
        ];
    }

    /**
     * UT-AC-05 - B di dalam A (A = 21 - 30 Nov).
     */
    public function test_b_di_dalam_a_beririsan(): void
    {
        $hasil = (new AvailabilityChecker())->adaIrisan(
            CarbonImmutable::parse('2026-11-21'),
            CarbonImmutable::parse('2026-11-30'),
            CarbonImmutable::parse('2026-11-24'),
            CarbonImmutable::parse('2026-11-25'),
        );

        $this->assertTrue($hasil);
    }

    /**
     * Argumen Carbon yang bisa berubah (mutable) beserta jamnya tidak boleh diubah.
     */
    public function test_tidak_mengubah_argumen(): void
    {
        $mulaiA = Carbon::parse('2026-11-21 15:30:00');
        $selesaiA = Carbon::parse('2026-11-23 15:30:00');
        $mulaiB = Carbon::parse('2026-11-23 08:00:00');
        $selesaiB = Carbon::parse('2026-11-25 08:00:00');

        (new AvailabilityChecker())->adaIrisan($mulaiA, $selesaiA, $mulaiB, $selesaiB);

        $this->assertSame('2026-11-21 15:30:00', $mulaiA->toDateTimeString());
        $this->assertSame('2026-11-23 15:30:00', $selesaiA->toDateTimeString());
        $this->assertSame('2026-11-23 08:00:00', $mulaiB->toDateTimeString());
        $this->assertSame('2026-11-25 08:00:00', $selesaiB->toDateTimeString());
    }
}
