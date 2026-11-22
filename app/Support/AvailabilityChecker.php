<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Pemeriksaan ketersediaan garasi. Fungsi murni tanpa efek samping.
 * saringGarasiTersedia() dan hitungHariTerpakai() menyusul bersama model.
 * Dipakai model Garage untuk memeriksa bentrok tanggal sewa.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AvailabilityChecker
{
    /**
     * Apakah dua rentang tanggal beririsan. Tanggal selesai inklusif, sehingga
     * dua sewa yang berbagi satu hari dianggap beririsan.
     *
     * @param  CarbonInterface  $mulaiA  tanggal mulai rentang pertama
     * @param  CarbonInterface  $selesaiA  tanggal selesai rentang pertama (inklusif)
     * @param  CarbonInterface  $mulaiB  tanggal mulai rentang kedua
     * @param  CarbonInterface  $selesaiB  tanggal selesai rentang kedua (inklusif)
     * @return bool true jika kedua rentang berbagi paling sedikit satu hari
     */
    public function adaIrisan(
        CarbonInterface $mulaiA,
        CarbonInterface $selesaiA,
        CarbonInterface $mulaiB,
        CarbonInterface $selesaiB,
    ): bool {
        if ($selesaiA->copy()->startOfDay()->lessThan($mulaiB->copy()->startOfDay())) {
            return false;
        }

        if ($selesaiB->copy()->startOfDay()->lessThan($mulaiA->copy()->startOfDay())) {
            return false;
        }

        return true;
    }
}
