<?php

namespace Database\Seeders;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\RegistrationStatus;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Domain\Enums\UserRole;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\CarGarage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use App\Domain\Models\Vehicle;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\RentalTypeResolver;
use App\Support\PaymentProofStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh untuk demo laporan dan halaman admin: tiga penyewa APPROVED,
 * satu PENDING, kendaraan, dan sewa yang tersebar sepanjang tahun relatif
 * terhadap hari ini. Biaya dihitung dengan RentalTypeResolver dan
 * PricingContext yang sama dengan aplikasi. Semua nama dan nomor fiktif.
 * Tidak melakukan apa pun bila sudah ada sewa.
 */
class DemoDataSeeder extends Seeder
{
    public const PATH_BUKTI = 'bukti-bayar/demo/contoh-bukti.png';

    /**
     * Rencana sewa: [hari sebelum hari ini, indeks kendaraan, kode garasi, tipe sewa, jumlah hari].
     *
     * @var array<int, array{0: int, 1: int, 2: string, 3: RentalType, 4: int}>
     */
    private const RENCANA = [
        [300, 0, 'C001', RentalType::HARIAN, 5],
        [270, 1, 'S001', RentalType::HARIAN, 3],
        [240, 2, 'E001', RentalType::HARIAN, 4],
        [205, 0, 'C002', RentalType::BULANAN, 0],
        [160, 3, 'S002', RentalType::HARIAN, 2],
        [130, 2, 'E002', RentalType::HARIAN, 6],
        [100, 1, 'S001', RentalType::HARIAN, 7],
        [75, 0, 'C001', RentalType::HARIAN, 10],
        [50, 2, 'C003', RentalType::HARIAN, 3],
        [30, 3, 'S003', RentalType::HARIAN, 5],
        [12, 1, 'S002', RentalType::HARIAN, 4],
        // Dua sewa terakhir masih berjalan hari ini.
        [2, 0, 'C004', RentalType::HARIAN, 5],
        [0, 2, 'E001', RentalType::HARIAN, 3],
    ];

    public function run(RentalTypeResolver $resolver, PricingContext $pricing): void
    {
        if (Rental::query()->exists() || ! CarGarage::query()->exists()) {
            return;
        }

        $hariIni = CarbonImmutable::today();
        $admin = User::query()->where('role', UserRole::ADMIN->value)->firstOrFail();
        $this->simpanBuktiContoh();

        DB::transaction(function () use ($hariIni, $admin, $resolver, $pricing) {
            [$penyewa, $kendaraan] = $this->buatPenyewaDanKendaraan($admin);
            $garasiDipakai = [];

            foreach (self::RENCANA as $urut => [$hariLalu, $indeksKendaraan, $kodeGarasi, $tipeSewa, $jumlahHari]) {
                $mulai = $hariIni->subDays($hariLalu);
                $kend = $kendaraan[$indeksKendaraan];
                $garasi = CarGarage::query()->findOrFail($kodeGarasi);
                $periode = $resolver->tentukan($tipeSewa, $jumlahHari, $mulai);
                $sisaBaterai = $kend->isElectric() ? 20.0 : 0.0;
                $biaya = $pricing->untukTipeSewa($periode->tipeSewa)->hitung($garasi, $kend, $sisaBaterai, $periode);
                $aktif = $periode->mencakup($hariIni);

                $sewa = Rental::query()->forceCreate([
                    'nomor_nota' => 'SEW-'.$mulai->format('Ymd').'-0001',
                    'penyewa_id' => $kend->penyewa_id,
                    'garage_id' => $garasi->id,
                    'vehicle_id' => $kend->id,
                    'tipe_sewa' => $periode->tipeSewa,
                    'jumlah_hari' => $periode->jumlahHariTersimpan(),
                    'tanggal_mulai' => $periode->tanggalMulai,
                    'tanggal_selesai' => $periode->tanggalSelesai,
                    'biaya_sewa' => $biaya->biayaSewa,
                    'sisa_baterai_snapshot' => $kend->isElectric() ? $sisaBaterai : null,
                    'jumlah_listrik_kwh' => $biaya->jumlahListrikKwh,
                    'biaya_listrik' => $biaya->biayaListrik,
                    'total_biaya' => $biaya->total,
                    'status' => $aktif ? RentalStatus::AKTIF : RentalStatus::SELESAI,
                ]);

                $this->bayar($sewa, null, $biaya->total, $penyewa[$kend->penyewa_id]->user_id, $mulai);

                if ($aktif) {
                    $garasiDipakai[] = $garasi->id;
                }

                // Satu contoh perpanjangan pada sewa harian pertama yang sudah selesai.
                if ($urut === 0) {
                    $this->perpanjang($sewa, $garasi->harga_sewa_harian, $admin->id);
                }
            }

            CarGarage::query()->whereKey($garasiDipakai)->update(['status' => GarageStatus::USED->value]);
        });
    }

    /**
     * @return array{0: array<int, Penyewa>, 1: array<int, Vehicle>}
     */
    private function buatPenyewaDanKendaraan(User $admin): array
    {
        $data = [
            ['Budi Santoso', 'budi@garasi.test', '7371010101900001', VehicleType::MOBIL, 'DD 1001 AA', 'Toyota', 'Avanza', null],
            ['Sari Wulandari', 'sari@garasi.test', '7371010101900002', VehicleType::SEPEDA_MOTOR, 'DD 2002 BB', 'Honda', 'Vario', null],
            ['Andi Pratama', 'andi@garasi.test', '7371010101900003', VehicleType::MOBIL_LISTRIK, 'DD 3003 CC', 'Hyundai', 'Ioniq 5', 72.6],
        ];

        $penyewa = [];
        $kendaraan = [];

        foreach ($data as [$nama, $email, $nik, $tipe, $plat, $merek, $model, $kapasitas]) {
            $p = $this->buatPenyewa($nama, $email, $nik, RegistrationStatus::APPROVED, $admin);
            $penyewa[$p->id] = $p;
            $kendaraan[] = Vehicle::query()->forceCreate([
                'penyewa_id' => $p->id,
                'type' => $tipe,
                'nomor_plat' => $plat,
                'merek' => $merek,
                'tipe' => $model,
                'kapasitas_baterai_kwh' => $kapasitas,
            ]);
        }

        // Kendaraan keempat: sepeda tanpa plat milik penyewa pertama.
        $kendaraan[] = Vehicle::query()->forceCreate([
            'penyewa_id' => array_key_first($penyewa),
            'type' => VehicleType::SEPEDA,
            'nomor_plat' => null,
            'merek' => 'Polygon',
            'tipe' => 'Heist',
        ]);

        $this->buatPenyewa('Dewi Lestari', 'dewi@garasi.test', '7371010101900004', RegistrationStatus::PENDING, null);

        return [$penyewa, $kendaraan];
    }

    private function buatPenyewa(string $nama, string $email, string $nik, RegistrationStatus $status, ?User $admin): Penyewa
    {
        $user = User::query()->forceCreate([
            'name' => $nama,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::PENYEWA,
        ]);

        return Penyewa::query()->forceCreate([
            'user_id' => $user->id,
            'nik' => $nik,
            'nama_lengkap' => $nama,
            'nomor_telepon' => '+62812'.substr($nik, -8),
            'status' => $status,
            'verified_by' => $admin?->id,
            'verified_at' => $admin ? now() : null,
        ]);
    }

    private function perpanjang(Rental $sewa, float $hargaHarian, int $adminId): void
    {
        $lama = $sewa->tanggal_selesai->toImmutable();
        $baru = $lama->addDays(2);

        $perpanjangan = RentalExtension::query()->forceCreate([
            'rental_id' => $sewa->id,
            'nomor_perpanjang' => 'PJG-'.$lama->format('Ymd').'-0001',
            'tanggal_selesai_lama' => $lama,
            'tanggal_selesai_baru' => $baru,
            'tambahan_hari' => 2,
            'biaya_tambahan' => $hargaHarian * 2,
            'diproses_oleh' => $adminId,
            // Diproses pada hari terakhir sewa lama, sehingga laporan
            // mencatat biayanya pada tanggal itu.
            'created_at' => $lama->setTime(10, 0),
        ]);

        $sewa->forceFill(['tanggal_selesai' => $baru])->save();
        $this->bayar($sewa, $perpanjangan->id, $hargaHarian * 2, $adminId, $lama);
    }

    private function bayar(Rental $sewa, ?int $perpanjanganId, float $jumlah, int $diunggahOleh, CarbonImmutable $tanggal): void
    {
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id,
            'rental_extension_id' => $perpanjanganId,
            'jumlah' => $jumlah,
            'path_bukti_bayar' => self::PATH_BUKTI,
            'diunggah_oleh' => $diunggahOleh,
            'diunggah_pada' => $tanggal->setTime(10, 0),
        ]);
    }

    /**
     * Satu gambar contoh dipakai bersama oleh semua pembayaran demo.
     */
    private function simpanBuktiContoh(): void
    {
        $gambar = imagecreatetruecolor(320, 200);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 231, 241, 255));
        imagestring($gambar, 5, 70, 90, 'BUKTI BAYAR CONTOH', imagecolorallocate($gambar, 10, 61, 145));

        ob_start();
        imagepng($gambar);
        Storage::disk(PaymentProofStorage::DISK)->put(self::PATH_BUKTI, (string) ob_get_clean());
    }
}
