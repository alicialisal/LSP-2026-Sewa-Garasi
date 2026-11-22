<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Garage;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGarageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_form_menampilkan_sisa_kuota_per_tipe(): void
    {
        Garage::factory()->bike('S998')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.garasi.create'))
            ->assertOk()
            ->assertViewIs('admin.garasi.create')
            ->assertViewHas('sisaKuota', ['BIKE' => 1, 'CAR' => 999, 'ELECTRIC' => 999]);
    }

    public function test_membuat_garasi_berkelompok(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.garasi.create'))
            ->post(route('admin.garasi.store'), [
                'tipe_garasi' => 'BIKE',
                'jumlah' => 3,
                'harga_sewa_harian' => 5000,
            ])
            ->assertRedirect(route('admin.garasi.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '3 garasi dibuat: S001 sampai S003.');

        $this->assertDatabaseCount('garages', 3);
    }

    /**
     * FT-03 - Lima garasi mobil listrik tanpa harga listrik ditolak.
     */
    public function test_garasi_listrik_tanpa_harga_listrik_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.garasi.store'), [
                'tipe_garasi' => 'ELECTRIC',
                'jumlah' => 5,
                'harga_sewa_harian' => 30000,
            ])
            ->assertSessionHasErrors('harga_listrik_per_kwh');

        $this->assertDatabaseCount('garages', 0);
    }

    /**
     * FT-04 - Tiga garasi sepeda saat yang terakhir S998 ditolak: sisa kuota 1.
     */
    public function test_melebihi_sisa_kuota_ditolak(): void
    {
        Garage::factory()->bike('S998')->create();

        $this->actingAs($this->admin)
            ->post(route('admin.garasi.store'), [
                'tipe_garasi' => 'BIKE',
                'jumlah' => 3,
                'harga_sewa_harian' => 5000,
            ])
            ->assertSessionHasErrors(['jumlah' => 'Jumlah melebihi sisa kuota tipe ini (sisa kuota 1).']);

        $this->assertDatabaseCount('garages', 1);
    }

    public function test_harga_harian_harus_lebih_dari_nol(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.garasi.store'), [
                'tipe_garasi' => 'CAR',
                'jumlah' => 1,
                'harga_sewa_harian' => 0,
            ])
            ->assertSessionHasErrors('harga_sewa_harian');
    }
}
