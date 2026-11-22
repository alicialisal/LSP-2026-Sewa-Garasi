<?php

namespace App\Services;

use App\Domain\Enums\GarageType;
use App\Domain\Models\Rental;
use App\Repositories\RentalRepository;
use App\Support\ReportAggregator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Laporan sewa untuk admin. Pendapatan = total biaya sewa + biaya
 * perpanjangan (asumsi nomor 18). Sewa dihitung pada tanggal mulainya;
 * biaya perpanjangan pada tanggal perpanjangan diproses (keputusan pengguna).
 * Bergantung pada RentalRepository dan ReportAggregator; dipakai
 * AdminReportController.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class ReportService
{
    public function __construct(
        private readonly RentalRepository $rentalRepo,
        private readonly ReportAggregator $agregator,
    ) {
    }

    /**
     * Laporan per bulan dalam satu tahun: 12 baris. Setiap laporan menyertakan
     * kunci `rincian`, yaitu daftar sewa baru dan perpanjangan pada baris itu
     * beserta bukti bayarnya, diambil dari data yang sama tanpa query tambahan.
     *
     * @param  int  $tahun  tahun laporan
     * @return Collection<int, array{bulan: int, jumlah_sewa: int, pendapatan: float, rincian: array}> baris per bulan, pendapatan dalam rupiah
     */
    public function perBulan(int $tahun): Collection
    {
        $sewa = $this->rentalRepo->rekapPerBulan($tahun);
        $perpanjangan = $this->rentalRepo->perpanjanganDalamRentang(
            CarbonImmutable::create($tahun, 1, 1),
            CarbonImmutable::create($tahun, 12, 31),
        );

        return $this->denganRincian(
            $this->agregator->kelompokkanPerBulan($sewa, $perpanjangan, $tahun),
            $this->agregator->rincian($sewa, $perpanjangan, fn (CarbonInterface $t) => $t->year === $tahun ? $t->month : null),
            'bulan',
        );
    }

    /**
     * Laporan per hari dalam satu bulan: satu baris per hari.
     *
     * @param  int  $tahun  tahun laporan
     * @param  int  $bulan  bulan laporan, 1 sampai 12
     * @return Collection<int, array{hari: int, jumlah_sewa: int, pendapatan: float, rincian: array}> baris per hari, pendapatan dalam rupiah
     */
    public function perHari(int $tahun, int $bulan): Collection
    {
        $awal = CarbonImmutable::create($tahun, $bulan, 1);
        $sewa = $this->rentalRepo->rekapPerHari($tahun, $bulan);
        $perpanjangan = $this->rentalRepo->perpanjanganDalamRentang($awal, $awal->endOfMonth()->startOfDay());

        return $this->denganRincian(
            $this->agregator->kelompokkanPerHari($sewa, $perpanjangan, $tahun, $bulan),
            $this->agregator->rincian(
                $sewa,
                $perpanjangan,
                fn (CarbonInterface $t) => $t->year === $tahun && $t->month === $bulan ? $t->day : null,
            ),
            'hari',
        );
    }

    /**
     * Laporan per tipe garasi dalam rentang tanggal: tiga baris.
     *
     * @param  CarbonInterface  $dari  tanggal awal rentang
     * @param  CarbonInterface  $sampai  tanggal akhir rentang (inklusif)
     * @return Collection<int, array{tipe: GarageType, jumlah_sewa: int, pendapatan: float, rincian: array}> baris per tipe garasi, pendapatan dalam rupiah
     */
    public function perTipeGarasi(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        $sewa = $this->rentalRepo->rekapPerTipeGarasi($dari, $sampai);
        $perpanjangan = $this->rentalRepo->perpanjanganDalamRentang($dari, $sampai);

        return $this->denganRincian(
            $this->agregator->kelompokkanPerTipeGarasi($sewa, $perpanjangan),
            $this->agregator->rincian($sewa, $perpanjangan, fn (CarbonInterface $t, Rental $s) => $s->garage->type->value),
            fn (array $baris) => $baris['tipe']->value,
        );
    }

    /**
     * Laporan per penyewa dalam rentang tanggal.
     *
     * @param  CarbonInterface  $dari  tanggal awal rentang
     * @param  CarbonInterface  $sampai  tanggal akhir rentang (inklusif)
     * @return Collection<int, array{penyewa_id: int, nama_lengkap: string, jumlah_sewa: int, pendapatan: float, rincian: array}> baris per penyewa, pendapatan terbesar lebih dulu
     */
    public function perPenyewa(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        $sewa = $this->rentalRepo->rekapPerPenyewa($dari, $sampai);
        $perpanjangan = $this->rentalRepo->perpanjanganDalamRentang($dari, $sampai);

        return $this->denganRincian(
            $this->agregator->kelompokkanPerPenyewa($sewa, $perpanjangan),
            $this->agregator->rincian($sewa, $perpanjangan, fn (CarbonInterface $t, Rental $s) => $s->penyewa_id),
            'penyewa_id',
        );
    }

    /**
     * Menempelkan daftar transaksi ke baris laporan yang kuncinya sama.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<int|string, list<array<string, mixed>>>  $rincian
     * @param  string|callable(array<string, mixed>): (int|string)  $kunci  kolom baris, atau fungsi pembentuk kunci
     * @return Collection<int, array<string, mixed>>
     */
    private function denganRincian(array $baris, array $rincian, string|callable $kunci): Collection
    {
        return collect($baris)->map(function (array $satuBaris) use ($rincian, $kunci) {
            $k = is_string($kunci) ? $satuBaris[$kunci] : $kunci($satuBaris);
            $satuBaris['rincian'] = $rincian[$k] ?? [];

            return $satuBaris;
        });
    }

    /**
     * Jumlah seluruh pendapatan pada baris laporan.
     *
     * @param  Collection<int, array{pendapatan: float}>  $baris  baris hasil salah satu laporan
     * @return float total pendapatan dalam rupiah, dibulatkan dua angka desimal
     */
    public function totalPendapatan(Collection $baris): float
    {
        return $this->agregator->jumlahkanPendapatan($baris->pluck('pendapatan')->all());
    }
}
