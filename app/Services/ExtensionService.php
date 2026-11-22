<?php

namespace App\Services;

use App\Domain\Enums\RentalType;
use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\RentalPeriod;
use App\Exceptions\JumlahHariTidakValidException;
use App\Repositories\RentalRepository;
use App\Support\DateCalculator;
use App\Support\DocumentNumberGenerator;
use App\Support\PaymentProofStorage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Perpanjangan sewa oleh admin. Dihitung dengan tarif harian dan tidak
 * dikonversi menjadi bulanan (asumsi nomor 17), hanya untuk sewa yang aktif
 * pada hari itu (asumsi nomor 29). Seperti sewa baru, penyimpanan dilakukan
 * dalam satu transaksi dan berkas bukti bayar dihapus kembali bila gagal.
 * Bergantung pada RentalRepository, PricingContext, DateCalculator,
 * DocumentNumberGenerator, GarageProvisioningService, dan PaymentProofStorage;
 * dipakai AdminExtensionController.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class ExtensionService
{
    public function __construct(
        private readonly RentalRepository $rentalRepo,
        private readonly PricingContext $pricing,
        private readonly DateCalculator $kalender,
        private readonly DocumentNumberGenerator $numbering,
        private readonly GarageProvisioningService $garasi,
        private readonly PaymentProofStorage $storage,
    ) {
    }

    /**
     * Sewa yang aktif hari ini. Sinkronisasi status dijalankan lebih dulu agar
     * sewa yang sudah lewat tidak ikut tampil.
     *
     * @return Collection<int, Rental> sewa berstatus AKTIF yang mencakup hari ini
     */
    public function sewaAktifHariIni(): Collection
    {
        $hariIni = CarbonImmutable::today();

        $this->garasi->sinkronkanStatus($hariIni);

        return $this->rentalRepo->aktifPada($hariIni);
    }

    /**
     * Biaya tambahan = harga sewa harian x tambahan hari.
     *
     * @param  Rental  $sewa  sewa yang akan diperpanjang, relasi garage dipakai untuk tarif
     * @param  CarbonInterface  $tanggalSelesaiBaru  tanggal selesai baru (inklusif)
     * @return float biaya tambahan dalam rupiah
     *
     * @throws JumlahHariTidakValidException jika tanggal baru tidak setelah tanggal selesai lama
     */
    public function hitungSisaBiaya(Rental $sewa, CarbonInterface $tanggalSelesaiBaru): float
    {
        $tambahanHari = $this->tambahanHari($sewa, $tanggalSelesaiBaru);

        $periode = new RentalPeriod(
            RentalType::HARIAN,
            $tambahanHari,
            $sewa->tanggal_selesai->copy()->addDay(),
            $tanggalSelesaiBaru,
        );

        return $this->pricing
            ->untukTipeSewa(RentalType::HARIAN)
            ->strategi()
            ->hitungBiayaSewa($sewa->garage, $periode);
    }

    /**
     * Memperpanjang sewa sampai tanggal selesai yang baru.
     *
     * @param  int  $rentalId  id sewa yang diperpanjang
     * @param  CarbonInterface  $tanggalSelesaiBaru  tanggal selesai baru, harus setelah tanggal selesai lama
     * @param  UploadedFile  $buktiBayar  bukti bayar biaya tambahan
     * @param  int  $adminId  id user admin yang memproses
     * @return RentalExtension riwayat perpanjangan yang tersimpan
     *
     * @throws DomainException jika sewa tidak ditemukan atau tidak aktif hari ini
     * @throws JumlahHariTidakValidException jika tanggal baru tidak setelah tanggal selesai lama
     * @throws \App\Exceptions\BuktiBayarTidakValidException jika berkas bukti bayar tidak valid
     */
    public function perpanjang(
        int $rentalId,
        CarbonInterface $tanggalSelesaiBaru,
        UploadedFile $buktiBayar,
        int $adminId,
    ): RentalExtension {
        $hariIni = CarbonImmutable::today();

        $sewa = $this->rentalRepo->cariId($rentalId)
            ?? throw new DomainException('Sewa tidak ditemukan.');

        if (! $sewa->aktifPada($hariIni)) {
            throw new DomainException('Hanya sewa yang aktif hari ini yang dapat diperpanjang.');
        }

        $tambahanHari = $this->tambahanHari($sewa, $tanggalSelesaiBaru);
        $biaya = $this->hitungSisaBiaya($sewa, $tanggalSelesaiBaru);

        $path = $this->storage->simpan($buktiBayar, 'bukti-bayar/'.$hariIni->format('Y/m'));

        try {
            return DB::transaction(
                fn () => $this->simpanDalamTransaksi($sewa, $tanggalSelesaiBaru, $tambahanHari, $biaya, $path, $adminId, $hariIni),
            );
        } catch (Throwable $e) {
            $this->storage->hapus($path);

            throw $e;
        }
    }

    private function simpanDalamTransaksi(
        Rental $sewa,
        CarbonInterface $tanggalSelesaiBaru,
        int $tambahanHari,
        float $biaya,
        string $path,
        int $adminId,
        CarbonImmutable $hariIni,
    ): RentalExtension {
        $perpanjangan = $this->rentalRepo->simpanPerpanjangan((new RentalExtension())->forceFill([
            'rental_id' => $sewa->id,
            'nomor_perpanjang' => $this->numbering->nomorPerpanjang($hariIni),
            'tanggal_selesai_lama' => $sewa->tanggal_selesai,
            'tanggal_selesai_baru' => $tanggalSelesaiBaru,
            'tambahan_hari' => $tambahanHari,
            'biaya_tambahan' => $biaya,
            'diproses_oleh' => $adminId,
        ]));

        // payments.rental_id harus sama dengan rental_extensions.rental_id;
        // MySQL tidak dapat menjamin ini dengan constraint sederhana.
        if ((int) $perpanjangan->rental_id !== (int) $sewa->id) {
            throw new DomainException('Perpanjangan tidak sesuai dengan sewa yang diperpanjang.');
        }

        $this->rentalRepo->simpanPembayaran((new Payment())->forceFill([
            'rental_id' => $sewa->id,
            'rental_extension_id' => $perpanjangan->id,
            'jumlah' => $biaya,
            'path_bukti_bayar' => $path,
            'diunggah_oleh' => $adminId,
            'diunggah_pada' => now(),
        ]));

        $sewa->tanggal_selesai = $tanggalSelesaiBaru;
        $this->rentalRepo->simpan($sewa);

        return $perpanjangan;
    }

    /**
     * @throws JumlahHariTidakValidException jika tambahan hari kurang dari satu
     */
    private function tambahanHari(Rental $sewa, CarbonInterface $tanggalSelesaiBaru): int
    {
        $tambahan = $this->kalender->selisihHari($sewa->tanggal_selesai, $tanggalSelesaiBaru);

        if ($tambahan < 1) {
            throw new JumlahHariTidakValidException(
                'Tanggal selesai baru harus setelah tanggal selesai lama.',
                ['tambahan_hari' => $tambahan],
            );
        }

        return $tambahan;
    }
}
