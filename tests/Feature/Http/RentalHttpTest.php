<?php

namespace Tests\Feature\Http;

use App\Domain\Enums\RentalType;
use App\Domain\Models\Garage;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\Vehicle;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Support\NomorPlatFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pengajuan sewa lewat HTTP (black-box, bagian G.5).
 */
class RentalHttpTest extends TestCase
{
    use RefreshDatabase;

    private Penyewa $penyewa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bekukanWaktu('2026-11-21 09:30:00');
        Storage::fake(PaymentProofStorage::DISK);

        Garage::factory()->car('C001')->create();
        $this->penyewa = Penyewa::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function bekukanWaktu(string $waktu): void
    {
        Carbon::setTestNow($waktu);
        CarbonImmutable::setTestNow($waktu);
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'tipe_kendaraan' => 'MOBIL',
            'nomor_plat' => 'DD 1234 AB',
            'merek' => 'Toyota',
            'tipe' => 'Avanza',
            'tipe_sewa' => 'HARIAN',
            'jumlah_hari' => 3,
            'garage_id' => 'C001',
            'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
        ], $ubah);
    }

    private function kirim(array $ubah = [], ?Penyewa $penyewa = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(($penyewa ?? $this->penyewa)->user)
            ->from(route('sewa.create'))
            ->post(route('sewa.store'), $this->isian($ubah));
    }

    public function test_sewa_valid_tersimpan(): void
    {
        $this->kirim()
            ->assertRedirect(route('sewa.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status')
            ->assertSessionHas('nomor_nota', 'SEW-20261121-0001')
            ->assertSessionHas('total_biaya', 75000.0);

        $sewa = Rental::query()->sole();
        $this->assertSame(75000.0, $sewa->total_biaya);
        $this->assertSame($this->penyewa->id, $sewa->penyewa_id);
    }

    /**
     * FT-02 - HARIAN 28 hari pada 10 Feb 2026 tersimpan sebagai BULANAN dengan biaya bulanan.
     */
    public function test_harian_28_hari_februari_2026_menjadi_bulanan(): void
    {
        $this->bekukanWaktu('2026-02-10 09:00:00');

        $this->kirim(['jumlah_hari' => 28])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('tipe_sewa', 'BULANAN')
            ->assertSessionHas('total_biaya', 625000.0);

        $sewa = Rental::query()->sole();
        $this->assertSame(RentalType::BULANAN, $sewa->tipe_sewa);
        $this->assertSame(0, $sewa->jumlah_hari);
        $this->assertSame('2026-03-09', $sewa->tanggal_selesai->toDateString());
    }

    /**
     * FT-08 - HARIAN 40 hari dikirim langsung (melewati Alpine.js) menjadi BULANAN 30 hari dengan notifikasi.
     */
    public function test_harian_40_hari_menjadi_bulanan_dengan_notifikasi(): void
    {
        $this->kirim(['jumlah_hari' => 40])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notifikasi', 'Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan (30 hari).');

        $this->assertSame('2026-12-20', Rental::query()->sole()->tanggal_selesai->toDateString());
    }

    /**
     * FT-06 - Bukti bayar berformat PDF ditolak.
     */
    public function test_bukti_bayar_pdf_ditolak(): void
    {
        $this->kirim(['bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['bukti_bayar' => 'Bukti bayar harus berupa gambar JPG, JPEG, atau PNG.']);

        $this->assertDatabaseCount('rentals', 0);
    }

    /**
     * FT-14 - Bukti bayar tepat 5 MB lolos.
     */
    public function test_bukti_bayar_tepat_5_mb_lolos(): void
    {
        $this->kirim(['bukti_bayar' => UploadedFile::fake()->image('bukti.jpg')->size(5120)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('rentals', 1);
    }

    /**
     * FT-15 - Bukti bayar 6 MB ditolak.
     */
    public function test_bukti_bayar_6_mb_ditolak(): void
    {
        $this->kirim(['bukti_bayar' => UploadedFile::fake()->image('bukti.jpg')->size(6144)])
            ->assertSessionHasErrors(['bukti_bayar' => 'Ukuran bukti bayar melebihi 5 MB.']);
    }

    /**
     * FT-07 - Dua penyewa memilih C001 pada hari yang sama: penyimpanan kedua gagal.
     */
    public function test_penyewa_kedua_pada_garasi_yang_sama_gagal(): void
    {
        $lain = Penyewa::factory()->approved()->create();

        $this->kirim(['nomor_plat' => 'DD 1111 AA'])->assertSessionHasNoErrors();
        $this->kirim(['nomor_plat' => 'DD 2222 BB'], $lain)
            ->assertRedirect(route('sewa.create'))
            ->assertSessionHasErrors(['umum' => 'Garasi tidak tersedia pada tanggal tersebut.']);

        $this->assertDatabaseCount('rentals', 1);
    }

    /**
     * FT-12 - Nomor plat yang terdaftar atas penyewa lain ditolak dengan pesan plat sudah dipakai.
     */
    public function test_plat_milik_penyewa_lain_ditolak(): void
    {
        Vehicle::factory()->create(['nomor_plat' => 'DD 1234 AB']);

        $this->kirim(['nomor_plat' => 'dd 1234 ab'])
            ->assertSessionHasErrors(['nomor_plat' => 'Nomor plat sudah terdaftar atas penyewa lain.']);

        $this->assertDatabaseCount('rentals', 0);
    }

    /**
     * FT-18 - Kendaraan yang masih aktif di garasi lain ditolak dengan pesan kendaraan sedang disewa.
     */
    public function test_kendaraan_aktif_di_garasi_lain_ditolak(): void
    {
        Garage::factory()->car('C002')->create();

        $this->kirim()->assertSessionHasNoErrors();
        $this->kirim(['garage_id' => 'C002'])
            ->assertSessionHasErrors(['umum' => 'Kendaraan sedang disewa di garasi lain.']);
    }

    public function test_tipe_kendaraan_tidak_cocok_dengan_garasi_ditolak(): void
    {
        $this->kirim(['tipe_kendaraan' => 'SEPEDA_MOTOR'])
            ->assertSessionHasErrors(['garage_id' => 'Tipe kendaraan tidak cocok dengan tipe garasi yang dipilih.']);
    }

    public function test_sisa_baterai_melebihi_kapasitas_ditolak(): void
    {
        $this->kirim([
            'tipe_kendaraan' => 'MOBIL_LISTRIK',
            'kapasitas_baterai_kwh' => 60,
            'sisa_baterai_kwh' => 70,
        ])->assertSessionHasErrors('sisa_baterai_kwh');
    }

    public function test_isian_wajib_mengikuti_tipe_kendaraan(): void
    {
        $this->kirim(['tipe_kendaraan' => 'SEPEDA_MOTOR', 'nomor_plat' => null])
            ->assertSessionHasErrors('nomor_plat');

        $this->kirim(['tipe_kendaraan' => 'MOBIL_LISTRIK'])
            ->assertSessionHasErrors(['kapasitas_baterai_kwh', 'sisa_baterai_kwh']);
    }

    public function test_sepeda_tanpa_plat_diterima(): void
    {
        Garage::factory()->bike('S001')->create();

        $this->kirim(['tipe_kendaraan' => 'SEPEDA', 'nomor_plat' => null, 'garage_id' => 'S001'])
            ->assertSessionHasNoErrors();

        $this->assertNull(Vehicle::query()->sole()->nomor_plat);
    }

    public function test_garasi_yang_tidak_ada_ditolak(): void
    {
        $this->kirim(['garage_id' => 'C999'])->assertSessionHasErrors('garage_id');
    }

    public function test_garasi_tersedia_mengembalikan_json_sesuai_tipe_kendaraan(): void
    {
        Garage::factory()->electric('E001')->create();
        Garage::factory()->bike('S001')->create();

        $this->actingAs($this->penyewa->user)
            ->getJson(route('sewa.garasi-tersedia', ['tipe_kendaraan' => 'MOBIL_LISTRIK']))
            ->assertOk()
            ->assertJsonPath('data.0.id', 'C001')
            ->assertJsonPath('data.0.harga_sewa_bulanan', 625000)
            ->assertJsonPath('data.1.id', 'E001')
            ->assertJsonPath('data.1.harga_listrik_per_kwh', 2500)
            ->assertJsonCount(2, 'data');
    }

    public function test_estimasi_harian_31_hari_menjadi_bulanan(): void
    {
        $this->actingAs($this->penyewa->user)
            ->getJson(route('sewa.estimasi', [
                'tipe_kendaraan' => 'MOBIL',
                'tipe_sewa' => 'HARIAN',
                'jumlah_hari' => 31,
                'garage_id' => 'C001',
            ]))
            ->assertOk()
            ->assertJsonPath('tipe_sewa', 'BULANAN')
            ->assertJsonPath('jumlah_hari', 30)
            ->assertJsonPath('melebihi_batas', true)
            ->assertJsonPath('tanggal_selesai', '2026-12-20')
            ->assertJsonPath('total', 625000)
            ->assertJsonPath('pesan', 'Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan (30 hari).');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_estimasi_dengan_isian_tidak_valid_mengembalikan_422(): void
    {
        $this->actingAs($this->penyewa->user)
            ->getJson(route('sewa.estimasi', [
                'tipe_kendaraan' => 'MOBIL',
                'tipe_sewa' => 'HARIAN',
                'jumlah_hari' => 0,
                'garage_id' => 'C001',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jumlah_hari');
    }
    public function test_nomor_plat_disimpan_dengan_format_standar(): void
    {
        $this->kirim(['nomor_plat' => 'dd1234ab'])->assertSessionHasNoErrors();

        $this->assertSame('DD 1234 AB', Vehicle::query()->sole()->nomor_plat);
    }

    /**
     * Struktur plat Indonesia: kode wilayah 1-2 huruf, nomor 1-4 angka, seri 0-3 huruf.
     *
     * @return array<string, array{0: string}>
     */
    public static function platSalahFormat(): array
    {
        return [
            'kode wilayah 3 huruf' => ['DDD 1234 AB'],
            'nomor 5 angka' => ['DD 12345 AB'],
            'seri 4 huruf' => ['DD 1234 ABCD'],
            'tanpa nomor' => ['DD AB'],
        ];
    }

    #[DataProvider('platSalahFormat')]
    public function test_nomor_plat_salah_format_ditolak(string $plat): void
    {
        $this->kirim(['nomor_plat' => $plat])
            ->assertSessionHasErrors(['nomor_plat' => NomorPlatFormatter::PESAN]);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_cek_plat_saat_diketik(): void
    {
        Vehicle::factory()->create(['nomor_plat' => 'DD 9999 ZZ']);

        $this->actingAs($this->penyewa->user)
            ->getJson(route('sewa.cek-plat', ['nomor_plat' => 'dd1234ab']))
            ->assertOk()
            ->assertExactJson(['nomor_plat' => 'DD 1234 AB', 'pesan' => null]);

        $this->actingAs($this->penyewa->user)
            ->getJson(route('sewa.cek-plat', ['nomor_plat' => 'dd 9999 zz']))
            ->assertOk()
            ->assertJson(['pesan' => 'Nomor plat sudah terdaftar atas penyewa lain.']);
    }

    public function test_cek_plat_hanya_untuk_penyewa_terverifikasi(): void
    {
        $this->getJson(route('sewa.cek-plat', ['nomor_plat' => 'DD 1234 AB']))->assertUnauthorized();

        $pending = Penyewa::factory()->create();
        $this->actingAs($pending->user)
            ->get(route('sewa.cek-plat', ['nomor_plat' => 'DD 1234 AB']))
            ->assertRedirect();
    }

    public function test_form_sewa_memakai_cek_saat_diketik(): void
    {
        $this->actingAs($this->penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertSee('cek-plat', false)
            ->assertSee(':disabled="!valid"', false);
    }
}
