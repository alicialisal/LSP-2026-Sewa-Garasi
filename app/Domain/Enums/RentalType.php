<?php

namespace App\Domain\Enums;

/**
 * Tipe sewa. Nilai sama dengan enum rental_type pada tabel rentals.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum RentalType: string
{
    case HARIAN = 'HARIAN';
    case BULANAN = 'BULANAN';
}
