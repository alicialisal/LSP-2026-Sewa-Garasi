<?php

use App\Support\NomorPlatFormatter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomor plat disimpan seragam dengan format Indonesia ("DD 1234 AB"). Plat
 * lama yang tersimpan tanpa spasi atau dengan huruf kecil diseragamkan; nilai
 * yang strukturnya tidak sesuai dibiarkan. Panjang kolom (15) tidak berubah.
 */
return new class () extends Migration {
    public function up(): void
    {
        $formatter = new NomorPlatFormatter();

        DB::table('vehicles')->whereNotNull('nomor_plat')->select(['id', 'nomor_plat'])->orderBy('id')
            ->each(function (object $baris) use ($formatter) {
                $plat = $formatter->normalisasi($baris->nomor_plat);

                if ($plat !== null && $plat !== $baris->nomor_plat) {
                    DB::table('vehicles')->where('id', $baris->id)->update(['nomor_plat' => $plat]);
                }
            });
    }

    public function down(): void
    {
        // Spasi tidak dikembalikan: format lama (tanpa spasi) masih dapat
        // dibentuk ulang dari format baru bila diperlukan.
    }
};
