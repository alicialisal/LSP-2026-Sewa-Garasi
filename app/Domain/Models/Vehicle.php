<?php

namespace App\Domain\Models;

use Database\Factories\VehicleFactory;
use App\Domain\Enums\VehicleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Kendaraan milik penyewa. Sisa baterai tidak disimpan di sini, melainkan
 * sebagai snapshot pada Rental.
 *
 * Kolom penting: type (VehicleType), nomor_plat (unik, boleh kosong untuk
 * sepeda), merek, tipe, dan kapasitas_baterai_kwh dalam kWh, hanya untuk mobil
 * listrik.
 *
 * Relasi: milik satu penyewa (Penyewa) dan punya banyak sewa (Rental).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable(['penyewa_id', 'type', 'nomor_plat', 'merek', 'tipe', 'kapasitas_baterai_kwh'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'kapasitas_baterai_kwh' => 'float',
        ];
    }

    /**
     * Apakah kendaraan ini mobil listrik.
     *
     * @return bool true jika tipe MOBIL_LISTRIK
     */
    public function isElectric(): bool
    {
        return $this->type === VehicleType::MOBIL_LISTRIK;
    }

    /**
     * Apakah kendaraan ini milik penyewa tertentu.
     *
     * @param  int  $penyewaId  id penyewa yang diperiksa
     * @return bool true jika penyewa_id kendaraan sama dengan id tersebut
     */
    public function dimilikiOleh(int $penyewaId): bool
    {
        return (int) $this->penyewa_id === $penyewaId;
    }

    /**
     * Kebutuhan pengisian (kWh) = kapasitas - sisa. Nol bila kendaraan tidak
     * memiliki kapasitas baterai.
     *
     * @param  float  $sisaKwh  sisa baterai dalam kWh, 0 sampai kapasitas
     * @return float kebutuhan pengisian dalam kWh
     * @throws InvalidArgumentException jika sisa negatif atau melebihi kapasitas
     */
    public function kebutuhanPengisianKwh(float $sisaKwh): float
    {
        $kapasitas = $this->kapasitas_baterai_kwh;

        if ($kapasitas === null) {
            return 0.0;
        }

        if ($sisaKwh < 0 || $sisaKwh > $kapasitas) {
            throw new InvalidArgumentException('Sisa baterai harus antara 0 dan kapasitas baterai.');
        }

        return round($kapasitas - $sisaKwh, 2);
    }

    /**
     * Penyewa pemilik kendaraan.
     *
     * @return BelongsTo penyewa pemilik kendaraan
     */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }

    /**
     * Sewa yang memakai kendaraan ini.
     *
     * @return HasMany daftar sewa kendaraan
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'vehicle_id');
    }
}
