<?php

namespace Database\Factories;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penyewa>
 */
class PenyewaFactory extends Factory
{
    protected $model = Penyewa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nik' => fake()->unique()->numerify('################'),
            'nama_lengkap' => fake()->name(),
            'nomor_telepon' => fake()->numerify('+628##########'),
            'status' => RegistrationStatus::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::APPROVED,
            'verified_at' => now(),
        ]);
    }

    public function rejected(string $alasan = 'Data tidak sesuai'): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::REJECTED,
            'alasan_penolakan' => $alasan,
            'verified_at' => now(),
        ]);
    }
}
