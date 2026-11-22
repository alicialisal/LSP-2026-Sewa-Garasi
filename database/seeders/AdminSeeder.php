<?php

namespace Database\Seeders;

use App\Domain\Enums\UserRole;
use App\Domain\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun admin untuk demo lokal. Ganti kata sandi sebelum dipakai di luar
 * komputer pengembang.
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'admin@garasi.test';

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Administrator', 'password' => 'password', 'role' => UserRole::ADMIN],
        );
    }
}
