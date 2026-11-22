<?php

namespace App\Services;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\GarageType;
use App\Domain\Models\Garage;
use App\Exceptions\KuotaGarasiHabisException;
use App\Repositories\GarageRepository;
use App\Repositories\RentalRepository;
use App\Support\GarageCodeFormatter;
use App\Support\GarageIdGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Pengelolaan garasi: pembuatan berkelompok oleh admin (asumsi nomor 13 dan
 * 14), daftar dan ringkasan, serta sinkronisasi status (asumsi nomor 25).
 * Kode dibangkitkan dengan MAX + 1 per tipe; pembangkitan dan penyimpanan
 * berada dalam satu transaksi. Jika dua permintaan benar-benar bersamaan,
 * kolom PK `garages.id` membuat penyimpanan kedua gagal dengan galat, bukan
 * ID kembar. Bergantung pada GarageRepository, RentalRepository, dan
 * GarageIdGenerator; dipakai AdminGarageController, RentalService,
 * ExtensionService, dan perintah garasi:sinkron-status.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarageProvisioningService
{
    public function __construct(
        private readonly GarageRepository $garageRepo,
        private readonly RentalRepository $rentalRepo,
        private readonly GarageIdGenerator $idGenerator,
    ) {
    }

    /**
     * Sinkronisasi status (asumsi nomor 25): sewa AKTIF yang tanggal
     * selesainya sudah lewat menjadi SELESAI dan garasinya kembali AVAILABLE,
     * kecuali garasi itu masih punya sewa aktif lain. Idempoten. Dipanggil
     * sebelum daftar garasi, garasi tersedia, riwayat sewa, dan daftar
     * perpanjangan ditampilkan, serta oleh perintah garasi:sinkron-status.
     *
     * @param  CarbonInterface  $hariIni  tanggal acuan, biasanya hari ini
     * @return int jumlah sewa yang diselesaikan
     */
    public function sinkronkanStatus(CarbonInterface $hariIni): int
    {
        $diselesaikan = $this->rentalRepo->selesaikanSewaLewat($hariIni);

        if ($diselesaikan->isEmpty()) {
            return 0;
        }

        $masihDipakai = $this->rentalRepo->aktifPada($hariIni)->pluck('garage_id')->all();

        $garasiDibebaskan = $diselesaikan->pluck('garage_id')->unique()->diff($masihDipakai);

        foreach ($garasiDibebaskan as $garageId) {
            $this->garageRepo->ubahStatus($garageId, GarageStatus::AVAILABLE);
        }

        return $diselesaikan->count();
    }

    /**
     * Daftar garasi untuk admin. Status disinkronkan lebih dulu (asumsi
     * nomor 25) agar garasi dari sewa yang sudah lewat tampil kosong.
     *
     * @param  GarageType|null  $tipe  tipe yang disaring, atau null untuk semua tipe
     * @param  CarbonInterface  $hariIni  tanggal acuan sinkronisasi, biasanya hari ini
     * @return Collection<int, Garage> garasi beserta sewa AKTIF-nya
     */
    public function daftarGarasi(?GarageType $tipe, CarbonInterface $hariIni): Collection
    {
        $this->sinkronkanStatus($hariIni);

        return $this->garageRepo->semua($tipe);
    }

    /**
     * Ringkasan per tipe: total, terpakai, kosong, dan sisa kuota. Ketiga
     * tipe selalu ada, termasuk yang belum punya garasi.
     *
     * @return array<int, array{tipe: GarageType, total: int, terpakai: int, kosong: int, sisa_kuota: int}> satu baris per tipe garasi
     */
    public function ringkasan(): array
    {
        $jumlah = $this->garageRepo->jumlahPerTipeDanStatus();
        $hasil = [];

        foreach (GarageType::cases() as $tipe) {
            $terpakai = $jumlah[$tipe->value][GarageStatus::USED->value] ?? 0;
            $kosong = $jumlah[$tipe->value][GarageStatus::AVAILABLE->value] ?? 0;

            $hasil[] = [
                'tipe' => $tipe,
                'total' => $terpakai + $kosong,
                'terpakai' => $terpakai,
                'kosong' => $kosong,
                'sisa_kuota' => $this->sisaKuota($tipe),
            ];
        }

        return $hasil;
    }

    /**
     * Membuat sekumpulan garasi baru bertipe sama berstatus AVAILABLE.
     *
     * @param  array{tipeGarasi: GarageType, jumlah: int, hargaSewaHarian: float, hargaListrikPerKwh: ?float}  $data  isian form; harga dalam rupiah
     * @return Collection<int, Garage> garasi yang baru dibuat
     *
     * @throws ValidationException jika garasi listrik tanpa harga listrik
     * @throws InvalidArgumentException jika jumlah kurang dari satu
     * @throws KuotaGarasiHabisException jika melebihi sisa kuota tipe
     */
    public function buatBerkelompok(array $data): Collection
    {
        $hargaListrik = $this->hargaListrik($data);

        return DB::transaction(function () use ($data, $hargaListrik) {
            $daftarKode = $this->idGenerator->berikutnya($data['tipeGarasi'], $data['jumlah']);

            $baris = array_map(fn (string $kode) => [
                'id' => $kode,
                'type' => $data['tipeGarasi'],
                'harga_sewa_harian' => $data['hargaSewaHarian'],
                'harga_listrik_per_kwh' => $hargaListrik,
                'status' => GarageStatus::AVAILABLE,
            ], $daftarKode);

            return $this->garageRepo->simpanBerkelompok($baris);
        });
    }

    /**
     * Sisa kuota garasi untuk tipe tersebut, dipakai form untuk membatasi isian.
     *
     * @param  GarageType  $tipe  tipe garasi
     * @return int banyak kode garasi yang masih dapat dibuat untuk tipe itu
     */
    public function sisaKuota(GarageType $tipe): int
    {
        return GarageCodeFormatter::KUOTA_PER_TIPE - $this->garageRepo->nomorTerakhir($tipe);
    }

    /**
     * Harga listrik wajib untuk ELECTRIC dan NULL untuk tipe lain (erd.dbml).
     *
     * @throws ValidationException jika garasi listrik tanpa harga listrik
     */
    private function hargaListrik(array $data): ?float
    {
        if ($data['tipeGarasi'] !== GarageType::ELECTRIC) {
            return null;
        }

        if ($data['hargaListrikPerKwh'] === null) {
            throw ValidationException::withMessages([
                'harga_listrik_per_kwh' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik.',
            ]);
        }

        return $data['hargaListrikPerKwh'];
    }
}
