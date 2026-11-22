<?php

namespace Database\Factories;

use App\Domain\Enums\VehicleType;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penyewa_id' => Penyewa::factory()->approved(),
            'type' => VehicleType::MOBIL,
            'nomor_plat' => 'DD '.fake()->unique()->numberBetween(1000, 9999).' '.strtoupper(fake()->lexify('??')),
            'merek' => 'Toyota',
            'tipe' => 'Avanza',
            'kapasitas_baterai_kwh' => null,
        ];
    }

    public function sepeda(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::SEPEDA,
            'nomor_plat' => null,
            'merek' => 'Polygon',
            'tipe' => 'Xtrada',
        ]);
    }

    public function mobilListrik(float $kapasitas = 60): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::MOBIL_LISTRIK,
            'merek' => 'Hyundai',
            'tipe' => 'Ioniq',
            'kapasitas_baterai_kwh' => $kapasitas,
        ]);
    }
}
