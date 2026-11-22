<?php

namespace Database\Factories;

use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Models\Garage;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    protected $model = Rental::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_nota' => 'SEW-20261121-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'penyewa_id' => Penyewa::factory()->approved(),
            'garage_id' => Garage::factory(),
            'vehicle_id' => Vehicle::factory(),
            'tipe_sewa' => RentalType::HARIAN,
            'jumlah_hari' => 3,
            'tanggal_mulai' => '2026-11-21',
            'tanggal_selesai' => '2026-11-23',
            'biaya_sewa' => 75000,
            'jumlah_listrik_kwh' => 0,
            'biaya_listrik' => 0,
            'total_biaya' => 75000,
            'status' => RentalStatus::AKTIF,
        ];
    }

    public function periode(string $mulai, string $selesai): static
    {
        return $this->state(fn () => ['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
    }

    public function selesai(): static
    {
        return $this->state(fn () => ['status' => RentalStatus::SELESAI]);
    }
}
