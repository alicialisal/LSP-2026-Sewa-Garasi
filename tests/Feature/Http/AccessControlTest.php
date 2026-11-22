<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Middleware EnsurePenyewaVerified dan EnsureUserIsAdmin.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('sewa.create'))->assertRedirect(route('login'));
        $this->get(route('admin.garasi.create'))->assertRedirect(route('login'));
    }

    /**
     * FT-01 - Penyewa PENDING membuka form sewa baru dialihkan dengan pesan.
     */
    public function test_penyewa_pending_dialihkan_dari_form_sewa(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertRedirect(route('beranda'))
            ->assertSessionHas('error', 'Akun belum diverifikasi admin, sehingga belum dapat mengajukan sewa.');
    }

    /**
     * FT-16 - Penyewa REJECTED dialihkan dengan pesan akun tidak disetujui.
     */
    public function test_penyewa_rejected_dialihkan_dari_form_sewa(): void
    {
        $penyewa = Penyewa::factory()->rejected()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertRedirect(route('beranda'))
            ->assertSessionHas('error', 'Pendaftaran akun tidak disetujui admin, sehingga tidak dapat mengajukan sewa.');
    }

    public function test_penyewa_pending_juga_tidak_dapat_mengirim_sewa(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($penyewa->user)
            ->post(route('sewa.store'), [])
            ->assertRedirect(route('beranda'));
    }

    public function test_penyewa_approved_dapat_membuka_form_sewa(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertViewIs('tenant.sewa.create');
    }

    /**
     * FT-05 - Penyewa membuka halaman admin mendapat 403.
     */
    public function test_penyewa_membuka_halaman_admin_mendapat_403(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)->get(route('admin.garasi.create'))->assertForbidden();
        $this->actingAs($penyewa->user)->get(route('admin.verifikasi.index'))->assertForbidden();
        $this->actingAs($penyewa->user)->get(route('admin.laporan.bulanan'))->assertForbidden();
    }

    public function test_admin_tidak_dapat_membuka_form_sewa_penyewa(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('sewa.create'))
            ->assertForbidden();
    }

    public function test_admin_dapat_membuka_halaman_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.garasi.create'))
            ->assertOk();
    }
}
