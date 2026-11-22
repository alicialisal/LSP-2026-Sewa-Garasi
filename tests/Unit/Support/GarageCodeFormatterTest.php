<?php

namespace Tests\Unit\Support;

use App\Domain\Enums\GarageType;
use App\Exceptions\KuotaGarasiHabisException;
use App\Support\GarageCodeFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GarageCodeFormatterTest extends TestCase
{
    private GarageCodeFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new GarageCodeFormatter();
    }

    /**
     * UT-GF-01 s/d UT-GF-03 - Format awalan tipe dan nomor tiga digit.
     */
    #[DataProvider('formatProvider')]
    public function test_format(GarageType $tipe, int $nomor, string $diharapkan): void
    {
        $this->assertSame($diharapkan, $this->formatter->format($tipe, $nomor));
    }

    /**
     * @return array<string, array{GarageType, int, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'UT-GF-01 BIKE 1' => [GarageType::BIKE, 1, 'S001'],
            'UT-GF-02 CAR 10' => [GarageType::CAR, 10, 'C010'],
            'UT-GF-03 ELECTRIC 999 batas atas' => [GarageType::ELECTRIC, 999, 'E999'],
        ];
    }

    /**
     * UT-GF-04 - Nomor 0 tidak valid.
     */
    public function test_format_menolak_nomor_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->formatter->format(GarageType::BIKE, 0);
    }

    /**
     * UT-GF-05 - Nomor 1000 melebihi kuota per tipe.
     */
    public function test_format_menolak_nomor_melebihi_kuota(): void
    {
        $this->expectException(KuotaGarasiHabisException::class);

        $this->formatter->format(GarageType::BIKE, 1000);
    }

    /**
     * UT-GF-06 - Kode diurai menjadi tipe dan nomor.
     */
    public function test_urai(): void
    {
        $this->assertSame([GarageType::CAR, 10], $this->formatter->urai('C010'));
    }

    /**
     * UT-GF-07 dan UT-GF-08 - Kode dengan awalan atau panjang salah tidak valid.
     */
    #[DataProvider('kodeTidakValidProvider')]
    public function test_valid_menolak_kode_salah(string $kode): void
    {
        $this->assertFalse($this->formatter->valid($kode));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function kodeTidakValidProvider(): array
    {
        return [
            'UT-GF-07 awalan salah' => ['X001'],
            'UT-GF-08 kurang digit' => ['S01'],
            'nomor 000' => ['S000'],
        ];
    }

    public function test_valid_menerima_kode_benar(): void
    {
        $this->assertTrue($this->formatter->valid('E003'));
    }

    public function test_urai_menolak_kode_tidak_valid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->formatter->urai('X001');
    }
}
