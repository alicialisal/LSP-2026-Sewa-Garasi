<?php

namespace App\Domain\Pricing;

/**
 * Hasil estimasi sewa: periode yang sudah ditentukan (termasuk penanda
 * konversi dan notifikasi) beserta rincian biayanya.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
final readonly class RentalEstimate
{
    public function __construct(
        public RentalPeriod $periode,
        public RentalCost $biaya,
    ) {
    }
}
