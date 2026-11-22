<?php

namespace App\Exceptions;

/**
 * Dilempar ketika penyewa belum berstatus APPROVED mengajukan sewa.
 * Dilempar oleh RentalService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class PenyewaBelumTerverifikasiException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode PENYEWA_BELUM_TERVERIFIKASI
     */
    public function kodeKesalahan(): string
    {
        return 'PENYEWA_BELUM_TERVERIFIKASI';
    }
}
