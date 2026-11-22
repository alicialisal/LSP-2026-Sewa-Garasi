<?php

namespace Tests\Unit\Support;

use App\Domain\Enums\GarageType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\ElectricCarGarage;
use App\Domain\Models\Garage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Support\ReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pengelompokan laporan (asumsi nomor 18, dengan keputusan pengguna):
 * sewa dihitung pada tanggal mulainya, sedangkan biaya perpanjangan dihitung
 * pada tanggal perpanjangan diproses.
 */
class ReportAggregatorTest extends TestCase
{
    private ReportAggregator $agregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agregator = new ReportAggregator();
    }

    private function sewa(string $mulai, float $total, ?Garage $garasi = null, ?Penyewa $penyewa = null): Rental
    {
        $sewa = new Rental();
        $sewa->setRawAttributes(['tanggal_mulai' => $mulai, 'total_biaya' => $total], true);

        if ($garasi !== null) {
            $sewa->setRelation('garage', $garasi);
        }

        if ($penyewa !== null) {
            $sewa->setRelation('penyewa', $penyewa);
        }

        return $sewa;
    }

    private function perpanjangan(string $diproses, float $biaya, ?Rental $sewa = null): RentalExtension
    {
        $perpanjangan = new RentalExtension();
        $perpanjangan->setRawAttributes([
            'created_at' => CarbonImmutable::parse($diproses),
            'biaya_tambahan' => $biaya,
        ], true);

        if ($sewa !== null) {
            $perpanjangan->setRelation('rental', $sewa);
        }

        return $perpanjangan;
    }

    private function penyewa(int $id, string $nama): Penyewa
    {
        $penyewa = new Penyewa();
        $penyewa->setRawAttributes(['id' => $id, 'nama_lengkap' => $nama], true);

        return $penyewa;
    }

    /**
     * UT-RA-01 - Dua sewa Januari (Rp100.000) dan satu sewa Maret (Rp50.000).
     */
    public function test_kelompok_per_bulan(): void
    {
        $hasil = $this->agregator->kelompokkanPerBulan(new Collection([
            $this->sewa('2026-01-05', 60000),
            $this->sewa('2026-01-20', 40000),
            $this->sewa('2026-03-10', 50000),
        ]), new Collection(), 2026);

        $this->assertCount(12, $hasil);
        $this->assertSame(['bulan' => 1, 'jumlah_sewa' => 2, 'pendapatan' => 100000.0], $hasil[0]);
        $this->assertSame(['bulan' => 2, 'jumlah_sewa' => 0, 'pendapatan' => 0.0], $hasil[1]);
        $this->assertSame(['bulan' => 3, 'jumlah_sewa' => 1, 'pendapatan' => 50000.0], $hasil[2]);
    }

    /**
     * UT-RA-02 - Koleksi kosong menghasilkan 12 baris bernilai nol.
     */
    public function test_kelompok_per_bulan_koleksi_kosong(): void
    {
        $hasil = $this->agregator->kelompokkanPerBulan(new Collection(), new Collection(), 2026);

        $this->assertCount(12, $hasil);
        foreach ($hasil as $indeks => $baris) {
            $this->assertSame(['bulan' => $indeks + 1, 'jumlah_sewa' => 0, 'pendapatan' => 0.0], $baris);
        }
    }

    /**
     * UT-RA-03 - Sewa dan perpanjangan tahun 2025 di dalam koleksi tidak dihitung.
     */
    public function test_data_tahun_lain_tidak_dihitung(): void
    {
        $hasil = $this->agregator->kelompokkanPerBulan(
            new Collection([$this->sewa('2025-01-05', 99000), $this->sewa('2026-01-05', 10000)]),
            new Collection([$this->perpanjangan('2025-01-10 10:00', 5000)]),
            2026,
        );

        $this->assertSame(1, $hasil[0]['jumlah_sewa']);
        $this->assertSame(10000.0, $hasil[0]['pendapatan']);
    }

    /**
     * Keputusan pengguna: sewa mulai Januari yang diperpanjang pada Februari
     * menambah pendapatan Februari, bukan Januari; jumlah sewa Februari tetap 0.
     */
    public function test_biaya_perpanjangan_masuk_bulan_perpanjangan_diproses(): void
    {
        $hasil = $this->agregator->kelompokkanPerBulan(
            new Collection([$this->sewa('2026-01-28', 75000)]),
            new Collection([$this->perpanjangan('2026-02-02 14:30', 50000)]),
            2026,
        );

        $this->assertSame(['bulan' => 1, 'jumlah_sewa' => 1, 'pendapatan' => 75000.0], $hasil[0]);
        $this->assertSame(['bulan' => 2, 'jumlah_sewa' => 0, 'pendapatan' => 50000.0], $hasil[1]);
    }

    /**
     * UT-RA-04 - Februari 2026 memiliki 28 baris.
     */
    public function test_kelompok_per_hari_februari_2026(): void
    {
        $hasil = $this->agregator->kelompokkanPerHari(new Collection(), new Collection(), 2026, 2);

        $this->assertCount(28, $hasil);
        $this->assertSame(1, $hasil[0]['hari']);
        $this->assertSame(28, $hasil[27]['hari']);
    }

    /**
     * UT-RA-05 - Februari 2028 (kabisat) memiliki 29 baris.
     */
    public function test_kelompok_per_hari_februari_2028(): void
    {
        $this->assertCount(29, $this->agregator->kelompokkanPerHari(new Collection(), new Collection(), 2028, 2));
    }

    public function test_kelompok_per_hari(): void
    {
        $hasil = $this->agregator->kelompokkanPerHari(
            new Collection([
                $this->sewa('2026-02-10', 60000),
                $this->sewa('2026-02-10', 40000),
                $this->sewa('2026-03-10', 99000),
            ]),
            new Collection([
                $this->perpanjangan('2026-02-11 09:00', 25000),
                $this->perpanjangan('2026-03-01 09:00', 99000),
            ]),
            2026,
            2,
        );

        $this->assertSame(['hari' => 10, 'jumlah_sewa' => 2, 'pendapatan' => 100000.0], $hasil[9]);
        $this->assertSame(['hari' => 11, 'jumlah_sewa' => 0, 'pendapatan' => 25000.0], $hasil[10]);
        $this->assertSame(['hari' => 12, 'jumlah_sewa' => 0, 'pendapatan' => 0.0], $hasil[11]);
    }

    /**
     * UT-RA-06 - Penjumlahan pendapatan.
     */
    public function test_jumlahkan_pendapatan(): void
    {
        $this->assertSame(150000.0, $this->agregator->jumlahkanPendapatan([100000, 0, 50000]));
        $this->assertSame(0.0, $this->agregator->jumlahkanPendapatan([]));
    }

    /**
     * Perpanjangan dihitung pada tipe garasi sewanya, walaupun sewanya dimulai di luar rentang.
     */
    public function test_kelompok_per_tipe_garasi(): void
    {
        $sewaLama = $this->sewa('2026-10-30', 25000, new ElectricCarGarage());

        $hasil = $this->agregator->kelompokkanPerTipeGarasi(
            new Collection([
                $this->sewa('2026-11-21', 75000, new CarGarage()),
                $this->sewa('2026-11-22', 25000, new CarGarage()),
            ]),
            new Collection([$this->perpanjangan('2026-11-02 10:00', 60000, $sewaLama)]),
        );

        $this->assertSame([
            ['tipe' => GarageType::BIKE, 'jumlah_sewa' => 0, 'pendapatan' => 0.0],
            ['tipe' => GarageType::CAR, 'jumlah_sewa' => 2, 'pendapatan' => 100000.0],
            ['tipe' => GarageType::ELECTRIC, 'jumlah_sewa' => 0, 'pendapatan' => 60000.0],
        ], $hasil);
    }

    /**
     * Penyewa yang hanya memperpanjang di dalam rentang tetap muncul dengan jumlah sewa 0.
     */
    public function test_kelompok_per_penyewa(): void
    {
        $budi = $this->penyewa(1, 'Budi');
        $sari = $this->penyewa(2, 'Sari');
        $andi = $this->penyewa(3, 'Andi');

        $hasil = $this->agregator->kelompokkanPerPenyewa(
            new Collection([
                $this->sewa('2026-11-21', 50000, penyewa: $budi),
                $this->sewa('2026-11-22', 30000, penyewa: $budi),
                $this->sewa('2026-11-22', 200000, penyewa: $sari),
            ]),
            new Collection([
                $this->perpanjangan('2026-11-05 10:00', 40000, $this->sewa('2026-10-30', 1, penyewa: $andi)),
                $this->perpanjangan('2026-11-23 10:00', 10000, $this->sewa('2026-11-21', 1, penyewa: $budi)),
            ]),
        );

        $this->assertSame([
            ['penyewa_id' => 2, 'nama_lengkap' => 'Sari', 'jumlah_sewa' => 1, 'pendapatan' => 200000.0],
            ['penyewa_id' => 1, 'nama_lengkap' => 'Budi', 'jumlah_sewa' => 2, 'pendapatan' => 90000.0],
            ['penyewa_id' => 3, 'nama_lengkap' => 'Andi', 'jumlah_sewa' => 0, 'pendapatan' => 40000.0],
        ], $hasil);
    }

    /**
     * Sewa lengkap untuk rincian: nomor nota, garasi, penyewa, dan pembayaran.
     *
     * @param  array<int, Payment>  $pembayaran
     */
    private function sewaLengkap(string $nota, string $mulai, float $total, Penyewa $penyewa, array $pembayaran = []): Rental
    {
        $sewa = $this->sewa($mulai, $total, penyewa: $penyewa);
        $sewa->setRawAttributes(array_merge($sewa->getAttributes(), [
            'nomor_nota' => $nota,
            'garage_id' => 'C001',
            'penyewa_id' => $penyewa->id,
        ]), true);
        $sewa->setRelation('payments', new Collection($pembayaran));

        return $sewa;
    }

    private function pembayaran(string $path, ?int $extensionId = null): Payment
    {
        $pembayaran = new Payment();
        $pembayaran->setRawAttributes(['path_bukti_bayar' => $path, 'rental_extension_id' => $extensionId], true);

        return $pembayaran;
    }

    /**
     * Rincian per bulan: sewa pada tanggal mulainya, perpanjangan pada tanggal
     * diprosesnya; bukti sewa diambil dari pembayaran awal, bukan pembayaran
     * perpanjangan.
     */
    public function test_rincian_dikelompokkan_dan_berisi_jenis_nomor_jumlah_bukti(): void
    {
        $budi = $this->penyewa(1, 'Budi');
        $sewaJanuari = $this->sewaLengkap('SEW-20260105-0001', '2026-01-05', 100000, $budi, [
            $this->pembayaran('bukti-bayar/2026/02/pjg.png', 9),
            $this->pembayaran('bukti-bayar/2026/01/awal.png'),
        ]);

        $perpanjangan = $this->perpanjangan('2026-02-03 10:00', 20000, $sewaJanuari);
        $perpanjangan->setRawAttributes(array_merge($perpanjangan->getAttributes(), ['nomor_perpanjang' => 'PJG-20260203-0001']), true);
        $perpanjangan->setRelation('payment', $this->pembayaran('bukti-bayar/2026/02/pjg.png', 9));

        $hasil = $this->agregator->rincian(
            new Collection([$sewaJanuari]),
            new Collection([$perpanjangan]),
            fn ($tanggal) => $tanggal->month,
        );

        $this->assertSame([1, 2], array_keys($hasil));
        $this->assertSame('Sewa baru', $hasil[1][0]['jenis']);
        $this->assertSame('SEW-20260105-0001', $hasil[1][0]['nomor']);
        $this->assertSame('2026-01-05', $hasil[1][0]['tanggal']->toDateString());
        $this->assertSame('Budi', $hasil[1][0]['penyewa']);
        $this->assertSame('C001', $hasil[1][0]['garasi']);
        $this->assertSame(100000.0, $hasil[1][0]['jumlah']);
        $this->assertSame('bukti-bayar/2026/01/awal.png', $hasil[1][0]['bukti']);

        $this->assertSame('Perpanjangan', $hasil[2][0]['jenis']);
        $this->assertSame('PJG-20260203-0001', $hasil[2][0]['nomor']);
        $this->assertSame('2026-02-03', $hasil[2][0]['tanggal']->toDateString());
        $this->assertSame(20000.0, $hasil[2][0]['jumlah']);
        $this->assertSame('bukti-bayar/2026/02/pjg.png', $hasil[2][0]['bukti']);
    }

    public function test_rincian_membuang_transaksi_di_luar_periode_dan_mengurutkan_menurut_tanggal(): void
    {
        $budi = $this->penyewa(1, 'Budi');

        $hasil = $this->agregator->rincian(
            new Collection([
                $this->sewaLengkap('SEW-20260120-0001', '2026-01-20', 50000, $budi),
                $this->sewaLengkap('SEW-20260105-0001', '2026-01-05', 50000, $budi),
                $this->sewaLengkap('SEW-20251231-0001', '2025-12-31', 50000, $budi),
            ]),
            new Collection(),
            fn ($tanggal) => $tanggal->year === 2026 ? $tanggal->month : null,
        );

        $this->assertSame([1], array_keys($hasil));
        $this->assertSame(['SEW-20260105-0001', 'SEW-20260120-0001'], array_column($hasil[1], 'nomor'));
    }

    public function test_rincian_bukti_null_bila_pembayaran_tidak_ada(): void
    {
        $budi = $this->penyewa(1, 'Budi');
        $sewa = $this->sewaLengkap('SEW-20260105-0001', '2026-01-05', 50000, $budi);
        $perpanjangan = $this->perpanjangan('2026-01-07 10:00', 10000, $sewa);
        $perpanjangan->setRelation('payment', null);

        $hasil = $this->agregator->rincian(new Collection([$sewa]), new Collection([$perpanjangan]), fn () => 'semua');

        $this->assertNull($hasil['semua'][0]['bukti']);
        $this->assertNull($hasil['semua'][1]['bukti']);
    }

    public function test_kunci_rincian_menerima_sewa_untuk_tipe_garasi_atau_penyewa(): void
    {
        $budi = $this->penyewa(7, 'Budi');
        $sewa = $this->sewaLengkap('SEW-20260105-0001', '2026-01-05', 50000, $budi);

        $hasil = $this->agregator->rincian(new Collection([$sewa]), new Collection(), fn ($tanggal, Rental $s) => $s->penyewa_id);

        $this->assertSame([7], array_keys($hasil));
    }
}
