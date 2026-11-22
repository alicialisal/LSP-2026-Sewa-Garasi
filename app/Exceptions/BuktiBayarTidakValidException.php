<?php

namespace App\Exceptions;

/**
 * Dilempar ketika berkas bukti bayar bukan JPG/PNG atau melebihi batas ukuran.
 * Dilempar oleh PaymentProofStorage, juga bila gambar tidak dapat dibaca.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class BuktiBayarTidakValidException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode BUKTI_BAYAR_TIDAK_VALID
     */
    public function kodeKesalahan(): string
    {
        return 'BUKTI_BAYAR_TIDAK_VALID';
    }

    /**
     * Isian form tempat pesan galat ditampilkan.
     *
     * @return string nama isian bukti_bayar
     */
    public function kunciGalat(): string
    {
        return 'bukti_bayar';
    }
}
