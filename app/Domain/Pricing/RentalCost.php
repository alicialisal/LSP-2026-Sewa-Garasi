<?php

namespace App\Domain\Pricing;

/**
 * Objek nilai rincian biaya sewa. total = biayaSewa + biayaListrik.
 *
 * biayaSewa, biayaListrik, dan total dalam rupiah; jumlahListrikKwh dalam kWh;
 * hargaListrikPerKwh dalam rupiah per kWh, null bila tidak ada isi listrik.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
final readonly class RentalCost
{
    public float $total;

    public function __construct(
        public float $biayaSewa,
        public float $jumlahListrikKwh,
        public ?float $hargaListrikPerKwh,
        public float $biayaListrik,
    ) {
        $this->total = round($biayaSewa + $biayaListrik, 2);
    }
}
