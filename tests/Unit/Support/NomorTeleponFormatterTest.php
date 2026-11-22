<?php

namespace Tests\Unit\Support;

use App\Support\NomorTeleponFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nomor HP Indonesia disimpan seragam sebagai +62 diikuti 9-12 angka yang
 * diawali 8, dan ditampilkan sebagai +62 812-3456-7890.
 */
class NomorTeleponFormatterTest extends TestCase
{
    private NomorTeleponFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new NomorTeleponFormatter();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nomorValid(): array
    {
        return [
            'awalan 0' => ['081234567890', '+6281234567890'],
            'awalan 62' => ['6281234567890', '+6281234567890'],
            'awalan +62 dengan spasi dan tanda hubung' => ['+62 812-3456-7890', '+6281234567890'],
            'tanpa awalan (diketik sesudah +62)' => ['812-3456-7890', '+6281234567890'],
            'batas bawah 9 angka' => ['0812345678', '+62812345678'],
            'batas atas 12 angka' => ['0812345678901', '+62812345678901'],
        ];
    }

    #[DataProvider('nomorValid')]
    public function test_normalisasi_nomor_valid(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, $this->formatter->normalisasi($masukan));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nomorTidakValid(): array
    {
        return [
            '8 angka (terlalu pendek)' => ['081234567'],
            '13 angka (terlalu panjang)' => ['08123456789012'],
            'telepon rumah' => ['0211234567'],
            'huruf' => ['nomorku'],
            'kosong' => [''],
        ];
    }

    #[DataProvider('nomorTidakValid')]
    public function test_normalisasi_menolak_nomor_tidak_valid(string $masukan): void
    {
        $this->assertNull($this->formatter->normalisasi($masukan));
    }

    public function test_tampilan_nomor_tersimpan(): void
    {
        $this->assertSame('+62 812-3456-7890', $this->formatter->tampilan('+6281234567890'));
        $this->assertSame('+62 812-3456-78', $this->formatter->tampilan('+62812345678'));
    }

    public function test_tampilan_nilai_lama_berawalan_0_ikut_dirapikan(): void
    {
        $this->assertSame('+62 812-3456-7890', $this->formatter->tampilan('081234567890'));
    }

    public function test_tampilan_nilai_tidak_valid_dikembalikan_apa_adanya(): void
    {
        $this->assertSame('021-1234567', $this->formatter->tampilan('021-1234567'));
    }

    public function test_tanpa_awalan_untuk_isian_form(): void
    {
        $this->assertSame('812-3456-7890', $this->formatter->tanpaAwalan('+6281234567890'));
        $this->assertSame('', $this->formatter->tanpaAwalan(''));
    }
}
