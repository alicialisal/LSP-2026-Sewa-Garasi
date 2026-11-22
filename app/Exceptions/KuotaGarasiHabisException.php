<?php

namespace App\Exceptions;

/**
 * Dilempar ketika nomor garasi melebihi kuota per tipe (maksimal 999).
 * Dilempar oleh GarageCodeFormatter dan GarageIdGenerator.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class KuotaGarasiHabisException extends AppException
{
    /**
     * Kode kesalahan yang stabil untuk log dan pengujian.
     *
     * @return string kode KUOTA_GARASI_HABIS
     */
    public function kodeKesalahan(): string
    {
        return 'KUOTA_GARASI_HABIS';
    }

    /**
     * Isian form tempat pesan galat ditampilkan.
     *
     * @return string nama isian jumlah
     */
    public function kunciGalat(): string
    {
        return 'jumlah';
    }
}
