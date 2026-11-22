<?php

namespace App\Console\Commands;

use App\Services\GarageProvisioningService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Menjalankan sinkronisasi status garasi secara manual (asumsi nomor 25):
 * sewa AKTIF yang sudah lewat menjadi SELESAI dan garasinya kembali AVAILABLE.
 * Memanggil GarageProvisioningService::sinkronkanStatus().
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class SinkronStatusGarasiCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'garasi:sinkron-status';

    /**
     * @var string
     */
    protected $description = 'Menyelesaikan sewa yang sudah lewat dan mengembalikan garasinya ke AVAILABLE';

    /**
     * Menjalankan sinkronisasi untuk hari ini dan menampilkan jumlah sewa yang diselesaikan.
     *
     * @param  GarageProvisioningService  $garasi  service yang menjalankan sinkronisasi
     * @return int kode keluar perintah, selalu SUCCESS
     */
    public function handle(GarageProvisioningService $garasi): int
    {
        $jumlah = $garasi->sinkronkanStatus(CarbonImmutable::today());

        $this->info("{$jumlah} sewa diselesaikan.");

        return self::SUCCESS;
    }
}
