<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login dan logout tanpa starter kit. UserFactory memakai kata sandi "password".
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_dapat_dibuka(): void
    {
        $this->get(route('login'))->assertOk()->assertViewIs('auth.login');
    }

    public function test_admin_diarahkan_ke_daftar_perpanjangan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.proses'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.perpanjangan.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_penyewa_diarahkan_ke_riwayat_sewa(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->post(route('login.proses'), ['email' => $penyewa->user->email, 'password' => 'password'])
            ->assertRedirect(route('sewa.index'));
    }

    /**
     * Asumsi nomor 16: penyewa PENDING tetap dapat login.
     */
    public function test_penyewa_pending_tetap_dapat_login(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->post(route('login.proses'), ['email' => $penyewa->user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($penyewa->user);
    }

    /**
     * Asumsi nomor 30: email dibandingkan tanpa membedakan huruf besar-kecil.
     */
    public function test_email_login_tanpa_membedakan_huruf(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@garasi.test']);

        $this->post(route('login.proses'), ['email' => 'ADMIN@Garasi.test', 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from(route('login'))
            ->post(route('login.proses'), ['email' => $admin->email, 'password' => 'salah'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_pengguna_yang_sudah_login_tidak_membuka_halaman_login(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('login'))
            ->assertRedirect();
    }
}
