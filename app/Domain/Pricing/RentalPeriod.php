<?php

namespace App\Domain\Pricing;

use App\Domain\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Objek nilai periode sewa. Tidak berubah setelah dibuat.
 *
 * jumlahHari adalah lama sewa sebenarnya, sedangkan jumlahHariTersimpan()
 * adalah nilai untuk kolom rentals.jumlah_hari.
 *
 * dikonversiOtomatis menandai sewa harian yang diubah menjadi bulanan;
 * melebihiBatas menandai isian yang melebihi jumlah hari sebulan.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
final readonly class RentalPeriod
{
    public CarbonImmutable $tanggalMulai;

    public CarbonImmutable $tanggalSelesai;

    public function __construct(
        public RentalType $tipeSewa,
        public int $jumlahHari,
        CarbonInterface $tanggalMulai,
        CarbonInterface $tanggalSelesai,
        public bool $dikonversiOtomatis = false,
        public bool $melebihiBatas = false,
    ) {
        $this->tanggalMulai = CarbonImmutable::instance($tanggalMulai)->startOfDay();
        $this->tanggalSelesai = CarbonImmutable::instance($tanggalSelesai)->startOfDay();
    }

    /**
     * Nilai untuk kolom rentals.jumlah_hari: jumlah hari untuk sewa harian,
     * 0 untuk sewa bulanan.
     *
     * @return int jumlah hari yang disimpan
     */
    public function jumlahHariTersimpan(): int
    {
        return $this->tipeSewa === RentalType::BULANAN ? 0 : $this->jumlahHari;
    }

    /**
     * Notifikasi untuk penyewa. Hanya terisi bila isian melebihi satu bulan;
     * string kosong bila tidak ada notifikasi.
     *
     * @return string pesan notifikasi atau string kosong
     */
    public function pesanNotifikasi(): string
    {
        if (! $this->melebihiBatas) {
            return '';
        }

        return "Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan ({$this->jumlahHari} hari).";
    }

    /**
     * Apakah tanggal berada dalam periode. Tanggal selesai bersifat inklusif.
     *
     * @param  CarbonInterface  $tanggal  tanggal yang diperiksa; jam diabaikan
     * @return bool true jika tanggal berada dalam periode
     */
    public function mencakup(CarbonInterface $tanggal): bool
    {
        $hari = CarbonImmutable::instance($tanggal)->startOfDay();

        return $hari->greaterThanOrEqualTo($this->tanggalMulai)
            && $hari->lessThanOrEqualTo($this->tanggalSelesai);
    }
}
