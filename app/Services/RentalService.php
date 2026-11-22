<?php

namespace App\Services;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\RentalEstimate;
use App\Domain\Pricing\RentalPeriod;
use App\Domain\Pricing\RentalTypeResolver;
use App\Domain\Rules\BatteryLevelRule;
use App\Domain\Rules\GarageCompatibilityRule;
use App\Exceptions\GarasiTidakTersediaException;
use App\Exceptions\KendaraanSedangDisewaException;
use App\Exceptions\KendaraanTidakCocokException;
use App\Exceptions\NomorPlatDipakaiPenyewaLainException;
use App\Exceptions\PenyewaBelumTerverifikasiException;
use App\Repositories\GarageRepository;
use App\Repositories\PenyewaRepository;
use App\Repositories\RentalRepository;
use App\Repositories\VehicleRepository;
use App\Support\DocumentNumberGenerator;
use App\Support\NomorPlatFormatter;
use App\Support\PaymentProofStorage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengajuan sewa baru. Penyimpanan sewa, pembayaran, dan perubahan status
 * garasi berada dalam satu transaksi (asumsi nomor 31); berkas bukti bayar
 * disimpan lebih dulu dan dihapus kembali bila transaksi gagal. Bergantung
 * pada repository garasi, penyewa, sewa, dan kendaraan, PricingContext,
 * RentalTypeResolver, DocumentNumberGenerator, GarageProvisioningService,
 * dan PaymentProofStorage; dipakai RentalController.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RentalService
{
    public function __construct(
        private readonly RentalRepository $rentalRepo,
        private readonly GarageRepository $garageRepo,
        private readonly PenyewaRepository $penyewaRepo,
        private readonly PricingContext $pricing,
        private readonly RentalTypeResolver $resolver,
        private readonly DocumentNumberGenerator $numbering,
        private readonly VehicleRepository $vehicleRepo,
        private readonly GarageProvisioningService $garasi,
        private readonly PaymentProofStorage $storage,
        private readonly NomorPlatFormatter $formatPlat = new NomorPlatFormatter(),
    ) {
    }

    /**
     * Garasi yang tersedia untuk tipe kendaraan pada tanggal tertentu.
     *
     * @param  VehicleType  $tipeKendaraan  tipe kendaraan, menentukan tipe garasi yang diizinkan
     * @param  CarbonInterface  $tanggal  tanggal sewa yang diperiksa
     * @return Collection<int, \App\Domain\Models\Garage> garasi tanpa sewa AKTIF pada tanggal itu
     */
    public function garasiTersedia(VehicleType $tipeKendaraan, CarbonInterface $tanggal): Collection
    {
        $this->garasi->sinkronkanStatus($tanggal);

        $hasil = new Collection();

        foreach ($tipeKendaraan->tipeGarasiDiizinkan() as $tipeGarasi) {
            $hasil = $hasil->concat($this->garageRepo->tersediaPada($tipeGarasi, $tanggal, $tanggal));
        }

        return $hasil->values();
    }

    /**
     * Riwayat sewa milik penyewa, terbaru lebih dulu. Status disinkronkan
     * lebih dulu agar sewa yang sudah lewat tampil Selesai.
     *
     * @param  int  $penyewaId  id penyewa pemilik sewa
     * @return Collection<int, Rental> riwayat sewa beserta garasi, kendaraan, pembayaran, dan perpanjangan
     */
    public function riwayat(int $penyewaId): Collection
    {
        $this->garasi->sinkronkanStatus(CarbonImmutable::today());

        return $this->rentalRepo->riwayatPenyewa($penyewaId);
    }

    /**
     * Kendaraan yang pernah diparkir penyewa, untuk pilihan di form sewa baru
     * (terakhir diparkir lebih dulu). Kendaraan yang sedang disewa hari ini
     * ditandai `sedang_disewa`. Sepeda tanpa plat dengan merek dan tipe sama
     * hanya diambil sekali, karena setiap sewa sepeda membuat record baru.
     *
     * @param  int  $penyewaId  id penyewa pemilik kendaraan
     * @return Collection<int, Vehicle> kendaraan unik yang pernah diparkir penyewa
     */
    public function kendaraanTersimpan(int $penyewaId): Collection
    {
        return $this->vehicleRepo->pernahDiparkirPenyewa($penyewaId, CarbonImmutable::today())
            ->unique(fn (Vehicle $k) => $k->nomor_plat ?? 'sepeda:'.mb_strtolower("{$k->merek}|{$k->tipe}"))
            ->values();
    }

    /**
     * Estimasi periode dan biaya tanpa menyimpan apa pun.
     *
     * @param  array{penyewaId: int, tipeKendaraan: VehicleType, nomorPlat: ?string, merek: string, tipe: string, kapasitasBateraiKwh: ?float, sisaBateraiKwh: ?float, tipeSewa: RentalType, jumlahHari: int, garageId: string}  $data  isian form sewa; kapasitas dan sisa baterai dalam kWh
     * @return RentalEstimate periode sewa yang dimulai hari ini beserta rincian biayanya
     *
     * @throws GarasiTidakTersediaException jika garasi tidak ditemukan
     */
    public function hitungEstimasi(array $data): RentalEstimate
    {
        $periode = $this->resolver->tentukan($data['tipeSewa'], $data['jumlahHari'], CarbonImmutable::today());

        $garasi = $this->garageRepo->cariId($data['garageId'])
            ?? throw new GarasiTidakTersediaException('Garasi tidak ditemukan.', ['garage_id' => $data['garageId']]);

        $kendaraan = (new Vehicle())->forceFill([
            'type' => $data['tipeKendaraan'],
            'kapasitas_baterai_kwh' => $data['tipeKendaraan']->wajibInfoBaterai() ? $data['kapasitasBateraiKwh'] : null,
        ]);

        $biaya = $this->pricing
            ->untukTipeSewa($periode->tipeSewa)
            ->hitung($garasi, $kendaraan, (float) ($data['sisaBateraiKwh'] ?? 0), $periode);

        return new RentalEstimate($periode, $biaya);
    }

    /**
     * Membuat sewa baru berikut pembayaran awalnya.
     *
     * @param  array{penyewaId: int, tipeKendaraan: VehicleType, nomorPlat: ?string, merek: string, tipe: string, kapasitasBateraiKwh: ?float, sisaBateraiKwh: ?float, tipeSewa: RentalType, jumlahHari: int, garageId: string}  $data  isian form sewa; kapasitas dan sisa baterai dalam kWh
     * @param  UploadedFile  $buktiBayar  bukti bayar sewa awal
     * @return Rental sewa baru berstatus AKTIF
     *
     * @throws PenyewaBelumTerverifikasiException jika penyewa belum APPROVED
     * @throws ValidationException jika sisa baterai tidak valid, atau plat dan kapasitas baterai wajib tetapi kosong atau tidak sesuai
     * @throws \App\Exceptions\BuktiBayarTidakValidException jika berkas bukti bayar tidak valid
     * @throws KendaraanTidakCocokException jika tipe kendaraan tidak cocok dengan garasi
     * @throws GarasiTidakTersediaException jika garasi tidak tersedia
     * @throws KendaraanSedangDisewaException jika kendaraan masih aktif di garasi lain
     * @throws NomorPlatDipakaiPenyewaLainException jika plat milik penyewa lain
     */
    public function buatSewa(array $data, UploadedFile $buktiBayar): Rental
    {
        $hariIni = CarbonImmutable::today();

        $penyewa = $this->penyewaRepo->cariId($data['penyewaId']);

        if ($penyewa === null || ! $penyewa->isVerified()) {
            throw new PenyewaBelumTerverifikasiException(
                'Akun belum diverifikasi sehingga belum dapat mengajukan sewa.',
                ['penyewa_id' => $data['penyewaId']],
            );
        }

        $this->validasiBaterai($data);

        $periode = $this->resolver->tentukan($data['tipeSewa'], $data['jumlahHari'], $hariIni);

        $path = $this->storage->simpan($buktiBayar, 'bukti-bayar/'.$hariIni->format('Y/m'));

        try {
            return DB::transaction(fn () => $this->simpanDalamTransaksi($data, $penyewa, $periode, $path, $hariIni));
        } catch (Throwable $e) {
            $this->storage->hapus($path);

            throw $e;
        }
    }

    /**
     * Cek dini nomor plat saat diketik di form sewa, dengan aturan yang sama
     * seperti tentukanKendaraan(): struktur plat Indonesia, plat milik
     * penyewa lain, dan kendaraan yang masih disewa hari ini. Sewa selalu
     * dimulai hari ini, jadi sewa aktif hari ini pasti bertabrakan. Hanya
     * membantu pengguna; buatSewa() tetap mengecek ulang.
     *
     * @param  int  $penyewaId  id penyewa yang mengisi form
     * @param  string  $plat  nomor plat sesuai isian pengguna
     * @param  CarbonInterface  $hariIni  tanggal acuan, biasanya hari ini
     * @return array{nomor_plat: ?string, pesan: ?string} plat format simpan dan pesan galat (null bila aman)
     */
    public function cekNomorPlat(int $penyewaId, string $plat, CarbonInterface $hariIni): array
    {
        $normal = $this->formatPlat->normalisasi($plat);

        if ($normal === null) {
            return ['nomor_plat' => null, 'pesan' => NomorPlatFormatter::PESAN];
        }

        $ada = $this->vehicleRepo->cariNomorPlat($normal);
        $pesan = null;

        if ($ada !== null && ! $ada->dimilikiOleh($penyewaId)) {
            $pesan = 'Nomor plat sudah terdaftar atas penyewa lain.';
        } elseif ($ada !== null && $this->rentalRepo->kendaraanSedangDisewa($ada->id, $hariIni, $hariIni)) {
            $pesan = 'Kendaraan sedang disewa di garasi lain.';
        }

        return ['nomor_plat' => $normal, 'pesan' => $pesan];
    }

    /**
     * @throws ValidationException jika mobil listrik tanpa sisa baterai atau tidak lolos BatteryLevelRule
     */
    private function validasiBaterai(array $data): void
    {
        if (! $data['tipeKendaraan']->wajibInfoBaterai()) {
            return;
        }

        $aturan = new BatteryLevelRule();

        if ($data['sisaBateraiKwh'] === null || ! $aturan->lolos((float) $data['kapasitasBateraiKwh'], $data['sisaBateraiKwh'])) {
            throw ValidationException::withMessages(['sisa_baterai_kwh' => $aturan->pesan()]);
        }
    }

    /**
     * Mengunci garasi, memeriksa kecocokan dan ketersediaan, lalu menyimpan sewa, pembayaran, dan status USED.
     */
    private function simpanDalamTransaksi(
        array $data,
        Penyewa $penyewa,
        RentalPeriod $periode,
        string $path,
        CarbonImmutable $hariIni,
    ): Rental {
        $garasi = $this->garageRepo->cariId($data['garageId'], true)
            ?? throw new GarasiTidakTersediaException('Garasi tidak ditemukan.', ['garage_id' => $data['garageId']]);

        $aturanKecocokan = new GarageCompatibilityRule();

        if (! $aturanKecocokan->lolos($data['tipeKendaraan'], $garasi->type)) {
            throw new KendaraanTidakCocokException($aturanKecocokan->pesan(), [
                'kendaraan' => $data['tipeKendaraan']->value,
                'garasi' => $garasi->type->value,
            ]);
        }

        if (! $garasi->tersediaPada($periode->tanggalMulai, $periode->tanggalSelesai)) {
            throw new GarasiTidakTersediaException('Garasi tidak tersedia pada tanggal tersebut.', [
                'garage_id' => $garasi->id,
            ]);
        }

        $kendaraan = $this->tentukanKendaraan($penyewa->id, $data);

        if ($this->rentalRepo->kendaraanSedangDisewa($kendaraan->id, $periode->tanggalMulai, $periode->tanggalSelesai)) {
            throw new KendaraanSedangDisewaException('Kendaraan sedang disewa di garasi lain.', [
                'vehicle_id' => $kendaraan->id,
            ]);
        }

        $sisaBaterai = (float) ($data['sisaBateraiKwh'] ?? 0);
        $biaya = $this->pricing->untukTipeSewa($periode->tipeSewa)->hitung($garasi, $kendaraan, $sisaBaterai, $periode);

        $sewa = $this->rentalRepo->simpan((new Rental())->forceFill([
            'nomor_nota' => $this->numbering->nomorNota($hariIni),
            'penyewa_id' => $penyewa->id,
            'garage_id' => $garasi->id,
            'vehicle_id' => $kendaraan->id,
            'tipe_sewa' => $periode->tipeSewa,
            'jumlah_hari' => $periode->jumlahHariTersimpan(),
            'tanggal_mulai' => $periode->tanggalMulai,
            'tanggal_selesai' => $periode->tanggalSelesai,
            'biaya_sewa' => $biaya->biayaSewa,
            'sisa_baterai_snapshot' => $kendaraan->isElectric() ? $sisaBaterai : null,
            'jumlah_listrik_kwh' => $biaya->jumlahListrikKwh,
            'biaya_listrik' => $biaya->biayaListrik,
            'total_biaya' => $biaya->total,
            'status' => RentalStatus::AKTIF,
        ]));

        $this->rentalRepo->simpanPembayaran((new Payment())->forceFill([
            'rental_id' => $sewa->id,
            'rental_extension_id' => null,
            'jumlah' => $biaya->total,
            'path_bukti_bayar' => $path,
            'diunggah_oleh' => $penyewa->user_id,
            'diunggah_pada' => $hariIni->setTimeFrom(now()),
        ]));

        $this->garageRepo->ubahStatus($garasi->id, GarageStatus::USED);

        return $sewa;
    }

    /**
     * Menentukan record kendaraan untuk sewa baru (asumsi nomor 12 dan 24).
     * Plat dinormalisasi ke format simpan ("DD 1234 AB") sebelum dicari; plat
     * yang tidak sesuai struktur Indonesia ditolak. Kendaraan terdaftar atas
     * penyewa yang sama dipakai ulang dan diperbarui; atas penyewa lain
     * ditolak. Sepeda tanpa plat selalu dibuat baru.
     *
     * @throws ValidationException jika plat atau kapasitas baterai wajib tetapi kosong, atau plat tidak sesuai struktur
     * @throws NomorPlatDipakaiPenyewaLainException jika plat milik penyewa lain
     */
    private function tentukanKendaraan(int $penyewaId, array $data): Vehicle
    {
        $this->validasiKendaraan($data);

        if (! $data['tipeKendaraan']->wajibNomorPlat()) {
            return $this->vehicleRepo->simpan($this->kendaraanBaru($penyewaId, $data, null));
        }

        $plat = $this->formatPlat->normalisasi((string) $data['nomorPlat'])
            ?? throw ValidationException::withMessages(['nomor_plat' => NomorPlatFormatter::PESAN]);

        $ada = $this->vehicleRepo->cariNomorPlat($plat);

        if ($ada === null) {
            return $this->vehicleRepo->simpan($this->kendaraanBaru($penyewaId, $data, $plat));
        }

        if (! $ada->dimilikiOleh($penyewaId)) {
            throw new NomorPlatDipakaiPenyewaLainException(
                'Nomor plat sudah terdaftar atas penyewa lain.',
                ['nomor_plat' => $plat],
            );
        }

        $ada->merek = $data['merek'];
        $ada->tipe = $data['tipe'];
        $ada->kapasitas_baterai_kwh = $data['kapasitasBateraiKwh'];

        return $this->vehicleRepo->simpan($ada);
    }

    /**
     * @throws ValidationException jika plat atau kapasitas baterai wajib tetapi kosong
     */
    private function validasiKendaraan(array $data): void
    {
        $tipe = $data['tipeKendaraan'];

        if ($tipe->wajibNomorPlat() && trim((string) $data['nomorPlat']) === '') {
            throw ValidationException::withMessages([
                'nomor_plat' => 'Nomor plat wajib diisi untuk tipe kendaraan ini.',
            ]);
        }

        if ($tipe->wajibInfoBaterai() && ($data['kapasitasBateraiKwh'] === null || $data['kapasitasBateraiKwh'] <= 0)) {
            throw ValidationException::withMessages([
                'kapasitas_baterai_kwh' => 'Kapasitas baterai wajib diisi untuk mobil listrik.',
            ]);
        }
    }

    private function kendaraanBaru(int $penyewaId, array $data, ?string $plat): Vehicle
    {
        return (new Vehicle())->forceFill([
            'penyewa_id' => $penyewaId,
            'type' => $data['tipeKendaraan'],
            'nomor_plat' => $plat,
            'merek' => $data['merek'],
            'tipe' => $data['tipe'],
            'kapasitas_baterai_kwh' => $data['tipeKendaraan']->wajibInfoBaterai() ? $data['kapasitasBateraiKwh'] : null,
        ]);
    }
}
