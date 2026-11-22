<?php

namespace App\Domain\Enums;

/**
 * Status garasi (cache). Ketersediaan aktual dihitung dari irisan tanggal sewa.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum GarageStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case USED = 'USED';
}
