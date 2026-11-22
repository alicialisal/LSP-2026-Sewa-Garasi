<?php

use App\Support\NomorTeleponFormatter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP disimpan seragam sebagai +62 diikuti angka (maksimal 15 karakter).
 * Data lama berawalan 0 atau 62 diseragamkan lebih dulu; nilai yang bukan
 * nomor HP valid dibiarkan apa adanya.
 */
return new class () extends Migration {
    public function up(): void
    {
        $formatter = new NomorTeleponFormatter();

        DB::table('penyewa')->select(['id', 'nomor_telepon'])->orderBy('id')->each(function (object $baris) use ($formatter) {
            $nomor = $formatter->normalisasi($baris->nomor_telepon);

            if ($nomor !== null && $nomor !== $baris->nomor_telepon) {
                DB::table('penyewa')->where('id', $baris->id)->update(['nomor_telepon' => $nomor]);
            }
        });

        Schema::table('penyewa', function (Blueprint $table) {
            $table->string('nomor_telepon', 15)->change();
        });
    }

    public function down(): void
    {
        Schema::table('penyewa', function (Blueprint $table) {
            $table->string('nomor_telepon', 20)->change();
        });
    }
};
