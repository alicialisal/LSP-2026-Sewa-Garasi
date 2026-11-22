<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Kendaraan. nomor_plat unik di seluruh sistem; NULL untuk sepeda
     * (indeks unik MySQL mengizinkan banyak NULL).
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penyewa_id')->constrained('penyewa');
            $table->enum('type', ['SEPEDA', 'SEPEDA_MOTOR', 'MOBIL', 'MOBIL_LISTRIK']);
            $table->string('nomor_plat', 15)->nullable()->unique();
            $table->string('merek', 100);
            $table->string('tipe', 100);
            $table->decimal('kapasitas_baterai_kwh', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
