<?php

namespace App\Domain\Models;

use Database\Factories\PenyewaFactory;
use App\Domain\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil penyewa. Hanya status APPROVED yang dapat melakukan penyewaan.
 *
 * Kolom penting: nik (16 karakter, unik), nama_lengkap, nomor_telepon, status
 * (RegistrationStatus), verified_by dan verified_at (admin dan waktu verifikasi),
 * serta alasan_penolakan.
 *
 * Relasi: milik satu akun (User); satu penyewa punya banyak kendaraan (Vehicle)
 * dan banyak sewa (Rental).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable(['user_id', 'nik', 'nama_lengkap', 'nomor_telepon', 'status', 'verified_by', 'verified_at', 'alasan_penolakan'])]
class Penyewa extends Model
{
    /** @use HasFactory<PenyewaFactory> */
    use HasFactory;

    protected static function newFactory(): PenyewaFactory
    {
        return PenyewaFactory::new();
    }

    protected $table = 'penyewa';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Apakah penyewa sudah disetujui (APPROVED).
     *
     * @return bool true jika status APPROVED
     */
    public function isVerified(): bool
    {
        return $this->status === RegistrationStatus::APPROVED;
    }

    /**
     * Akun login milik penyewa.
     *
     * @return BelongsTo akun user penyewa
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Kendaraan milik penyewa.
     *
     * @return HasMany daftar kendaraan penyewa
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'penyewa_id');
    }

    /**
     * Sewa milik penyewa.
     *
     * @return HasMany daftar sewa penyewa
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'penyewa_id');
    }
}
