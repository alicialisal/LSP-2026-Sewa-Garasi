<?php

namespace Tests\Feature\Http;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Garage;
use App\Domain\Models\Rental;
use App\Domain\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar garasi untuk admin (tambahan atas permintaan pengguna, di luar diagram A/B.4):
 * status, filter per tipe, ringkasan terpakai dan kosong, tombol tambah garasi.
 */
class AdminGarageListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
        $this->admin = User::factory()->admin()->create();

        Garage::factory()->bike('S001')->create();
        Garage::factory()->car('C001')->create();
        Garage::factory()->car('C002')->used()->create();
        Garage::factory()->electric('E001')->create();
        Rental::factory()->periode('2026-11-21', '2026-11-25')->create(['garage_id' => 'C002', 'nomor_nota' => 'SEW-20261121-0001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_daftar_semua_garasi_dengan_ringkasan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garasi.index'))
            ->assertOk()
            ->assertViewIs('admin.garasi.index')
            ->assertViewHas('garasi', fn ($g) => $g->pluck('id')->all() === ['S001', 'C001', 'C002', 'E001'])
            ->assertViewHas('ringkasan', fn ($r) => $r[1]['total'] === 2 && $r[1]['terpakai'] === 1 && $r[1]['kosong'] === 1)
            ->assertViewHas('tipeAktif', null)
            ->assertSeeInOrder(['Daftar Garasi', 'Tambah Garasi', 'C001', 'C002'])
            ->assertSee(route('admin.garasi.create'))
            ->assertSee('Terpakai')
            ->assertSee('sampai 2026-11-25');
    }

    public function test_filter_per_tipe(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garasi.index', ['tipe' => 'CAR']))
            ->assertOk()
            ->assertViewHas('garasi', fn ($g) => $g->pluck('id')->all() === ['C001', 'C002'])
            ->assertViewHas('tipeAktif', \App\Domain\Enums\GarageType::CAR)
            ->assertDontSee('S001')
            ->assertDontSee('E001');
    }

    public function test_tipe_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.garasi.index', ['tipe' => 'KAPAL']))
            ->assertSessionHasErrors('tipe');
    }

    /**
     * Status disinkronkan sebelum daftar tampil: sewa yang sudah lewat membebaskan garasinya.
     */
    public function test_status_disinkronkan_sebelum_daftar_tampil(): void
    {
        Garage::factory()->car('C003')->used()->create();
        $lewat = Rental::factory()->periode('2026-11-17', '2026-11-20')->create(['garage_id' => 'C003', 'nomor_nota' => 'SEW-20261117-0001']);

        $this->actingAs($this->admin)
            ->get(route('admin.garasi.index', ['tipe' => 'CAR']))
            ->assertViewHas('ringkasan', fn ($r) => $r[1]['terpakai'] === 1 && $r[1]['kosong'] === 2);

        $this->assertSame(RentalStatus::SELESAI, $lewat->fresh()->status);
    }

    public function test_setelah_menambah_garasi_kembali_ke_daftar(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.garasi.store'), ['tipe_garasi' => 'BIKE', 'jumlah' => 2, 'harga_sewa_harian' => 5000])
            ->assertRedirect(route('admin.garasi.index'))
            ->assertSessionHas('status', '2 garasi dibuat: S002 sampai S003.');
    }

    public function test_menu_garasi_mengarah_ke_daftar(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.perpanjangan.index'))
            ->assertSee(route('admin.garasi.index'));
    }
}
