<?php

namespace Tests\Unit\Domain\Models;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class RentalTest extends TestCase
{
    private function sewa(string $mulai, string $selesai, RentalStatus $status = RentalStatus::AKTIF): Rental
    {
        // Atribut mentah seperti baris dari basis data; menghindari resolver koneksi.
        $sewa = new Rental();
        $sewa->setRawAttributes([
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'status' => $status->value,
        ], true);

        return $sewa;
    }

    /**
     * Lama sewa dibaca dari tanggal; hari terakhir inklusif.
     */
    public function test_durasi_hari_inklusif(): void
    {
        $this->assertSame(30, $this->sewa('2026-11-21', '2026-12-20')->durasiHari());
        $this->assertSame(1, $this->sewa('2026-11-21', '2026-11-21')->durasiHari());
    }

    public function test_aktif_pada_mencakup_batas_inklusif(): void
    {
        $sewa = $this->sewa('2026-11-21', '2026-11-23');

        $this->assertFalse($sewa->aktifPada(CarbonImmutable::parse('2026-11-20')));
        $this->assertTrue($sewa->aktifPada(CarbonImmutable::parse('2026-11-21')));
        $this->assertTrue($sewa->aktifPada(CarbonImmutable::parse('2026-11-23')));
        $this->assertFalse($sewa->aktifPada(CarbonImmutable::parse('2026-11-24')));
    }

    public function test_sewa_selesai_tidak_aktif(): void
    {
        $sewa = $this->sewa('2026-11-21', '2026-11-23', RentalStatus::SELESAI);

        $this->assertFalse($sewa->aktifPada(CarbonImmutable::parse('2026-11-22')));
    }

    private function pembayaran(string $path, ?int $extensionId): Payment
    {
        $pembayaran = new Payment();
        $pembayaran->setRawAttributes(['path_bukti_bayar' => $path, 'rental_extension_id' => $extensionId], true);

        return $pembayaran;
    }

    private function perpanjangan(int $id, float $biaya): RentalExtension
    {
        $perpanjangan = new RentalExtension();
        $perpanjangan->setRawAttributes(['id' => $id, 'biaya_tambahan' => $biaya], true);

        return $perpanjangan;
    }

    /**
     * Sewa dengan pembayaran awal dan dua perpanjangan (relasi sudah dimuat).
     */
    private function sewaDenganPerpanjangan(): Rental
    {
        $sewa = $this->sewa('2026-11-21', '2026-11-27');
        $sewa->setRawAttributes(array_merge($sewa->getAttributes(), ['total_biaya' => 75000]), true);
        $sewa->setRelation('payments', new Collection([
            $this->pembayaran('bukti/pjg-7.png', 7),
            $this->pembayaran('bukti/awal.png', null),
            $this->pembayaran('bukti/pjg-8.png', 8),
        ]));
        $sewa->setRelation('extensions', new Collection([$this->perpanjangan(7, 50000), $this->perpanjangan(8, 25000)]));

        return $sewa;
    }

    public function test_pembayaran_awal_adalah_pembayaran_tanpa_perpanjangan(): void
    {
        $this->assertSame('bukti/awal.png', $this->sewaDenganPerpanjangan()->pembayaranAwal()->path_bukti_bayar);
    }

    public function test_pembayaran_per_perpanjangan(): void
    {
        $sewa = $this->sewaDenganPerpanjangan();

        $this->assertSame('bukti/pjg-8.png', $sewa->pembayaranPerpanjangan($this->perpanjangan(8, 0))->path_bukti_bayar);
        $this->assertNull($sewa->pembayaranPerpanjangan($this->perpanjangan(99, 0)));
    }

    public function test_total_dibayar_termasuk_biaya_perpanjangan(): void
    {
        $this->assertSame(150000.0, $this->sewaDenganPerpanjangan()->totalDibayar());
    }

    public function test_tanpa_pembayaran_dan_perpanjangan(): void
    {
        $sewa = $this->sewa('2026-11-21', '2026-11-23');
        $sewa->setRawAttributes(array_merge($sewa->getAttributes(), ['total_biaya' => 75000]), true);
        $sewa->setRelation('payments', new Collection());
        $sewa->setRelation('extensions', new Collection());

        $this->assertNull($sewa->pembayaranAwal());
        $this->assertSame(75000.0, $sewa->totalDibayar());
    }
}
