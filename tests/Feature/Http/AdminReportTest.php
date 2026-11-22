<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Payment;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_laporan_bulanan_default_tahun_berjalan(): void
    {
        Rental::factory()->periode('2026-03-10', '2026-03-12')->create(['total_biaya' => 50000, 'nomor_nota' => 'SEW-20260310-0001']);

        $this->actingAs($this->admin)
            ->get(route('admin.laporan.bulanan'))
            ->assertOk()
            ->assertViewIs('admin.laporan.bulanan')
            ->assertViewHas('tahun', 2026)
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 12 && $baris[2]['pendapatan'] === 50000.0)
            ->assertViewHas('total', 50000.0);
    }

    /**
     * Membuat satu sewa November beserta pembayaran awal, satu perpanjangan
     * beserta pembayarannya, dan mengembalikan sewanya.
     */
    private function sewaDenganPerpanjangan(): Rental
    {
        $sewa = Rental::factory()->periode('2026-11-10', '2026-11-12')
            ->create(['total_biaya' => 50000, 'nomor_nota' => 'SEW-20261110-0001']);
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id, 'rental_extension_id' => null, 'jumlah' => 50000,
            'path_bukti_bayar' => 'bukti-bayar/2026/11/awal.png', 'diunggah_oleh' => $sewa->penyewa->user_id,
            'diunggah_pada' => '2026-11-10 10:00:00',
        ]);
        $perpanjangan = RentalExtension::query()->forceCreate([
            'rental_id' => $sewa->id, 'nomor_perpanjang' => 'PJG-20261112-0001',
            'tanggal_selesai_lama' => '2026-11-12', 'tanggal_selesai_baru' => '2026-11-14', 'tambahan_hari' => 2,
            'biaya_tambahan' => 20000, 'diproses_oleh' => $this->admin->id, 'created_at' => '2026-11-12 09:00:00',
        ]);
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id, 'rental_extension_id' => $perpanjangan->id, 'jumlah' => 20000,
            'path_bukti_bayar' => 'bukti-bayar/2026/11/pjg.png', 'diunggah_oleh' => $this->admin->id,
            'diunggah_pada' => '2026-11-12 09:00:00',
        ]);

        return $sewa;
    }

    public function test_keempat_laporan_menampilkan_rincian_transaksi_dan_bukti(): void
    {
        $this->sewaDenganPerpanjangan();
        $urlAwal = 'data-bukti="/storage/bukti-bayar/2026/11/awal.png"';
        $urlPerpanjangan = 'data-bukti="/storage/bukti-bayar/2026/11/pjg.png"';

        foreach (['admin.laporan.bulanan', 'admin.laporan.harian', 'admin.laporan.tipe-garasi', 'admin.laporan.penyewa'] as $rute) {
            $this->actingAs($this->admin)
                ->get(route($rute))
                ->assertOk()
                ->assertSee('SEW-20261110-0001')
                ->assertSee('PJG-20261112-0001')
                ->assertSee('Sewa baru')
                ->assertSee('Perpanjangan')
                ->assertSee($urlAwal, false)
                ->assertSee($urlPerpanjangan, false)
                ->assertSee('id="modal-bukti"', false);
        }
    }

    public function test_laporan_harian_februari_2028(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.harian', ['tahun' => 2028, 'bulan' => 2]))
            ->assertOk()
            ->assertViewHas('bulan', 2)
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 29);
    }

    public function test_laporan_per_tipe_garasi_default_bulan_berjalan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.tipe-garasi'))
            ->assertOk()
            ->assertViewHas('dari', fn ($dari) => $dari->toDateString() === '2026-11-01')
            ->assertViewHas('sampai', fn ($sampai) => $sampai->toDateString() === '2026-11-30')
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 3);
    }

    public function test_laporan_per_penyewa_dengan_rentang(): void
    {
        Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['total_biaya' => 75000]);

        $this->actingAs($this->admin)
            ->get(route('admin.laporan.penyewa', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 1)
            ->assertViewHas('total', 75000.0);
    }

    /**
     * Ekspor PDF dihapus atas keputusan pengguna: laporan hanya tampil sebagai
     * halaman, tanpa tombol unduh, dan parameter format diabaikan.
     */
    public function test_laporan_hanya_halaman_tanpa_unduh_pdf(): void
    {
        foreach (['admin.laporan.bulanan', 'admin.laporan.harian', 'admin.laporan.tipe-garasi', 'admin.laporan.penyewa'] as $rute) {
            $this->actingAs($this->admin)
                ->get(route($rute, ['format' => 'pdf']))
                ->assertOk()
                ->assertHeader('content-type', 'text/html; charset=utf-8')
                ->assertDontSee('Unduh PDF');
        }
    }

    public function test_rentang_terbalik_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.penyewa', ['dari' => '2026-11-30', 'sampai' => '2026-11-01']))
            ->assertSessionHasErrors('sampai');
    }

    public function test_bulan_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.harian', ['tahun' => 2026, 'bulan' => 13]))
            ->assertSessionHasErrors('bulan');
    }
}
