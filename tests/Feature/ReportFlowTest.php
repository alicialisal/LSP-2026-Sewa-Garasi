<?php

namespace Tests\Feature;

use App\Domain\Enums\GarageType;
use App\Domain\Models\Garage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan sewa dengan repository Eloquent dan MySQL sungguhan. Sewa dikelompokkan
 * menurut tanggal mulai; pendapatan = total biaya + biaya perpanjangan.
 */
class ReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReportService
    {
        return $this->app->make(ReportService::class);
    }

    private function pembayaran(Rental $sewa, string $path, ?RentalExtension $perpanjangan = null): void
    {
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id,
            'rental_extension_id' => $perpanjangan?->id,
            'jumlah' => $perpanjangan?->biaya_tambahan ?? $sewa->total_biaya,
            'path_bukti_bayar' => $path,
            'diunggah_oleh' => $sewa->penyewa->user_id,
            'diunggah_pada' => '2026-01-05 10:00:00',
        ]);
    }

    private function perpanjangan(Rental $sewa, string $nomor, float $biaya, string $diproses): RentalExtension
    {
        return RentalExtension::query()->forceCreate([
            'rental_id' => $sewa->id,
            'nomor_perpanjang' => $nomor,
            'tanggal_selesai_lama' => '2026-01-07',
            'tanggal_selesai_baru' => '2026-01-09',
            'tambahan_hari' => 2,
            'biaya_tambahan' => $biaya,
            'diproses_oleh' => User::factory()->admin()->create()->id,
            'created_at' => $diproses,
        ]);
    }

    public function test_laporan_per_bulan_dalam_tahun(): void
    {
        $januari = Rental::factory()->periode('2026-01-05', '2026-01-07')->create(['total_biaya' => 60000, 'nomor_nota' => 'SEW-20260105-0001']);
        Rental::factory()->periode('2026-01-20', '2026-01-22')->create(['total_biaya' => 40000, 'nomor_nota' => 'SEW-20260120-0001']);
        Rental::factory()->periode('2026-03-10', '2026-03-12')->create(['total_biaya' => 50000, 'nomor_nota' => 'SEW-20260310-0001']);
        Rental::factory()->periode('2025-12-31', '2026-01-02')->create(['total_biaya' => 99000, 'nomor_nota' => 'SEW-20251231-0001']);
        // Sewa Januari diperpanjang pada 3 Februari: biayanya masuk Februari.
        $this->perpanjangan($januari, 'PJG-20260203-0001', 30000, '2026-02-03 10:00:00');

        $hasil = $this->service()->perBulan(2026);

        $this->assertCount(12, $hasil);
        $this->assertSame(['bulan' => 1, 'jumlah_sewa' => 2, 'pendapatan' => 100000.0], Arr::except($hasil[0], 'rincian'));
        $this->assertSame(['bulan' => 2, 'jumlah_sewa' => 0, 'pendapatan' => 30000.0], Arr::except($hasil[1], 'rincian'));
        $this->assertSame(['bulan' => 3, 'jumlah_sewa' => 1, 'pendapatan' => 50000.0], Arr::except($hasil[2], 'rincian'));
        $this->assertSame(180000.0, $this->service()->totalPendapatan($hasil));
    }

    public function test_rincian_per_bulan_memuat_sewa_dan_perpanjangan_beserta_bukti_tanpa_n_plus_1(): void
    {
        $budi = Penyewa::factory()->approved()->create(['nama_lengkap' => 'Budi']);
        $januari = Rental::factory()->periode('2026-01-05', '2026-01-07')
            ->create(['penyewa_id' => $budi->id, 'total_biaya' => 60000, 'nomor_nota' => 'SEW-20260105-0001']);
        $this->pembayaran($januari, 'bukti-bayar/2026/01/awal.png');
        $perpanjangan = $this->perpanjangan($januari, 'PJG-20260203-0001', 30000, '2026-02-03 10:00:00');
        $this->pembayaran($januari, 'bukti-bayar/2026/02/pjg.png', $perpanjangan);
        Rental::factory()->periode('2026-01-20', '2026-01-22')->create(['total_biaya' => 40000, 'nomor_nota' => 'SEW-20260120-0001']);

        DB::enableQueryLog();
        $hasil = $this->service()->perBulan(2026);
        $jumlahQuery = count(DB::getQueryLog());

        $januariRincian = $hasil[0]['rincian'];
        $this->assertSame(['SEW-20260105-0001', 'SEW-20260120-0001'], array_column($januariRincian, 'nomor'));
        $this->assertSame('Budi', $januariRincian[0]['penyewa']);
        $this->assertSame('bukti-bayar/2026/01/awal.png', $januariRincian[0]['bukti']);
        $this->assertNull($januariRincian[1]['bukti']);

        $this->assertSame('Perpanjangan', $hasil[1]['rincian'][0]['jenis']);
        $this->assertSame('bukti-bayar/2026/02/pjg.png', $hasil[1]['rincian'][0]['bukti']);

        // Sewa + relasinya (garage, penyewa, payments) dan perpanjangan + relasinya:
        // jumlah query tetap, tidak bertambah per baris.
        $this->assertLessThanOrEqual(9, $jumlahQuery);
    }

    public function test_laporan_per_hari_dalam_bulan(): void
    {
        Rental::factory()->periode('2026-02-10', '2026-02-12')->create(['total_biaya' => 60000, 'nomor_nota' => 'SEW-20260210-0001']);
        Rental::factory()->periode('2026-02-10', '2026-02-11')->create(['total_biaya' => 40000, 'nomor_nota' => 'SEW-20260210-0002']);
        Rental::factory()->periode('2026-03-01', '2026-03-02')->create(['total_biaya' => 99000, 'nomor_nota' => 'SEW-20260301-0001']);

        $hasil = $this->service()->perHari(2026, 2);

        $this->assertCount(28, $hasil);
        $this->assertSame(['hari' => 10, 'jumlah_sewa' => 2, 'pendapatan' => 100000.0], Arr::except($hasil[9], 'rincian'));
        $this->assertSame(['hari' => 28, 'jumlah_sewa' => 0, 'pendapatan' => 0.0], Arr::except($hasil[27], 'rincian'));
    }

    public function test_laporan_per_tipe_garasi_dalam_rentang(): void
    {
        Garage::factory()->bike('S001')->create();
        Garage::factory()->electric('E001')->create();
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['garage_id' => 'S001', 'total_biaya' => 15000, 'nomor_nota' => 'SEW-20261121-0001']);
        Rental::factory()->periode('2026-11-22', '2026-11-24')->create(['garage_id' => 'E001', 'total_biaya' => 190000, 'nomor_nota' => 'SEW-20261122-0001']);
        Rental::factory()->periode('2026-12-01', '2026-12-03')->create(['garage_id' => 'E001', 'total_biaya' => 99000, 'nomor_nota' => 'SEW-20261201-0001']);

        $hasil = $this->service()->perTipeGarasi(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(
            [
                ['tipe' => GarageType::BIKE, 'jumlah_sewa' => 1, 'pendapatan' => 15000.0],
                ['tipe' => GarageType::CAR, 'jumlah_sewa' => 0, 'pendapatan' => 0.0],
                ['tipe' => GarageType::ELECTRIC, 'jumlah_sewa' => 1, 'pendapatan' => 190000.0],
            ],
            $hasil->map(fn (array $b) => Arr::except($b, 'rincian'))->all(),
        );
    }

    public function test_laporan_per_penyewa_dalam_rentang(): void
    {
        $budi = Penyewa::factory()->approved()->create(['nama_lengkap' => 'Budi']);
        $sari = Penyewa::factory()->approved()->create(['nama_lengkap' => 'Sari']);
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['penyewa_id' => $budi->id, 'total_biaya' => 50000, 'nomor_nota' => 'SEW-20261121-0001']);
        Rental::factory()->periode('2026-11-22', '2026-11-24')->create(['penyewa_id' => $sari->id, 'total_biaya' => 200000, 'nomor_nota' => 'SEW-20261122-0001']);

        $hasil = $this->service()->perPenyewa(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(['Sari', 'Budi'], $hasil->pluck('nama_lengkap')->all());
        $this->assertSame([200000.0, 50000.0], $hasil->pluck('pendapatan')->all());
    }
}
