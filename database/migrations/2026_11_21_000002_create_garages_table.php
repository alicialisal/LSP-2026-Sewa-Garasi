<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Garasi. ID dibangkitkan dengan MAX(id) + 1 per tipe (S001, C010, E003).
     * Harga sewa bulanan = 25 x harga harian, dihitung di aplikasi.
     */
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->char('id', 4)->primary();
            $table->enum('type', ['BIKE', 'CAR', 'ELECTRIC']);
            $table->decimal('harga_sewa_harian', 12, 2);
            $table->decimal('harga_listrik_per_kwh', 10, 2)->nullable();
            $table->enum('status', ['AVAILABLE', 'USED'])->default('AVAILABLE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
