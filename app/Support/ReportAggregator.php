<?php

namespace App\Support;

use App\Domain\Enums\GarageType;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pengelompokan data laporan sewa. Pendapatan = total biaya sewa +
 * biaya perpanjangan (asumsi nomor 18), dengan keputusan pengguna:
 * - total biaya sewa dihitung pada tanggal mulai sewa (dan jumlah sewa ikut di sana);
 * - biaya perpanjangan dihitung pada tanggal perpanjangan diproses.
 *
 * Bagian pengolahan larik dan koleksi pada rancangan; tanpa akses basis data.
 * Pemformatan rupiah dilakukan di tampilan. Dipakai ReportService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class ReportAggregator
{
    private const BULAN_PER_TAHUN = 12;

    /**
     * Satu baris per bulan 1-12 (termasuk bulan tanpa data). Data di luar
     * tahun yang diminta tidak dihitung.
     *
     * @param  Collection<int, Rental>  $sewa  sewa yang akan dihitung
     * @param  Collection<int, RentalExtension>  $perpanjangan  perpanjangan yang akan dihitung
     * @param  int  $tahun  tahun laporan, misalnya 2026
     * @return array<int, array{bulan: int, jumlah_sewa: int, pendapatan: float}> dua belas baris, pendapatan dalam rupiah
     */
    public function kelompokkanPerBulan(Collection $sewa, Collection $perpanjangan, int $tahun): array
    {
        $baris = [];

        for ($bulan = 1; $bulan <= self::BULAN_PER_TAHUN; $bulan++) {
            $baris[] = ['bulan' => $bulan, 'jumlah_sewa' => 0, 'pendapatan' => 0.0];
        }

        $indeks = fn (CarbonInterface $tanggal) => $tanggal->year === $tahun ? $tanggal->month - 1 : null;

        return $this->isi($baris, $sewa, $perpanjangan, $indeks);
    }

    /**
     * Satu baris per hari dalam bulan (28 sampai 31 baris).
     *
     * @param  Collection<int, Rental>  $sewa  sewa yang akan dihitung
     * @param  Collection<int, RentalExtension>  $perpanjangan  perpanjangan yang akan dihitung
     * @param  int  $tahun  tahun laporan
     * @param  int  $bulan  bulan laporan, 1 sampai 12
     * @return array<int, array{hari: int, jumlah_sewa: int, pendapatan: float}> satu baris per hari, pendapatan dalam rupiah
     */
    public function kelompokkanPerHari(Collection $sewa, Collection $perpanjangan, int $tahun, int $bulan): array
    {
        $jumlahHari = CarbonImmutable::create($tahun, $bulan, 1)->daysInMonth;
        $baris = [];

        for ($hari = 1; $hari <= $jumlahHari; $hari++) {
            $baris[] = ['hari' => $hari, 'jumlah_sewa' => 0, 'pendapatan' => 0.0];
        }

        $indeks = fn (CarbonInterface $tanggal) => $tanggal->year === $tahun && $tanggal->month === $bulan
            ? $tanggal->day - 1
            : null;

        return $this->isi($baris, $sewa, $perpanjangan, $indeks);
    }

    /**
     * Satu baris per tipe garasi (BIKE, CAR, ELECTRIC), termasuk yang tanpa data.
     * Perpanjangan dihitung pada tipe garasi sewanya.
     *
     * @param  Collection<int, Rental>  $sewa  relasi garage sudah dimuat
     * @param  Collection<int, RentalExtension>  $perpanjangan  relasi rental.garage sudah dimuat
     * @return array<int, array{tipe: GarageType, jumlah_sewa: int, pendapatan: float}>
     */
    public function kelompokkanPerTipeGarasi(Collection $sewa, Collection $perpanjangan): array
    {
        $baris = [];

        foreach (GarageType::cases() as $tipe) {
            $baris[$tipe->value] = ['tipe' => $tipe, 'jumlah_sewa' => 0, 'pendapatan' => 0.0];
        }

        foreach ($sewa as $satuSewa) {
            $kunci = $satuSewa->garage->type->value;
            $baris[$kunci]['jumlah_sewa']++;
            $baris[$kunci]['pendapatan'] += $satuSewa->total_biaya;
        }

        foreach ($perpanjangan as $satuPerpanjangan) {
            $baris[$satuPerpanjangan->rental->garage->type->value]['pendapatan'] += $satuPerpanjangan->biaya_tambahan;
        }

        return $this->bulatkan(array_values($baris));
    }

    /**
     * Satu baris per penyewa yang memiliki sewa atau perpanjangan pada
     * periode itu, diurutkan dari pendapatan terbesar, lalu menurut nama.
     *
     * @param  Collection<int, Rental>  $sewa  relasi penyewa sudah dimuat
     * @param  Collection<int, RentalExtension>  $perpanjangan  relasi rental.penyewa sudah dimuat
     * @return array<int, array{penyewa_id: int, nama_lengkap: string, jumlah_sewa: int, pendapatan: float}>
     */
    public function kelompokkanPerPenyewa(Collection $sewa, Collection $perpanjangan): array
    {
        $baris = [];

        $barisPenyewa = function ($penyewa) use (&$baris) {
            $baris[$penyewa->id] ??= [
                'penyewa_id' => $penyewa->id,
                'nama_lengkap' => $penyewa->nama_lengkap,
                'jumlah_sewa' => 0,
                'pendapatan' => 0.0,
            ];

            return $penyewa->id;
        };

        foreach ($sewa as $satuSewa) {
            $kunci = $barisPenyewa($satuSewa->penyewa);
            $baris[$kunci]['jumlah_sewa']++;
            $baris[$kunci]['pendapatan'] += $satuSewa->total_biaya;
        }

        foreach ($perpanjangan as $satuPerpanjangan) {
            $kunci = $barisPenyewa($satuPerpanjangan->rental->penyewa);
            $baris[$kunci]['pendapatan'] += $satuPerpanjangan->biaya_tambahan;
        }

        $baris = $this->bulatkan(array_values($baris));

        usort($baris, fn (array $a, array $b) => [$b['pendapatan'], $a['nama_lengkap']] <=> [$a['pendapatan'], $b['nama_lengkap']]);

        return $baris;
    }

    /**
     * Rincian transaksi per baris laporan: sewa baru pada tanggal mulainya dan
     * perpanjangan pada tanggal diprosesnya (aturan yang sama dengan
     * pendapatan), diurutkan menurut tanggal lalu nomor.
     *
     * @param  Collection<int, Rental>  $sewa  relasi penyewa dan payments sudah dimuat
     * @param  Collection<int, RentalExtension>  $perpanjangan  relasi rental.penyewa dan payment sudah dimuat
     * @param  callable(CarbonInterface, Rental): (int|string|null)  $kunci  kunci baris, atau null bila di luar periode
     * @return array<int|string, list<array{jenis: string, nomor: string, tanggal: CarbonImmutable, penyewa: string, garasi: string, jumlah: float, bukti: ?string}>>
     */
    public function rincian(Collection $sewa, Collection $perpanjangan, callable $kunci): array
    {
        $hasil = [];

        foreach ($sewa as $satuSewa) {
            $k = $kunci($satuSewa->tanggal_mulai, $satuSewa);

            if ($k !== null) {
                $hasil[$k][] = $this->item(
                    'Sewa baru',
                    $satuSewa->nomor_nota,
                    $satuSewa->tanggal_mulai,
                    $satuSewa,
                    $satuSewa->total_biaya,
                    $satuSewa->pembayaranAwal()?->path_bukti_bayar,
                );
            }
        }

        foreach ($perpanjangan as $satuPerpanjangan) {
            $k = $kunci($satuPerpanjangan->created_at, $satuPerpanjangan->rental);

            if ($k !== null) {
                $hasil[$k][] = $this->item(
                    'Perpanjangan',
                    $satuPerpanjangan->nomor_perpanjang,
                    $satuPerpanjangan->created_at,
                    $satuPerpanjangan->rental,
                    $satuPerpanjangan->biaya_tambahan,
                    $satuPerpanjangan->payment?->path_bukti_bayar,
                );
            }
        }

        foreach ($hasil as &$daftar) {
            usort($daftar, fn (array $a, array $b) => [$a['tanggal']->toDateString(), $a['nomor']] <=> [$b['tanggal']->toDateString(), $b['nomor']]);
        }
        unset($daftar);

        return $hasil;
    }

    /**
     * @return array{jenis: string, nomor: string, tanggal: CarbonImmutable, penyewa: string, garasi: string, jumlah: float, bukti: ?string}
     */
    private function item(string $jenis, ?string $nomor, CarbonInterface $tanggal, Rental $sewa, float $jumlah, ?string $bukti): array
    {
        return [
            'jenis' => $jenis,
            'nomor' => (string) $nomor,
            'tanggal' => CarbonImmutable::instance($tanggal),
            'penyewa' => (string) $sewa->penyewa?->nama_lengkap,
            'garasi' => (string) $sewa->garage_id,
            'jumlah' => round($jumlah, 2),
            'bukti' => $bukti,
        ];
    }

    /**
     * Menjumlahkan daftar pendapatan.
     *
     * @param  array<int, int|float>  $pendapatan  daftar pendapatan dalam rupiah
     * @return float total pendapatan, dibulatkan dua angka desimal
     */
    public function jumlahkanPendapatan(array $pendapatan): float
    {
        return round((float) array_sum($pendapatan), 2);
    }

    /**
     * Mengisi baris waktu: sewa pada tanggal mulainya (jumlah dan total biaya),
     * perpanjangan pada tanggal diprosesnya (biaya tambahan saja).
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @param  Collection<int, Rental>  $sewa  sewa yang akan dihitung
     * @param  Collection<int, RentalExtension>  $perpanjangan  perpanjangan yang akan dihitung
     * @param  callable(CarbonInterface): ?int  $indeks  indeks baris, atau null bila di luar periode
     * @return array<int, array<string, mixed>>
     */
    private function isi(array $baris, Collection $sewa, Collection $perpanjangan, callable $indeks): array
    {
        foreach ($sewa as $satuSewa) {
            $i = $indeks($satuSewa->tanggal_mulai);

            if ($i !== null) {
                $baris[$i]['jumlah_sewa']++;
                $baris[$i]['pendapatan'] += $satuSewa->total_biaya;
            }
        }

        foreach ($perpanjangan as $satuPerpanjangan) {
            $i = $indeks($satuPerpanjangan->created_at);

            if ($i !== null) {
                $baris[$i]['pendapatan'] += $satuPerpanjangan->biaya_tambahan;
            }
        }

        return $this->bulatkan($baris);
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private function bulatkan(array $baris): array
    {
        return array_map(function (array $satuBaris) {
            $satuBaris['pendapatan'] = round($satuBaris['pendapatan'], 2);

            return $satuBaris;
        }, $baris);
    }
}
