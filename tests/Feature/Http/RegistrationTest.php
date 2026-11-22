<?php

namespace Tests\Feature\Http;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'nik' => '7371012345670001',
            'nama_lengkap' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ubah);
    }

    public function test_halaman_pendaftaran_dapat_dibuka(): void
    {
        $this->get(route('daftar'))->assertOk()->assertViewIs('auth.register');
    }

    public function test_pendaftaran_valid_menyimpan_penyewa_pending(): void
    {
        $this->post(route('daftar.simpan'), $this->isian())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $penyewa = Penyewa::query()->where('nik', '7371012345670001')->sole();
        $this->assertSame(RegistrationStatus::PENDING, $penyewa->status);
        $this->assertSame('budi@example.com', $penyewa->user->email);
        $this->assertTrue(Hash::check('rahasia123', $penyewa->user->password));
        $this->assertGuest();
    }

    /**
     * FT-10 - NIK 15 digit ditolak.
     */
    public function test_nik_15_digit_ditolak(): void
    {
        $this->post(route('daftar.simpan'), $this->isian(['nik' => '737101234567000']))
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseCount('penyewa', 0);
    }

    public function test_nik_berisi_huruf_ditolak(): void
    {
        $this->post(route('daftar.simpan'), $this->isian(['nik' => '73710123456700AB']))
            ->assertSessionHasErrors('nik');
    }

    /**
     * FT-11 - NIK atau email yang sudah ada ditolak pada isian masing-masing.
     */
    public function test_nik_dan_email_yang_sudah_ada_ditolak(): void
    {
        Penyewa::factory()->create(['nik' => '7371012345670001']);
        User::factory()->create(['email' => 'budi@example.com']);

        $this->post(route('daftar.simpan'), $this->isian(['email' => 'BUDI@example.com']))
            ->assertSessionHasErrors(['nik', 'email']);

        $this->assertDatabaseCount('penyewa', 1);
    }

    public function test_nomor_hp_disimpan_seragam_berawalan_62(): void
    {
        $this->post(route('daftar.simpan'), $this->isian(['nomor_telepon' => '812-3456-7890']))
            ->assertRedirect(route('login'));

        $this->assertSame('+6281234567890', Penyewa::query()->sole()->nomor_telepon);
    }

    public function test_nomor_berawalan_0_juga_diseragamkan(): void
    {
        $this->post(route('daftar.simpan'), $this->isian())->assertRedirect(route('login'));

        $this->assertSame('+6281234567890', Penyewa::query()->sole()->nomor_telepon);
    }

    public function test_nomor_telepon_rumah_ditolak(): void
    {
        $this->post(route('daftar.simpan'), $this->isian(['nomor_telepon' => '021-1234567']))
            ->assertSessionHasErrors('nomor_telepon');

        $this->assertDatabaseCount('penyewa', 0);
    }

    public function test_isian_nomor_ditampilkan_ulang_tanpa_awalan_62(): void
    {
        $this->from(route('daftar'))
            ->post(route('daftar.simpan'), $this->isian(['nomor_telepon' => '081234567890', 'nik' => '123']));

        $this->get(route('daftar'))->assertSee('value="812-3456-7890"', false)->assertSee('+62');
    }

    public function test_konfirmasi_kata_sandi_harus_sama(): void
    {
        $this->post(route('daftar.simpan'), $this->isian(['password_confirmation' => 'berbeda123']))
            ->assertSessionHasErrors('password');
    }

    public function test_cek_saat_diketik_melaporkan_nik_dan_email_terdaftar(): void
    {
        Penyewa::factory()->create(['nik' => '7371012345670001']);
        User::factory()->create(['email' => 'budi@example.com']);

        $this->getJson(route('daftar.cek', ['nik' => '7371012345670001', 'email' => 'Budi@example.com']))
            ->assertOk()
            ->assertExactJson(['nik' => 'NIK sudah terdaftar.', 'email' => 'Email sudah terdaftar.']);

        $this->getJson(route('daftar.cek', ['nik' => '7371012345670002', 'email' => 'baru@example.com']))
            ->assertOk()
            ->assertExactJson(['nik' => null, 'email' => null]);
    }

    public function test_cek_saat_diketik_menolak_format_salah(): void
    {
        $this->getJson(route('daftar.cek', ['nik' => '123']))->assertUnprocessable()->assertJsonValidationErrors('nik');
    }

    public function test_cek_saat_diketik_dibatasi_60_kali_per_menit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('daftar.cek', ['email' => 'a@example.com']))->assertOk();
        }

        $this->getJson(route('daftar.cek', ['email' => 'a@example.com']))->assertStatus(429);
    }

    public function test_form_daftar_memakai_cek_saat_diketik(): void
    {
        $this->get(route('daftar'))
            ->assertOk()
            ->assertSee('x-data="formDaftar(', false)
            ->assertSee('urlCek', false)
            ->assertSee(':disabled="!valid"', false);
    }
}
