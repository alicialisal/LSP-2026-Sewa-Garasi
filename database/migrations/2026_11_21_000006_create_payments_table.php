<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Pembayaran sewa awal dan perpanjangan dalam satu tabel. rental_id harus
     * sama dengan rental_extensions.rental_id bila rental_extension_id diisi;
     * hal ini diperiksa di ExtensionService, bukan constraint.
     *
     * Relasi ke perpanjangan satu ke satu (erd.dbml: "-"), sehingga
     * rental_extension_id diberi UNIQUE (NULL boleh berulang di MySQL).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals');
            $table->foreignId('rental_extension_id')->nullable()->constrained('rental_extensions');

            $table->decimal('jumlah', 12, 2);
            $table->string('path_bukti_bayar', 255);

            $table->foreignId('diunggah_oleh')->constrained('users');
            $table->timestamp('diunggah_pada');

            $table->index('rental_id', 'idx_payment_rental');
            $table->unique('rental_extension_id', 'idx_payment_extension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
