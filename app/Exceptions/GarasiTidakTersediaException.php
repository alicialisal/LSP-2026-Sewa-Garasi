<?php

namespace App\Exceptions;

/**
 * Dilempar ketika garasi tidak ada atau sudah dipakai pada tanggal yang diminta.
 * Dilempar oleh RentalService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarasiTidakTersediaException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode GARASI_TIDAK_TERSEDIA
     */
    public function kodeKesalahan(): string
    {
        return 'GARASI_TIDAK_TERSEDIA';
    }
}
