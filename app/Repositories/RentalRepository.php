<?php

namespace App\Repositories;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Akses data sewa, perpanjangan, dan pembayaran (diagram B.3). Dipakai
 * RentalService, ExtensionService, GarageProvisioningService, ReportService,
 * dan DocumentNumberGenerator.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RentalRepository
{
    /**
     * Nomor nota terakhir yang berawalan prefix tersebut (misalnya
     * "SEW-20261121-"), atau null bila belum ada.
     *
     * @param  string  $prefix  awalan nomor nota harian
     * @return string|null nomor nota tertinggi, atau null
     */
    public function nomorNotaTerakhir(string $prefix): ?string
    {
        return Rental::query()->where('nomor_nota', 'like', $prefix.'%')->max('nomor_nota');
    }

    /**
     * Nomor perpanjangan terakhir yang berawalan prefix tersebut (misalnya
     * "PJG-20261121-"), atau null bila belum ada.
     *
     * @param  string  $prefix  awalan nomor perpanjangan harian
     * @return string|null nomor perpanjangan tertinggi, atau null
     */
    public function nomorPerpanjangTerakhir(string $prefix): ?string
    {
        return RentalExtension::query()->where('nomor_perpanjang', 'like', $prefix.'%')->max('nomor_perpanjang');
    }

    /**
     * Sewa berstatus AKTIF yang mencakup tanggal tersebut (inklusif).
     *
     * @param  CarbonInterface  $tanggal  tanggal acuan
     * @return Collection<int, \App\Domain\Models\Rental> sewa aktif pada tanggal itu
     */
    public function aktifPada(CarbonInterface $tanggal): Collection
    {
        $hari = $tanggal->toDateString();

        return Rental::query()
            ->where('status', RentalStatus::AKTIF->value)
            ->whereDate('tanggal_mulai', '<=', $hari)
            ->whereDate('tanggal_selesai', '>=', $hari)
            ->get();
    }

    /**
     * Mengubah sewa AKTIF yang tanggal selesainya sebelum tanggal tersebut
     * menjadi SELESAI, dan mengembalikan sewa-sewa yang diubah.
     *
     * @param  CarbonInterface  $tanggal  tanggal acuan, biasanya hari ini
     * @return Collection<int, \App\Domain\Models\Rental> sewa yang baru diubah menjadi SELESAI
     */
    public function selesaikanSewaLewat(CarbonInterface $tanggal): Collection
    {
        return DB::transaction(function () use ($tanggal) {
            $lewat = Rental::query()
                ->where('status', RentalStatus::AKTIF->value)
                ->whereDate('tanggal_selesai', '<', $tanggal->toDateString())
                ->lockForUpdate()
                ->get();

            if ($lewat->isNotEmpty()) {
                Rental::query()
                    ->whereKey($lewat->modelKeys())
                    ->update(['status' => RentalStatus::SELESAI->value]);

                $lewat->each(fn (Rental $sewa) => $sewa->status = RentalStatus::SELESAI);
            }

            return $lewat;
        });
    }

    /**
     * Apakah kendaraan sudah berada di sewa AKTIF lain yang tanggalnya
     * beririsan dengan rentang tersebut (asumsi nomor 23).
     *
     * @param  int  $vehicleId  id kendaraan
     * @param  CarbonInterface  $mulai  tanggal mulai rentang
     * @param  CarbonInterface  $selesai  tanggal selesai rentang (inklusif)
     * @return bool true jika kendaraan sedang disewa pada rentang itu
     */
    public function kendaraanSedangDisewa(int $vehicleId, CarbonInterface $mulai, CarbonInterface $selesai): bool
    {
        return Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', RentalStatus::AKTIF->value)
            ->where(function (Builder $kueri) use ($mulai, $selesai) {
                $kueri->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
                    ->whereDate('tanggal_selesai', '>=', $mulai->toDateString());
            })
            ->exists();
    }

    /**
     * Data mentah laporan: sewa (semua status) yang tanggal mulainya jatuh
     * pada tahun tersebut.
     *
     * @param  int  $tahun  tahun laporan
     * @return Collection<int, Rental> sewa beserta garasi, penyewa, dan pembayaran
     */
    public function rekapPerBulan(int $tahun): Collection
    {
        return $this->dalamRentang(
            CarbonImmutable::create($tahun, 1, 1),
            CarbonImmutable::create($tahun, 12, 31),
        );
    }

    /**
     * Sewa yang tanggal mulainya jatuh pada bulan tersebut.
     *
     * @param  int  $tahun  tahun laporan
     * @param  int  $bulan  bulan laporan, 1 sampai 12
     * @return Collection<int, Rental> sewa beserta garasi, penyewa, dan pembayaran
     */
    public function rekapPerHari(int $tahun, int $bulan): Collection
    {
        $awal = CarbonImmutable::create($tahun, $bulan, 1);

        return $this->dalamRentang($awal, $awal->endOfMonth());
    }

    /**
     * Sewa yang tanggal mulainya dalam rentang (inklusif).
     *
     * @param  CarbonInterface  $dari  tanggal awal rentang
     * @param  CarbonInterface  $sampai  tanggal akhir rentang (inklusif)
     * @return Collection<int, Rental> sewa beserta garasi, penyewa, dan pembayaran
     */
    public function rekapPerTipeGarasi(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return $this->dalamRentang($dari, $sampai);
    }

    /**
     * Sewa yang tanggal mulainya dalam rentang (inklusif).
     *
     * @param  CarbonInterface  $dari  tanggal awal rentang
     * @param  CarbonInterface  $sampai  tanggal akhir rentang (inklusif)
     * @return Collection<int, Rental> sewa beserta garasi, penyewa, dan pembayaran
     */
    public function rekapPerPenyewa(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return $this->dalamRentang($dari, $sampai);
    }

    /**
     * Perpanjangan yang diproses (created_at) dalam rentang tanggal, inklusif,
     * beserta sewa, garasi, dan penyewanya. Biaya perpanjangan dilaporkan
     * pada tanggal diproses, bukan tanggal mulai sewanya.
     *
     * @param  CarbonInterface  $dari  tanggal awal rentang
     * @param  CarbonInterface  $sampai  tanggal akhir rentang (inklusif)
     * @return Collection<int, RentalExtension> perpanjangan beserta sewa, garasi, penyewa, dan pembayaran
     */
    public function perpanjanganDalamRentang(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return RentalExtension::query()
            ->with(['rental.garage', 'rental.penyewa', 'payment'])
            ->whereBetween('created_at', [
                $dari->copy()->startOfDay()->toDateTimeString(),
                $sampai->copy()->endOfDay()->toDateTimeString(),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Sewa dalam rentang tanggal mulai beserta garasi, penyewa, dan
     * pembayarannya, yang dipakai ringkasan maupun rincian laporan
     * (eager loading, tanpa N+1).
     *
     * @return Collection<int, Rental>
     */
    private function dalamRentang(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return Rental::query()
            ->with(['garage', 'penyewa', 'payments'])
            ->whereBetween('tanggal_mulai', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('tanggal_mulai')
            ->orderBy('id')
            ->get();
    }

    /**
     * Seluruh sewa milik penyewa, terbaru lebih dulu, beserta garasi,
     * kendaraan, pembayaran, dan perpanjangannya (eager loading).
     *
     * @param  int  $penyewaId  id penyewa pemilik sewa
     * @return Collection<int, Rental> riwayat sewa penyewa tersebut
     */
    public function riwayatPenyewa(int $penyewaId): Collection
    {
        return Rental::query()
            ->with(['garage', 'vehicle', 'payments', 'extensions' => fn ($kueri) => $kueri->orderBy('created_at')->orderBy('id')])
            ->where('penyewa_id', $penyewaId)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Mencari sewa menurut id.
     *
     * @param  int  $id  id sewa
     * @return Rental|null sewa, atau null jika tidak ada
     */
    public function cariId(int $id): ?Rental
    {
        return Rental::query()->find($id);
    }

    /**
     * Menyimpan sewa baru atau perubahan sewa.
     *
     * @param  Rental  $sewa  sewa yang akan disimpan
     * @return Rental sewa yang sama setelah tersimpan
     */
    public function simpan(Rental $sewa): Rental
    {
        $sewa->save();

        return $sewa;
    }

    /**
     * Menyimpan riwayat perpanjangan. Dipanggil di dalam transaksi yang sama
     * dengan pembayaran dan perubahan tanggal selesai sewa.
     *
     * @param  RentalExtension  $perpanjangan  perpanjangan yang akan disimpan
     * @return RentalExtension perpanjangan yang sama setelah tersimpan
     */
    public function simpanPerpanjangan(RentalExtension $perpanjangan): RentalExtension
    {
        $perpanjangan->save();

        return $perpanjangan;
    }

    /**
     * Menyimpan pembayaran sewa awal atau perpanjangan. Dipanggil di dalam
     * transaksi yang sama dengan penyimpanan sewa.
     *
     * @param  Payment  $pembayaran  pembayaran yang akan disimpan
     * @return Payment pembayaran yang sama setelah tersimpan
     */
    public function simpanPembayaran(Payment $pembayaran): Payment
    {
        $pembayaran->save();

        return $pembayaran;
    }
}
