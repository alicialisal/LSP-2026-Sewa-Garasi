<?php

namespace App\Domain\Pricing;

use App\Domain\Enums\RentalType;
use App\Exceptions\JumlahHariTidakValidException;
use App\Support\DateCalculator;
use Carbon\CarbonInterface;

/**
 * Satu-satunya tempat aturan periode sewa diterapkan: hari sebulan, konversi
 * otomatis harian ke bulanan, dan tanggal selesai.
 *
 * Kompleksitas siklomatik 5 (lihat diagrams.md bagian G.2).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RentalTypeResolver
{
    public function __construct(private readonly DateCalculator $kalender)
    {
    }

    /**
     * Menentukan periode sewa dari isian penyewa.
     *
     * @param  RentalType  $tipeSewa  tipe sewa pilihan penyewa
     * @param  int  $jumlahHari  lama sewa dalam hari, minimal 1 untuk sewa HARIAN; diabaikan untuk sewa BULANAN
     * @param  CarbonInterface  $tanggalMulai  tanggal mulai sewa
     * @return RentalPeriod periode sewa; bertipe BULANAN bila jumlah hari sama dengan
     *                      atau melebihi jumlah hari sebulan
     * @throws JumlahHariTidakValidException jika sewa harian kurang dari satu hari
     */
    public function tentukan(RentalType $tipeSewa, int $jumlahHari, CarbonInterface $tanggalMulai): RentalPeriod
    {
        $hariSebulan = $this->kalender->jumlahHariSebulan($tanggalMulai);

        if ($tipeSewa === RentalType::BULANAN) {
            return $this->buatPeriode(RentalType::BULANAN, $hariSebulan, $tanggalMulai);
        }

        if ($jumlahHari < 1) {
            throw new JumlahHariTidakValidException(
                'Jumlah hari sewa minimal 1 hari.',
                ['jumlah_hari' => $jumlahHari],
            );
        }

        if ($jumlahHari > $hariSebulan) {
            return $this->buatPeriode(RentalType::BULANAN, $hariSebulan, $tanggalMulai, true, true);
        }

        if ($jumlahHari === $hariSebulan) {
            return $this->buatPeriode(RentalType::BULANAN, $hariSebulan, $tanggalMulai, true);
        }

        return $this->buatPeriode(RentalType::HARIAN, $jumlahHari, $tanggalMulai);
    }

    private function buatPeriode(
        RentalType $tipeSewa,
        int $durasi,
        CarbonInterface $tanggalMulai,
        bool $dikonversiOtomatis = false,
        bool $melebihiBatas = false,
    ): RentalPeriod {
        return new RentalPeriod(
            $tipeSewa,
            $durasi,
            $tanggalMulai,
            $this->kalender->tanggalSelesai($tanggalMulai, $durasi),
            $dikonversiOtomatis,
            $melebihiBatas,
        );
    }
}
