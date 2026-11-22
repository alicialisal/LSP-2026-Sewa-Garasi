<?php

namespace App\Domain\Models;

use App\Domain\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun login untuk Admin dan Penyewa.
 *
 * Kolom penting: email (unik), password (disimpan sebagai hash), dan role
 * (UserRole).
 *
 * Relasi: akun berperan penyewa punya satu profil Penyewa.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Tabel users pada erd.dbml tidak memiliki kolom remember_token, sehingga
     * fitur "ingat saya" dinonaktifkan.
     *
     * @return string selalu string kosong
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    /**
     * Apakah akun berperan admin.
     *
     * @return bool true jika role ADMIN
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Apakah akun berperan penyewa.
     *
     * @return bool true jika role PENYEWA
     */
    public function isPenyewa(): bool
    {
        return $this->role === UserRole::PENYEWA;
    }

    /**
     * Profil penyewa milik akun ini.
     *
     * @return HasOne profil penyewa
     */
    public function penyewa(): HasOne
    {
        return $this->hasOne(Penyewa::class, 'user_id');
    }
}
