<?php

namespace App\Support;

use App\Repositories\RentalRepository;
use Carbon\CarbonInterface;
use LogicException;

/**
 * Pembangkit nomor dokumen (asumsi nomor 22): SEW-yyyymmdd-NNNN untuk nota
 * dan PJG-yyyymmdd-NNNN untuk perpanjangan. Nomor urut diambil dengan
 * MAX + 1 per hari. Format dikumpulkan di sini agar mudah diganti.
 * Bergantung pada RentalRepository; dipakai RentalService dan ExtensionService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class DocumentNumberGenerator
{
    private const PREFIX_NOTA = 'SEW';

    private const PREFIX_PERPANJANG = 'PJG';

    private const DIGIT_URUT = 4;

    public function __construct(private readonly RentalRepository $rentalRepo)
    {
    }

    /**
     * Nomor nota sewa berikutnya untuk tanggal yang diberikan.
     *
     * @param  CarbonInterface  $tanggal  tanggal nota, menentukan bagian yyyymmdd
     * @return string nomor nota dengan format SEW-yyyymmdd-NNNN
     *
     * @throws LogicException jika nomor urut harian sudah habis
     */
    public function nomorNota(CarbonInterface $tanggal): string
    {
        $prefix = $this->prefixHarian(self::PREFIX_NOTA, $tanggal);

        return $prefix.$this->urutBerikutnya($this->rentalRepo->nomorNotaTerakhir($prefix));
    }

    /**
     * Nomor perpanjangan berikutnya untuk tanggal yang diberikan.
     *
     * @param  CarbonInterface  $tanggal  tanggal perpanjangan, menentukan bagian yyyymmdd
     * @return string nomor perpanjangan dengan format PJG-yyyymmdd-NNNN
     *
     * @throws LogicException jika nomor urut harian sudah habis
     */
    public function nomorPerpanjang(CarbonInterface $tanggal): string
    {
        $prefix = $this->prefixHarian(self::PREFIX_PERPANJANG, $tanggal);

        return $prefix.$this->urutBerikutnya($this->rentalRepo->nomorPerpanjangTerakhir($prefix));
    }

    private function prefixHarian(string $kode, CarbonInterface $tanggal): string
    {
        return $kode.'-'.$tanggal->format('Ymd').'-';
    }

    /**
     * @throws LogicException jika nomor urut harian sudah habis
     */
    private function urutBerikutnya(?string $nomorTerakhir): string
    {
        $terakhir = $nomorTerakhir === null ? 0 : (int) substr($nomorTerakhir, -self::DIGIT_URUT);
        $berikutnya = $terakhir + 1;

        if ($berikutnya > (10 ** self::DIGIT_URUT) - 1) {
            throw new LogicException('Nomor urut dokumen hari ini sudah habis.');
        }

        return str_pad((string) $berikutnya, self::DIGIT_URUT, '0', STR_PAD_LEFT);
    }
}
