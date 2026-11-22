<?php

namespace App\Support;

use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use Psr\Log\LoggerInterface;

/**
 * Jejak audit sewa: pembuatan sewa, perpanjangan, dan permintaan yang ditolak
 * aturan bisnis. Membantu penelusuran saat debugging (diagram B.6).
 * Dipakai RentalController, AdminExtensionController, dan Handler.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RentalAuditLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Mencatat sewa baru sebagai log info.
     *
     * @param  Rental  $sewa  sewa yang baru disimpan
     */
    public function catatPembuatanSewa(Rental $sewa): void
    {
        $this->logger->info('Sewa dibuat', [
            'rental_id' => $sewa->id,
            'nomor_nota' => $sewa->nomor_nota,
            'penyewa_id' => $sewa->penyewa_id,
            'garage_id' => $sewa->garage_id,
            'total_biaya' => $sewa->total_biaya,
        ]);
    }

    /**
     * Mencatat perpanjangan sewa sebagai log info.
     *
     * @param  RentalExtension  $perpanjangan  perpanjangan yang baru disimpan
     */
    public function catatPerpanjangan(RentalExtension $perpanjangan): void
    {
        $this->logger->info('Sewa diperpanjang', [
            'rental_extension_id' => $perpanjangan->id,
            'rental_id' => $perpanjangan->rental_id,
            'nomor_perpanjang' => $perpanjangan->nomor_perpanjang,
            'tambahan_hari' => $perpanjangan->tambahan_hari,
            'biaya_tambahan' => $perpanjangan->biaya_tambahan,
            'diproses_oleh' => $perpanjangan->diproses_oleh,
        ]);
    }

    /**
     * Mencatat permintaan yang ditolak aturan bisnis sebagai log peringatan.
     *
     * @param  string  $kodeKesalahan  kode kesalahan dari exception aplikasi
     * @param  array<string, mixed>  $konteks  data pendukung untuk penelusuran
     */
    public function catatKegagalan(string $kodeKesalahan, array $konteks): void
    {
        $this->logger->warning("Aturan bisnis menolak permintaan: {$kodeKesalahan}", $konteks);
    }
}
