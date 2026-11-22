<?php

namespace App\Repositories;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Akses data kendaraan (diagram B.3). Dipakai RentalService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class VehicleRepository
{
    /**
     * Mencari kendaraan menurut nomor plat yang sudah dinormalisasi.
     *
     * @param  string  $nomorPlat  plat dalam format simpan, misalnya "DD 1234 AB"
     * @return Vehicle|null kendaraan, atau null jika tidak ada
     */
    public function cariNomorPlat(string $nomorPlat): ?Vehicle
    {
        return Vehicle::query()->where('nomor_plat', $nomorPlat)->first();
    }

    /**
     * Kendaraan milik penyewa yang pernah diparkir (punya sewa), yang terakhir
     * diparkir lebih dulu. Atribut `sedang_disewa` bernilai benar bila
     * kendaraan berada di sewa AKTIF pada tanggal tersebut.
     *
     * @param  int  $penyewaId  id penyewa pemilik kendaraan
     * @param  CarbonInterface  $tanggal  tanggal acuan untuk atribut sedang_disewa
     * @return Collection<int, Vehicle> kendaraan yang pernah diparkir penyewa
     */
    public function pernahDiparkirPenyewa(int $penyewaId, CarbonInterface $tanggal): Collection
    {
        $hari = $tanggal->toDateString();

        return Vehicle::query()
            ->where('penyewa_id', $penyewaId)
            ->whereHas('rentals')
            ->withMax('rentals', 'tanggal_mulai')
            ->withExists(['rentals as sedang_disewa' => fn ($kueri) => $kueri
                ->where('status', RentalStatus::AKTIF->value)
                ->where('tanggal_mulai', '<=', $hari)
                ->where('tanggal_selesai', '>=', $hari)])
            ->orderByDesc('rentals_max_tanggal_mulai')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Menyimpan kendaraan baru atau perubahan kendaraan.
     *
     * @param  Vehicle  $kendaraan  kendaraan yang akan disimpan
     * @return Vehicle kendaraan yang sama setelah tersimpan
     */
    public function simpan(Vehicle $kendaraan): Vehicle
    {
        $kendaraan->save();

        return $kendaraan;
    }
}
