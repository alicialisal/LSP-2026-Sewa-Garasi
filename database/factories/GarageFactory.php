<?php

namespace Database\Factories;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pabrik garasi. Model yang dibuat adalah kelas turunan sesuai kolom type
 * (Single Table Inheritance), karena Garage sendiri abstrak.
 *
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    protected $model = Garage::class;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Garage
    {
        $tipe = $attributes['type'] ?? GarageType::CAR;
        $tipe = $tipe instanceof GarageType ? $tipe : GarageType::from($tipe);
        $kelas = $tipe->kelasModel();

        return new $kelas($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'C'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => GarageType::CAR,
            'harga_sewa_harian' => 25000,
            'harga_listrik_per_kwh' => null,
            'status' => GarageStatus::AVAILABLE,
        ];
    }

    public function bike(string $id = 'S001'): static
    {
        return $this->state(fn () => [
            'id' => $id,
            'type' => GarageType::BIKE,
            'harga_sewa_harian' => 5000,
        ]);
    }

    public function car(string $id = 'C001'): static
    {
        return $this->state(fn () => ['id' => $id, 'type' => GarageType::CAR]);
    }

    public function electric(string $id = 'E001', float $hargaListrik = 2500): static
    {
        return $this->state(fn () => [
            'id' => $id,
            'type' => GarageType::ELECTRIC,
            'harga_sewa_harian' => 30000,
            'harga_listrik_per_kwh' => $hargaListrik,
        ]);
    }

    public function used(): static
    {
        return $this->state(fn () => ['status' => GarageStatus::USED]);
    }
}
