<?php

namespace Tests\Feature\Http;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_daftar_hanya_memuat_pendaftar_pending(): void
    {
        $pending = Penyewa::factory()->create();
        Penyewa::factory()->approved()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.verifikasi.index'))
            ->assertOk()
            ->assertViewIs('admin.verifikasi.index')
            ->assertViewHas('pendaftar', fn ($daftar) => $daftar->pluck('id')->all() === [$pending->id]);
    }

    /**
     * Angka di menu Verifikasi menunjukkan jumlah pendaftar PENDING dan tampil
     * di setiap halaman admin, seperti notifikasi.
     */
    public function test_menu_verifikasi_menampilkan_jumlah_pendaftar_menunggu(): void
    {
        Penyewa::factory()->count(2)->create();
        Penyewa::factory()->approved()->create();
        Penyewa::factory()->rejected()->create();

        foreach (['admin.perpanjangan.index', 'admin.garasi.index', 'admin.laporan.bulanan'] as $rute) {
            $this->actingAs($this->admin)
                ->get(route($rute))
                ->assertOk()
                ->assertSee('data-test="jumlah-verifikasi">2</span>', false)
                ->assertSee('2 akun menunggu verifikasi');
        }
    }

    public function test_angka_verifikasi_tidak_tampil_bila_tidak_ada_pendaftar_menunggu(): void
    {
        Penyewa::factory()->approved()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.perpanjangan.index'))
            ->assertOk()
            ->assertDontSee('data-test="jumlah-verifikasi"', false);
    }

    public function test_angka_verifikasi_tidak_tampil_untuk_penyewa(): void
    {
        Penyewa::factory()->create();
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.index'))
            ->assertOk()
            ->assertDontSee('data-test="jumlah-verifikasi"', false);
    }

    public function test_nomor_hp_ditampilkan_dengan_format_indonesia(): void
    {
        Penyewa::factory()->create(['nomor_telepon' => '+6281234567890']);
        Penyewa::factory()->create(['nomor_telepon' => '085712345678']);

        $this->actingAs($this->admin)
            ->get(route('admin.verifikasi.index'))
            ->assertSee('+62 812-3456-7890')
            ->assertSee('+62 857-1234-5678');
    }

    public function test_terima_pendaftar(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.terima', $penyewa->id))
            ->assertRedirect(route('admin.verifikasi.index'))
            ->assertSessionHas('status');

        $penyewa->refresh();
        $this->assertSame(RegistrationStatus::APPROVED, $penyewa->status);
        $this->assertSame($this->admin->id, $penyewa->verified_by);
        $this->assertNotNull($penyewa->verified_at);
    }

    public function test_tolak_pendaftar_dengan_alasan(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.tolak', $penyewa->id), ['alasan' => 'Foto KTP tidak terbaca'])
            ->assertRedirect(route('admin.verifikasi.index'));

        $penyewa->refresh();
        $this->assertSame(RegistrationStatus::REJECTED, $penyewa->status);
        $this->assertSame('Foto KTP tidak terbaca', $penyewa->alasan_penolakan);
    }

    public function test_tolak_tanpa_alasan_ditolak(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.verifikasi.tolak', $penyewa->id), ['alasan' => ''])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(RegistrationStatus::PENDING, $penyewa->fresh()->status);
    }

    public function test_terima_penyewa_yang_sudah_approved_ditolak(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.verifikasi.index'))
            ->post(route('admin.verifikasi.terima', $penyewa->id))
            ->assertRedirect(route('admin.verifikasi.index'))
            ->assertSessionHasErrors(['umum' => 'Hanya penyewa berstatus PENDING yang dapat diverifikasi.']);
    }
}
