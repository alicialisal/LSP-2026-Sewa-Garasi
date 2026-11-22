<?php

namespace App\Exceptions;

/**
 * Dilempar ketika nomor plat sudah terdaftar atas penyewa lain.
 * Dilempar oleh RentalService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class NomorPlatDipakaiPenyewaLainException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode NOMOR_PLAT_DIPAKAI_PENYEWA_LAIN
     */
    public function kodeKesalahan(): string
    {
        return 'NOMOR_PLAT_DIPAKAI_PENYEWA_LAIN';
    }

    /**
     * Isian form tempat pesan galat ditampilkan.
     *
     * @return string nama isian nomor_plat
     */
    public function kunciGalat(): string
    {
        return 'nomor_plat';
    }
}
