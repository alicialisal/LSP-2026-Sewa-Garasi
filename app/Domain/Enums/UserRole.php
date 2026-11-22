<?php

namespace App\Domain\Enums;

/**
 * Peran akun login.
 * Nilai sama dengan enum role pada tabel users.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case PENYEWA = 'PENYEWA';
}
