<?php

namespace App\Domain\Enums;

/**
 * Status verifikasi penyewa. Hanya APPROVED yang dapat mengajukan sewa.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum RegistrationStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
