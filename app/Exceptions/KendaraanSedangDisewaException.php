<?php

namespace App\Exceptions;

/**
 * Dilempar ketika kendaraan masih aktif di sewa garasi lain (asumsi nomor 23).
 * Dilempar oleh RentalService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class KendaraanSedangDisewaException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode KENDARAAN_SEDANG_DISEWA
     */
    public function kodeKesalahan(): string
    {
        return 'KENDARAAN_SEDANG_DISEWA';
    }
}
