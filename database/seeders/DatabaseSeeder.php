<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal untuk demo lokal: satu admin, garasi contoh, serta penyewa
     * dan sewa contoh untuk laporan.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            GarageSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
