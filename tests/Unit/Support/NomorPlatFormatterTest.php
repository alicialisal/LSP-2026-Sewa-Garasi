<?php

namespace Tests\Unit\Support;

use App\Support\NomorPlatFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Struktur plat nomor Indonesia: kode wilayah (1-2 huruf) + nomor registrasi
 * (1-4 angka, tidak diawali 0) + seri huruf belakang (opsional, 1-3 huruf).
 * Disimpan seragam dengan spasi, misalnya "DD 1234 AB".
 */
class NomorPlatFormatterTest extends TestCase
{
    private NomorPlatFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new NomorPlatFormatter();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function platValid(): array
    {
        return [
            'dengan spasi' => ['DD 1234 AB', 'DD 1234 AB'],
            'tanpa spasi' => ['DD1234AB', 'DD 1234 AB'],
            'huruf kecil dan spasi ganda' => ['  dd  1234   ab ', 'DD 1234 AB'],
            'tanda hubung' => ['DD-1234-AB', 'DD 1234 AB'],
            'kode wilayah 1 huruf, nomor 1 angka, seri 1 huruf' => ['B1A', 'B 1 A'],
            'seri 3 huruf' => ['B 1234 ABC', 'B 1234 ABC'],
            'tanpa seri' => ['D 777', 'D 777'],
            'nomor 4 angka tanpa seri' => ['AB9999', 'AB 9999'],
        ];
    }

    #[DataProvider('platValid')]
    public function test_normalisasi_plat_valid(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, $this->formatter->normalisasi($masukan));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function platTidakValid(): array
    {
        return [
            'kode wilayah 3 huruf' => ['DDD 1234 AB'],
            'kode wilayah 4 huruf' => ['TEST12'],
            'tanpa kode wilayah' => ['1234 AB'],
            'nomor 5 angka' => ['DD 12345 AB'],
            'tanpa nomor' => ['DD AB'],
            'nomor diawali 0' => ['DD 0123 AB'],
            'seri 4 huruf' => ['DD 1234 ABCD'],
            'karakter lain' => ['DD 12#4 AB'],
            'kosong' => [''],
        ];
    }

    #[DataProvider('platTidakValid')]
    public function test_normalisasi_menolak_plat_tidak_valid(string $masukan): void
    {
        $this->assertNull($this->formatter->normalisasi($masukan));
    }
}
