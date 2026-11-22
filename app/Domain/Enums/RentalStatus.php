<?php

namespace App\Domain\Enums;

/**
 * Status sewa. Hanya AKTIF dan SELESAI (asumsi nomor 26).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum RentalStatus: string
{
    case AKTIF = 'AKTIF';
    case SELESAI = 'SELESAI';
}
