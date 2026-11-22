<?php

namespace Tests\Unit\Support;

use App\Support\DateCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateCalculatorTest extends TestCase
{
    private DateCalculator $kalender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kalender = new DateCalculator();
    }

    /**
     * UT-DC-01 s/d UT-DC-04 - Jumlah hari dalam bulan tanggal mulai.
     */
    #[DataProvider('hariSebulanProvider')]
    public function test_jumlah_hari_sebulan(string $tanggal, int $diharapkan): void
    {
        $hasil = $this->kalender->jumlahHariSebulan(CarbonImmutable::parse($tanggal));

        $this->assertSame($diharapkan, $hasil);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function hariSebulanProvider(): array
    {
        return [
            'UT-DC-01 bulan 28 hari' => ['2026-02-10', 28],
            'UT-DC-02 tahun kabisat' => ['2028-02-10', 29],
            'UT-DC-03 bulan 30 hari' => ['2026-11-21', 30],
            'UT-DC-04 bulan 31 hari' => ['2027-01-31', 31],
        ];
    }

    /**
     * UT-DC-05 s/d UT-DC-07 - Tanggal selesai inklusif: mulai + jumlah hari - 1.
     */
    #[DataProvider('tanggalSelesaiProvider')]
    public function test_tanggal_selesai_bersifat_inklusif(string $mulai, int $jumlahHari, string $diharapkan): void
    {
        $hasil = $this->kalender->tanggalSelesai(CarbonImmutable::parse($mulai), $jumlahHari);

        $this->assertSame($diharapkan, $hasil->toDateString());
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function tanggalSelesaiProvider(): array
    {
        return [
            'UT-DC-05 batas bawah 1 hari' => ['2026-11-21', 1, '2026-11-21'],
            'UT-DC-06 lintas bulan' => ['2026-11-21', 15, '2026-12-05'],
            'UT-DC-07 lintas tahun' => ['2026-12-20', 31, '2027-01-19'],
        ];
    }

    /**
     * UT-DC-08 - Jumlah hari kurang dari satu ditolak.
     */
    public function test_tanggal_selesai_menolak_jumlah_hari_tidak_valid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->kalender->tanggalSelesai(CarbonImmutable::parse('2026-11-21'), 0);
    }

    /**
     * UT-DC-09 dan UT-DC-10 - Selisih hari kalender antara dua tanggal.
     */
    #[DataProvider('selisihHariProvider')]
    public function test_selisih_hari(string $awal, string $akhir, int $diharapkan): void
    {
        $hasil = $this->kalender->selisihHari(CarbonImmutable::parse($awal), CarbonImmutable::parse($akhir));

        $this->assertSame($diharapkan, $hasil);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function selisihHariProvider(): array
    {
        return [
            'UT-DC-09 tanggal berbeda' => ['2026-11-23', '2026-11-26', 3],
            'UT-DC-10 tanggal sama' => ['2026-11-23', '2026-11-23', 0],
        ];
    }
}
