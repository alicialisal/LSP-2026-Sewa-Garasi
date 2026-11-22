<?php

namespace App\Exceptions;

/**
 * Dilempar ketika tipe kendaraan tidak diterima tipe garasi yang dipilih.
 * Dilempar oleh RentalService berdasarkan GarageCompatibilityRule.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class KendaraanTidakCocokException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode KENDARAAN_TIDAK_COCOK
     */
    public function kodeKesalahan(): string
    {
        return 'KENDARAAN_TIDAK_COCOK';
    }

    /**
     * Isian form tempat pesan galat ditampilkan.
     *
     * @return string nama isian garage_id
     */
    public function kunciGalat(): string
    {
        return 'garage_id';
    }
}
