<?php

namespace App\Repositories;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Enums\RentalStatus;
use App\Domain\Models\CarGarage;
use App\Domain\Models\Garage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Akses data garasi (diagram B.3). Dipakai GarageProvisioningService,
 * RentalService, GarageIdGenerator, dan StoreRentalRequest.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarageRepository
{
    /**
     * Pintu query untuk seluruh garasi, apa pun tipenya.
     *
     * Garage abstrak sehingga Garage::query() tidak dapat dipakai (framework
     * membuat `new static`). Query lewat satu kelas turunan tanpa filter tipe
     * tetap memuat kelas yang sesuai kolom type, karena newFromBuilder()
     * diwarisi dari Garage. CarGarage hanya dipilih sebagai pintu, bukan
     * sebagai filter.
     *
     * @return Builder<Garage>
     */
    private function kueri(): Builder
    {
        return CarGarage::query();
    }

    /**
     * Nomor tertinggi yang ada untuk tipe tersebut, 0 jika belum ada.
     *
     * @param  GarageType  $tipe  tipe garasi yang dicari
     * @return int nomor urut tertinggi tanpa huruf awalan
     */
    public function nomorTerakhir(GarageType $tipe): int
    {
        return (int) $this->kueri()
            ->where('type', $tipe->value)
            ->max(DB::raw('CAST(SUBSTRING(id, 2) AS UNSIGNED)'));
    }

    /**
     * Menyimpan beberapa garasi sekaligus. Setiap baris berisi kunci id, type
     * (GarageType), harga_sewa_harian, harga_listrik_per_kwh, dan status
     * (GarageStatus). Dipanggil di dalam transaksi pemanggil; hasilnya
     * berkelas turunan sesuai tipe.
     *
     * @param  array<int, array<string, mixed>>  $dataGarasi  baris data garasi yang akan disimpan
     * @return Collection<int, Garage> garasi yang sudah tersimpan
     */
    public function simpanBerkelompok(array $dataGarasi): Collection
    {
        $hasil = new Collection();

        foreach ($dataGarasi as $baris) {
            $kelas = $baris['type']->kelasModel();

            $garasi = new $kelas();
            $garasi->forceFill($baris)->save();

            $hasil->push($garasi);
        }

        return $hasil;
    }

    /**
     * Semua garasi (atau satu tipe), urut menurut tipe lalu kode, beserta sewa
     * AKTIF-nya untuk menampilkan sampai kapan garasi terpakai.
     *
     * @param  GarageType|null  $tipe  tipe yang disaring, atau null untuk semua tipe
     * @return Collection<int, Garage> garasi beserta relasi rentals berstatus AKTIF
     */
    public function semua(?GarageType $tipe): Collection
    {
        return $this->kueri()
            ->when($tipe !== null, fn (Builder $kueri) => $kueri->where('type', $tipe->value))
            ->with(['rentals' => fn ($sewa) => $sewa->where('status', RentalStatus::AKTIF->value)->orderBy('tanggal_selesai')])
            // Kolom enum MySQL diurutkan menurut urutan definisinya: BIKE, CAR, ELECTRIC.
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }

    /**
     * Jumlah garasi per tipe dan status, misalnya
     * ['CAR' => ['AVAILABLE' => 3, 'USED' => 2]]. Tipe tanpa garasi tidak muncul.
     *
     * @return array<string, array<string, int>> jumlah garasi per tipe lalu per status
     */
    public function jumlahPerTipeDanStatus(): array
    {
        $hasil = [];

        $baris = $this->kueri()->toBase()
            ->select('type', 'status', DB::raw('COUNT(*) AS jumlah'))
            ->groupBy('type', 'status')
            ->get();

        foreach ($baris as $b) {
            $hasil[$b->type][$b->status] = (int) $b->jumlah;
        }

        return $hasil;
    }

    /**
     * Mengubah kolom status garasi (cache).
     *
     * @param  string  $garageId  kode garasi, misalnya C010
     * @param  GarageStatus  $status  status baru
     */
    public function ubahStatus(string $garageId, GarageStatus $status): void
    {
        $this->kueri()->whereKey($garageId)->update(['status' => $status->value]);
    }

    /**
     * Mencari garasi menurut kode. Dengan $kunci = true baris dikunci
     * (lockForUpdate) dan hanya boleh dipakai di dalam transaksi (asumsi nomor 9).
     *
     * @param  string  $garageId  kode garasi, misalnya C010
     * @param  bool  $kunci  true untuk mengunci baris selama transaksi
     * @return Garage|null garasi berkelas turunan sesuai tipe, atau null jika tidak ada
     */
    public function cariId(string $garageId, bool $kunci = false): ?Garage
    {
        return $this->kueri()
            ->when($kunci, fn (Builder $kueri) => $kueri->lockForUpdate())
            ->find($garageId);
    }

    /**
     * Garasi bertipe tersebut yang tidak memiliki sewa AKTIF beririsan dengan
     * rentang tanggal. Dihitung dari tanggal sewa, bukan dari kolom status.
     *
     * @param  GarageType  $tipe  tipe garasi yang dicari
     * @param  CarbonInterface  $mulai  tanggal mulai rentang
     * @param  CarbonInterface  $selesai  tanggal selesai rentang (inklusif)
     * @return Collection<int, Garage> garasi tersedia, urut menurut kode
     */
    public function tersediaPada(GarageType $tipe, CarbonInterface $mulai, CarbonInterface $selesai): Collection
    {
        return $this->kueri()
            ->where('type', $tipe->value)
            ->whereDoesntHave('rentals', function (Builder $sewa) use ($mulai, $selesai) {
                $sewa->where('status', RentalStatus::AKTIF->value)
                    ->whereDate('tanggal_mulai', '<=', $selesai->toDateString())
                    ->whereDate('tanggal_selesai', '>=', $mulai->toDateString());
            })
            ->orderBy('id')
            ->get();
    }
}
