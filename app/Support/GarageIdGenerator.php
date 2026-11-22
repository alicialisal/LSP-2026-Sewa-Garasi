<?php

namespace App\Support;

use App\Domain\Enums\GarageType;
use App\Exceptions\KuotaGarasiHabisException;
use App\Repositories\GarageRepository;
use InvalidArgumentException;

/**
 * Membangkitkan kode garasi berikutnya dengan MAX(id) + 1 per tipe
 * (asumsi nomor 14). Kuota 999 berlaku per tipe. Bergantung pada
 * GarageRepository dan GarageCodeFormatter; dipakai GarageProvisioningService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarageIdGenerator
{
    public function __construct(
        private readonly GarageRepository $garageRepo,
        private readonly GarageCodeFormatter $formatter,
    ) {
    }

    /**
     * Menyiapkan sejumlah kode garasi baru yang melanjutkan nomor terakhir tipe itu.
     *
     * @param  GarageType  $tipe  tipe garasi yang akan ditambah
     * @param  int  $jumlah  banyak kode yang dibuat, minimal 1
     * @return array<int, string> daftar kode berurutan
     *
     * @throws InvalidArgumentException jika jumlah kurang dari satu
     * @throws KuotaGarasiHabisException jika melebihi sisa kuota tipe
     */
    public function berikutnya(GarageType $tipe, int $jumlah): array
    {
        if ($jumlah < 1) {
            throw new InvalidArgumentException('Jumlah garasi minimal 1.');
        }

        $terakhir = $this->garageRepo->nomorTerakhir($tipe);

        if ($terakhir + $jumlah > GarageCodeFormatter::KUOTA_PER_TIPE) {
            throw new KuotaGarasiHabisException(
                'Jumlah garasi melebihi sisa kuota tipe ini.',
                ['tipe' => $tipe->value, 'terakhir' => $terakhir, 'jumlah' => $jumlah],
            );
        }

        $daftarKode = [];

        for ($i = 1; $i <= $jumlah; $i++) {
            $daftarKode[] = $this->formatter->format($tipe, $terakhir + $i);
        }

        return $daftarKode;
    }
}
