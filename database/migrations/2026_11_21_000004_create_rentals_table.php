<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Sewa. Tanggal selesai inklusif. jumlah_hari: harian = jumlah input,
     * bulanan = 0. Tumpang-tindih tanggal ditegakkan di aplikasi (transaksi
     * dengan lockForUpdate), bukan constraint.
     */
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_nota', 30)->unique();
            $table->foreignId('penyewa_id')->constrained('penyewa');
            $table->char('garage_id', 4);
            $table->foreign('garage_id')->references('id')->on('garages');
            $table->foreignId('vehicle_id')->constrained('vehicles');

            $table->enum('tipe_sewa', ['HARIAN', 'BULANAN']);
            $table->integer('jumlah_hari');

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            $table->decimal('biaya_sewa', 12, 2);

            $table->decimal('sisa_baterai_snapshot', 8, 2)->nullable();
            $table->decimal('jumlah_listrik_kwh', 8, 2)->default(0);
            $table->decimal('biaya_listrik', 12, 2)->default(0);
            $table->decimal('total_biaya', 12, 2);

            $table->enum('status', ['AKTIF', 'SELESAI'])->default('AKTIF');

            $table->timestamps();

            $table->index(['garage_id', 'tanggal_mulai', 'tanggal_selesai'], 'idx_rentals_ketersediaan');
            $table->index(['penyewa_id', 'status'], 'idx_rentals_penyewa_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
