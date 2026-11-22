<?php

namespace App\Exceptions;

/**
 * Dilempar ketika jumlah hari sewa kurang dari satu. Dilempar oleh
 * RentalTypeResolver dan ExtensionService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class JumlahHariTidakValidException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode JUMLAH_HARI_TIDAK_VALID
     */
    public function kodeKesalahan(): string
    {
        return 'JUMLAH_HARI_TIDAK_VALID';
    }
}
