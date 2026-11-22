<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Garage;
use App\Domain\Models\Payment;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\RentalExtension;
use App\Domain\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman awal penyewa: riwayat sewanya (Aktif atau Selesai) dengan tombol
 * Sewa baru, dan rincian perpanjangan beserta bukti bayar.
 */
class RiwayatSewaTest extends TestCase
{
    use RefreshDatabase;

    private Penyewa $penyewa;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
        $this->penyewa = Penyewa::factory()->approved()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function sewa(string $nota, string $mulai, string $selesai, ?Penyewa $milik = null): Rental
    {
        return Rental::factory()->periode($mulai, $selesai)->create([
            'penyewa_id' => ($milik ?? $this->penyewa)->id,
            'nomor_nota' => $nota,
            'total_biaya' => 75000,
        ]);
    }

    public function test_riwayat_kosong_menampilkan_ajakan_dan_tombol_sewa_baru(): void
    {
        $this->actingAs($this->penyewa->user)
            ->get(route('sewa.index'))
            ->assertOk()
            ->assertViewIs('tenant.sewa.index')
            ->assertSee('Belum ada sewa')
            ->assertSee(route('sewa.create'));
    }

    public function test_hanya_sewa_milik_sendiri_terbaru_lebih_dulu(): void
    {
        $this->sewa('SEW-20261001-0001', '2026-10-01', '2026-10-03');
        $this->sewa('SEW-20261121-0001', '2026-11-21', '2026-11-23');
        $this->sewa('SEW-20261121-0002', '2026-11-21', '2026-11-23', Penyewa::factory()->approved()->create());

        $this->actingAs($this->penyewa->user)
            ->get(route('sewa.index'))
            ->assertOk()
            ->assertSeeInOrder(['SEW-20261121-0001', 'SEW-20261001-0001'])
            ->assertDontSee('SEW-20261121-0002');
    }

    public function test_sewa_yang_sudah_lewat_tampil_selesai_dan_yang_berjalan_aktif(): void
    {
        Garage::factory()->car('C009')->used()->create();
        $lewat = $this->sewa('SEW-20261110-0001', '2026-11-10', '2026-11-20');
        $lewat->update(['garage_id' => 'C009']);
        $this->sewa('SEW-20261121-0001', '2026-11-21', '2026-11-23');

        $this->actingAs($this->penyewa->user)
            ->get(route('sewa.index'))
            ->assertSeeInOrder(['SEW-20261121-0001', 'Aktif', 'SEW-20261110-0001', 'Selesai']);

        // Sinkronisasi ikut membebaskan garasi sewa yang sudah lewat.
        $this->assertDatabaseHas('garages', ['id' => 'C009', 'status' => 'AVAILABLE']);
    }

    public function test_baris_sewa_menampilkan_tombol_bukti_awal_dan_perpanjangan_serta_total_dibayar(): void
    {
        $sewa = $this->sewa('SEW-20261121-0001', '2026-11-21', '2026-11-25');
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id, 'rental_extension_id' => null, 'jumlah' => 75000,
            'path_bukti_bayar' => 'bukti-bayar/2026/11/awal.png', 'diunggah_oleh' => $this->penyewa->user_id,
            'diunggah_pada' => '2026-11-21 09:00:00',
        ]);
        $perpanjangan = RentalExtension::query()->forceCreate([
            'rental_id' => $sewa->id, 'nomor_perpanjang' => 'PJG-20261121-0001',
            'tanggal_selesai_lama' => '2026-11-23', 'tanggal_selesai_baru' => '2026-11-25', 'tambahan_hari' => 2,
            'biaya_tambahan' => 50000, 'diproses_oleh' => User::factory()->admin()->create()->id,
            'created_at' => '2026-11-21 10:00:00',
        ]);
        Payment::query()->forceCreate([
            'rental_id' => $sewa->id, 'rental_extension_id' => $perpanjangan->id, 'jumlah' => 50000,
            'path_bukti_bayar' => 'bukti-bayar/2026/11/pjg.png', 'diunggah_oleh' => $perpanjangan->diproses_oleh,
            'diunggah_pada' => '2026-11-21 10:00:00',
        ]);

        $this->actingAs($this->penyewa->user)
            ->get(route('sewa.index'))
            ->assertSee('Diperpanjang 1 kali')
            ->assertSee('Lihat bukti')
            ->assertSee('Perpanjangan 1')
            ->assertDontSee('id="sewa-'.$sewa->id.'"', false)
            ->assertSee('Rp125.000')
            ->assertSee('data-bukti="/storage/bukti-bayar/2026/11/awal.png"', false)
            ->assertSee('data-bukti="/storage/bukti-bayar/2026/11/pjg.png"', false)
            ->assertSee('id="modal-bukti"', false);
    }

    public function test_penyewa_belum_terverifikasi_tidak_dapat_membuka_riwayat(): void
    {
        $this->actingAs(Penyewa::factory()->create()->user)
            ->get(route('sewa.index'))
            ->assertRedirect(route('beranda'));
    }

    public function test_admin_tidak_dapat_membuka_riwayat_penyewa(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('sewa.index'))
            ->assertForbidden();
    }
}
