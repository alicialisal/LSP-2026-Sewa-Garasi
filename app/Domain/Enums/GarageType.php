<?php

namespace App\Domain\Enums;

use App\Domain\Models\BikeGarage;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;

/**
 * Tipe garasi. Nilai sama dengan enum garage_type pada tabel garages.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum GarageType: string
{
    case BIKE = 'BIKE';
    case CAR = 'CAR';
    case ELECTRIC = 'ELECTRIC';

    /**
     * Awalan kode garasi: S001-S999, C001-C999, E001-E999.
     *
     * @return string huruf awalan kode garasi (S, C, atau E)
     */
    public function prefixKode(): string
    {
        return match ($this) {
            self::BIKE => 'S',
            self::CAR => 'C',
            self::ELECTRIC => 'E',
        };
    }

    /**
     * Kelas model turunan Garage untuk tipe ini (Single Table Inheritance).
     *
     * @return class-string<Garage>
     */
    public function kelasModel(): string
    {
        return match ($this) {
            self::BIKE => BikeGarage::class,
            self::CAR => CarGarage::class,
            self::ELECTRIC => ElectricCarGarage::class,
        };
    }

    /**
     * Nama tipe garasi untuk ditampilkan.
     *
     * @return string nama tipe garasi
     */
    public function label(): string
    {
        return match ($this) {
            self::BIKE => 'Garasi Sepeda',
            self::CAR => 'Garasi Mobil',
            self::ELECTRIC => 'Garasi Mobil Listrik',
        };
    }
}
