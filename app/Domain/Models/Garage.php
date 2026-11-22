<?php

namespace App\Domain\Models;

use Database\Factories\GarageFactory;
use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Enums\RentalStatus;
use App\Domain\Pricing\MonthlyPricingStrategy;
use App\Domain\Rules\GarageCompatibilityRule;
use App\Support\AvailabilityChecker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Garasi, diterapkan sebagai Single Table Inheritance: satu tabel garages
 * dengan kolom type sebagai diskriminator. newFromBuilder() memilih kelas
 * turunan yang tepat saat data dimuat.
 *
 * Harga sewa bulanan tidak disimpan; dihitung dari harga harian.
 *
 * Kolom penting: id berupa kode garasi 4 karakter (mis. S001), type (GarageType),
 * harga_sewa_harian dalam rupiah, harga_listrik_per_kwh dalam rupiah per kWh
 * (boleh kosong), dan status (GarageStatus).
 *
 * Relasi: satu garasi punya banyak sewa (Rental).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable(['id', 'type', 'harga_sewa_harian', 'harga_listrik_per_kwh', 'status'])]
abstract class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    protected static function newFactory(): GarageFactory
    {
        return GarageFactory::new();
    }

    public $incrementing = false;

    protected $table = 'garages';

    protected $keyType = 'string';

    /**
     * Biaya tambahan di luar sewa, polimorfik per tipe garasi.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang diparkir
     * @param  float  $sisaBateraiKwh  sisa baterai saat sewa dibuat
     * @return float biaya tambahan dalam rupiah
     */
    abstract public function biayaTambahan(Vehicle $kendaraan, float $sisaBateraiKwh): float;

    /**
     * Membuat model dari baris database dengan kelas turunan sesuai kolom type.
     *
     * @param  array<string, mixed>|object  $attributes
     * @param  string|null  $connection
     * @return Garage turunan yang sesuai kolom type, bukan selalu kelas pemanggil
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $atribut = (array) $attributes;
        $tipe = GarageType::tryFrom((string) ($atribut['type'] ?? ''));

        if ($tipe === null) {
            return parent::newFromBuilder($attributes, $connection);
        }

        $kelas = $tipe->kelasModel();
        $model = (new $kelas())->newInstance([], true);
        $model->setRawAttributes($atribut, true);
        $model->setConnection($connection ?: $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'harga_sewa_harian' => 'float',
            'harga_listrik_per_kwh' => 'float',
            'status' => GarageStatus::class,
        ];
    }

    /**
     * Harga sewa bulanan = FAKTOR_BULANAN x harga sewa harian.
     *
     * @return float harga sewa bulanan dalam rupiah
     */
    public function hargaSewaBulanan(): float
    {
        return round($this->harga_sewa_harian * MonthlyPricingStrategy::FAKTOR_BULANAN, 2);
    }

    /**
     * Apakah garasi ini menerima tipe kendaraan tersebut.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang akan diparkir
     * @return bool true jika tipe kendaraan cocok dengan tipe garasi
     */
    public function menerima(Vehicle $kendaraan): bool
    {
        return (new GarageCompatibilityRule())->lolos($kendaraan->type, $this->type);
    }

    /**
     * Tersedia bila tidak ada sewa AKTIF yang tanggalnya beririsan dengan
     * rentang yang diminta. Status kolom garasi tidak dipakai di sini.
     *
     * @param  CarbonInterface  $mulai  tanggal mulai rentang yang diminta
     * @param  CarbonInterface  $selesai  tanggal selesai rentang yang diminta
     * @return bool true jika garasi tersedia pada rentang tersebut
     */
    public function tersediaPada(CarbonInterface $mulai, CarbonInterface $selesai): bool
    {
        $pemeriksa = new AvailabilityChecker();

        foreach ($this->rentals as $sewa) {
            if ($sewa->status !== RentalStatus::AKTIF) {
                continue;
            }

            if ($pemeriksa->adaIrisan($sewa->tanggal_mulai, $sewa->tanggal_selesai, $mulai, $selesai)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sewa yang memakai garasi ini.
     *
     * @return HasMany daftar sewa garasi ini
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'garage_id');
    }
}
