<?php

namespace App\Domain\Enums;

/**
 * Tipe kendaraan. Nilai sama dengan enum vehicle_type pada tabel vehicles.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
enum VehicleType: string
{
    case SEPEDA = 'SEPEDA';
    case SEPEDA_MOTOR = 'SEPEDA_MOTOR';
    case MOBIL = 'MOBIL';
    case MOBIL_LISTRIK = 'MOBIL_LISTRIK';

    /**
     * Tipe garasi yang boleh dipakai kendaraan ini.
     *
     * @return array<int, GarageType>
     */
    public function tipeGarasiDiizinkan(): array
    {
        return match ($this) {
            self::SEPEDA, self::SEPEDA_MOTOR => [GarageType::BIKE],
            self::MOBIL => [GarageType::CAR],
            self::MOBIL_LISTRIK => [GarageType::CAR, GarageType::ELECTRIC],
        };
    }

    /**
     * Nomor plat wajib untuk semua kendaraan kecuali sepeda.
     *
     * @return bool true jika nomor plat wajib diisi
     */
    public function wajibNomorPlat(): bool
    {
        return $this !== self::SEPEDA;
    }

    /**
     * Kapasitas baterai wajib hanya untuk mobil listrik.
     *
     * @return bool true jika kapasitas baterai wajib diisi
     */
    public function wajibInfoBaterai(): bool
    {
        return $this === self::MOBIL_LISTRIK;
    }
}
