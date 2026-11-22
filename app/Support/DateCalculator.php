<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Perhitungan tanggal sewa. Fungsi murni tanpa status internal dan tanpa
 * akses basis data. Dipakai ExtensionService, RentalTypeResolver, model
 * Rental, dan RentalController.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class DateCalculator
{
    /**
     * Jumlah hari dalam bulan dari tanggal yang diberikan. Tanggal berapa pun
     * di bulan yang sama menghasilkan nilai yang sama.
     *
     * @param  CarbonInterface  $tanggal  tanggal mana pun di bulan yang dihitung
     * @return int jumlah hari dalam bulan tersebut (28 sampai 31)
     */
    public function jumlahHariSebulan(CarbonInterface $tanggal): int
    {
        return $tanggal->daysInMonth;
    }

    /**
     * Tanggal selesai sewa. Bersifat inklusif, yaitu hari terakhir pemakaian:
     * tanggal mulai + jumlah hari - 1.
     *
     * @param  CarbonInterface  $mulai  tanggal mulai sewa
     * @param  int  $jumlahHari  lama sewa dalam hari, minimal 1
     * @return CarbonImmutable hari terakhir pemakaian, pada awal hari
     *
     * @throws InvalidArgumentException jika jumlah hari kurang dari satu
     */
    public function tanggalSelesai(CarbonInterface $mulai, int $jumlahHari): CarbonImmutable
    {
        if ($jumlahHari < 1) {
            throw new InvalidArgumentException('Jumlah hari minimal 1.');
        }

        return CarbonImmutable::instance($mulai)
            ->startOfDay()
            ->addDays($jumlahHari - 1);
    }

    /**
     * Selisih hari kalender dari tanggal awal ke tanggal akhir. Bernilai
     * negatif jika tanggal akhir mendahului tanggal awal.
     *
     * @param  CarbonInterface  $awal  tanggal awal (jam diabaikan)
     * @param  CarbonInterface  $akhir  tanggal akhir (jam diabaikan)
     * @return int selisih dalam hari
     */
    public function selisihHari(CarbonInterface $awal, CarbonInterface $akhir): int
    {
        return (int) CarbonImmutable::instance($awal)->startOfDay()
            ->diffInDays(CarbonImmutable::instance($akhir)->startOfDay(), false);
    }
}
