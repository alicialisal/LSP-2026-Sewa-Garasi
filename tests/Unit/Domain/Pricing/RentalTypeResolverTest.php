<?php

namespace Tests\Unit\Domain\Pricing;

use App\Domain\Enums\RentalType;
use App\Domain\Pricing\RentalTypeResolver;
use App\Exceptions\JumlahHariTidakValidException;
use App\Support\DateCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Basis path testing untuk RentalTypeResolver::tentukan() (bagian G.2).
 * V(G) = 5, sehingga ada lima jalur independen P1 s/d P5.
 */
class RentalTypeResolverTest extends TestCase
{
    private RentalTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new RentalTypeResolver(new DateCalculator());
    }

    /**
     * Jalur P1 - sewa bulanan: durasi sebanyak hari sebulan, tanpa konversi.
     *
     * UT-RT-01, UT-RT-12, UT-RT-14, UT-RT-15, UT-RT-16, UT-RT-17.
     */
    #[DataProvider('jalurP1Provider')]
    public function test_p1_bulanan_berdurasi_hari_sebulan(string $mulai, int $durasi, string $selesai): void
    {
        $periode = $this->resolver->tentukan(RentalType::BULANAN, 0, CarbonImmutable::parse($mulai));

        $this->assertSame(RentalType::BULANAN, $periode->tipeSewa);
        $this->assertSame($durasi, $periode->jumlahHari);
        $this->assertSame($selesai, $periode->tanggalSelesai->toDateString());
        $this->assertFalse($periode->dikonversiOtomatis);
        $this->assertFalse($periode->melebihiBatas);
        $this->assertSame(0, $periode->jumlahHariTersimpan());
        $this->assertSame('', $periode->pesanNotifikasi());
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function jalurP1Provider(): array
    {
        return [
            'UT-RT-01 November' => ['2026-11-21', 30, '2026-12-20'],
            'UT-RT-12 Desember' => ['2026-12-20', 31, '2027-01-19'],
            'UT-RT-14 akhir bulan, 31 Jan' => ['2027-01-31', 31, '2027-03-02'],
            'UT-RT-15 akhir bulan, 30 Jan' => ['2027-01-30', 31, '2027-03-01'],
            'UT-RT-16 akhir bulan, 29 Jan' => ['2027-01-29', 31, '2027-02-28'],
            'UT-RT-17 awal Februari' => ['2026-02-01', 28, '2026-02-28'],
        ];
    }

    /**
     * Jalur P2 - jumlah hari kurang dari satu ditolak.
     *
     * UT-RT-02 dan UT-RT-03.
     */
    #[DataProvider('jalurP2Provider')]
    public function test_p2_jumlah_hari_kurang_dari_satu_ditolak(int $jumlahHari): void
    {
        $this->expectException(JumlahHariTidakValidException::class);

        $this->resolver->tentukan(RentalType::HARIAN, $jumlahHari, CarbonImmutable::parse('2026-11-21'));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function jalurP2Provider(): array
    {
        return [
            'UT-RT-02 nol' => [0],
            'UT-RT-03 negatif' => [-1],
        ];
    }

    /**
     * Jalur P3 - melebihi hari sebulan: menjadi bulanan disertai notifikasi.
     *
     * UT-RT-04 dan UT-RT-05.
     */
    #[DataProvider('jalurP3Provider')]
    public function test_p3_melebihi_hari_sebulan_menjadi_bulanan_dengan_notifikasi(
        int $jumlahHari,
        string $mulai,
        int $durasi,
        string $selesai,
    ): void {
        $periode = $this->resolver->tentukan(RentalType::HARIAN, $jumlahHari, CarbonImmutable::parse($mulai));

        $this->assertSame(RentalType::BULANAN, $periode->tipeSewa);
        $this->assertSame($durasi, $periode->jumlahHari);
        $this->assertSame($selesai, $periode->tanggalSelesai->toDateString());
        $this->assertTrue($periode->dikonversiOtomatis);
        $this->assertTrue($periode->melebihiBatas);
        $this->assertSame(0, $periode->jumlahHariTersimpan());
        $this->assertSame(
            "Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan ({$durasi} hari).",
            $periode->pesanNotifikasi(),
        );
    }

    /**
     * @return array<string, array{int, string, int, string}>
     */
    public static function jalurP3Provider(): array
    {
        return [
            'UT-RT-04 Februari 2026' => [29, '2026-02-10', 28, '2026-03-09'],
            'UT-RT-05 Februari 2028 kabisat' => [35, '2028-02-10', 29, '2028-03-09'],
        ];
    }

    /**
     * Jalur P4 - tepat sebanyak hari sebulan: konversi otomatis tanpa notifikasi.
     *
     * UT-RT-06, UT-RT-07, UT-RT-08, UT-RT-18.
     */
    #[DataProvider('jalurP4Provider')]
    public function test_p4_sebanyak_hari_sebulan_dikonversi_menjadi_bulanan(
        int $jumlahHari,
        string $mulai,
        int $durasi,
        string $selesai,
    ): void {
        $periode = $this->resolver->tentukan(RentalType::HARIAN, $jumlahHari, CarbonImmutable::parse($mulai));

        $this->assertSame(RentalType::BULANAN, $periode->tipeSewa);
        $this->assertSame($durasi, $periode->jumlahHari);
        $this->assertSame($selesai, $periode->tanggalSelesai->toDateString());
        $this->assertTrue($periode->dikonversiOtomatis);
        $this->assertFalse($periode->melebihiBatas);
        $this->assertSame(0, $periode->jumlahHariTersimpan());
        $this->assertSame('', $periode->pesanNotifikasi());
    }

    /**
     * @return array<string, array{int, string, int, string}>
     */
    public static function jalurP4Provider(): array
    {
        return [
            'UT-RT-06 Februari 2026' => [28, '2026-02-10', 28, '2026-03-09'],
            'UT-RT-07 Februari 2028 kabisat' => [29, '2028-02-10', 29, '2028-03-09'],
            'UT-RT-08 bulan 31 hari' => [31, '2027-01-05', 31, '2027-02-04'],
            'UT-RT-18 akhir bulan' => [31, '2027-01-31', 31, '2027-03-02'],
        ];
    }

    /**
     * Jalur P5 - kurang dari hari sebulan: tetap harian.
     *
     * UT-RT-09, UT-RT-10, UT-RT-11, UT-RT-13.
     */
    #[DataProvider('jalurP5Provider')]
    public function test_p5_kurang_dari_hari_sebulan_tetap_harian(
        int $jumlahHari,
        string $mulai,
        string $selesai,
    ): void {
        $periode = $this->resolver->tentukan(RentalType::HARIAN, $jumlahHari, CarbonImmutable::parse($mulai));

        $this->assertSame(RentalType::HARIAN, $periode->tipeSewa);
        $this->assertSame($jumlahHari, $periode->jumlahHari);
        $this->assertSame($selesai, $periode->tanggalSelesai->toDateString());
        $this->assertFalse($periode->dikonversiOtomatis);
        $this->assertFalse($periode->melebihiBatas);
        $this->assertSame($jumlahHari, $periode->jumlahHariTersimpan());
        $this->assertSame('', $periode->pesanNotifikasi());
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function jalurP5Provider(): array
    {
        return [
            'UT-RT-09 batas atas Februari 2026' => [27, '2026-02-10', '2026-03-08'],
            'UT-RT-10 batas atas Februari 2028 kabisat' => [28, '2028-02-10', '2028-03-08'],
            'UT-RT-11 batas bawah' => [1, '2026-11-21', '2026-11-21'],
            'UT-RT-13 batas atas Desember' => [30, '2026-12-20', '2027-01-18'],
        ];
    }

    /**
     * Pelengkap RentalPeriod::mencakup(): tanggal selesai bersifat inklusif.
     */
    public function test_periode_mencakup_tanggal_mulai_dan_selesai_tetapi_tidak_di_luarnya(): void
    {
        $periode = $this->resolver->tentukan(RentalType::HARIAN, 3, CarbonImmutable::parse('2026-11-21'));

        $this->assertFalse($periode->mencakup(CarbonImmutable::parse('2026-11-20')));
        $this->assertTrue($periode->mencakup(CarbonImmutable::parse('2026-11-21')));
        $this->assertTrue($periode->mencakup(CarbonImmutable::parse('2026-11-23')));
        $this->assertFalse($periode->mencakup(CarbonImmutable::parse('2026-11-24')));
    }
}
