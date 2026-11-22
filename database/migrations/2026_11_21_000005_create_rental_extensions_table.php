<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Histori perpanjangan sewa. Dihitung dengan tarif harian.
     */
    public function up(): void
    {
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals');
            $table->string('nomor_perpanjang', 30)->unique();

            $table->date('tanggal_selesai_lama');
            $table->date('tanggal_selesai_baru');
            $table->integer('tambahan_hari');

            $table->decimal('biaya_tambahan', 12, 2);

            $table->foreignId('diproses_oleh')->constrained('users');

            $table->timestamp('created_at')->nullable();

            $table->index('rental_id', 'idx_extension_rental');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_extensions');
    }
};
