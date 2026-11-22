<?php

namespace Tests\Feature\Http;

use App\Domain\Models\Garage;
use App\Domain\Models\Penyewa;
use App\Domain\Models\Rental;
use App\Domain\Models\User;
use App\Domain\Models\Vehicle;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Isi halaman yang benar-benar dilihat pengguna (G.5, sisi tampilan).
 */
class ViewRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_beranda_untuk_tamu(): void
    {
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Sistem Penyewaan Garasi Kendaraan')
            ->assertSee(route('daftar'));
    }

    /**
     * FT-01 - Pesan "akun belum diverifikasi" tampil di halaman tujuan.
     */
    public function test_pesan_akun_belum_diverifikasi_tampil(): void
    {
        $penyewa = Penyewa::factory()->create();

        $this->actingAs($penyewa->user)
            ->followingRedirects()
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertSee('Akun belum diverifikasi admin')
            ->assertSee('PENDING');
    }

    public function test_halaman_login_dan_daftar(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('name="email"', false)->assertSee('name="password"', false);
        $this->get(route('daftar'))->assertOk()->assertSee('name="nik"', false)->assertSee('name="password_confirmation"', false);
    }

    public function test_galat_login_tampil_di_form(): void
    {
        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.proses'), ['email' => 'tidak@ada.test', 'password' => 'salah'])
            ->assertSee('Email atau kata sandi salah.');
    }

    public function test_form_sewa_memuat_hari_sebulan(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertSee('21 November 2026')
            ->assertSee('Bulan ini memiliki 30 hari, sehingga sewa harian paling lama 29 hari.')
            ->assertSee('name="bukti_bayar"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    /**
     * Tanggal selesai dapat dipilih langsung sebagai pengganti jumlah hari.
     * Isian ini hanya membantu: yang dikirim ke server tetap jumlah_hari,
     * sehingga aturan konversi ke bulanan tetap dijalankan RentalTypeResolver.
     */
    public function test_form_sewa_dapat_memilih_tanggal_selesai(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertSee('id="tanggal_selesai"', false)
            ->assertSee('type="date"', false)
            ->assertSee('min="2026-11-21"', false)
            ->assertSee('tanggalMulai', false)
            ->assertDontSee('name="tanggal_selesai"', false);
    }

    /**
     * FT-02 dan FT-08 - Setelah tersimpan, halaman menampilkan total biaya bulanan
     * dan notifikasi "sewa maksimal 1 bulan".
     */
    public function test_ringkasan_sewa_tampil_setelah_tersimpan(): void
    {
        Storage::fake(PaymentProofStorage::DISK);
        Garage::factory()->car('C001')->create();
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->followingRedirects()
            ->post(route('sewa.store'), [
                'tipe_kendaraan' => 'MOBIL',
                'nomor_plat' => 'DD 1234 AB',
                'merek' => 'Toyota',
                'tipe' => 'Avanza',
                'tipe_sewa' => 'HARIAN',
                'jumlah_hari' => 40,
                'garage_id' => 'C001',
                'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertOk()
            ->assertSee('SEW-20261121-0001')
            ->assertSee('BULANAN')
            ->assertSee('Rp625.000')
            ->assertSee('Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan (30 hari).');
    }

    public function test_galat_validasi_tampil_di_bawah_isian(): void
    {
        Garage::factory()->car('C001')->create();
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->followingRedirects()
            ->from(route('sewa.create'))
            ->post(route('sewa.store'), [
                'tipe_kendaraan' => 'MOBIL',
                'merek' => 'Toyota',
                'tipe' => 'Avanza',
                'tipe_sewa' => 'HARIAN',
                'jumlah_hari' => 3,
                'garage_id' => 'C001',
                'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ])
            ->assertSee('Bukti bayar harus berupa gambar JPG, JPEG, atau PNG.')
            ->assertSee('Nomor plat wajib diisi untuk tipe kendaraan ini.')
            ->assertSee('value="Toyota"', false);
    }

    public function test_halaman_admin_buat_garasi_menampilkan_sisa_kuota(): void
    {
        Garage::factory()->bike('S998')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.garasi.create'))
            ->assertOk()
            ->assertSee('Garasi Sepeda')
            ->assertSee('1 dari 999')
            ->assertSee(':disabled="!valid"', false);
    }

    /**
     * Pesan galat dari server tetap dirender di HTML (sebelum Alpine aktif)
     * dan isiannya ditandai is-invalid.
     */
    public function test_pesan_galat_server_dirender_di_bawah_isian(): void
    {
        $this->from(route('daftar'))->post(route('daftar.simpan'), ['nik' => '123']);

        $this->get(route('daftar'))
            ->assertOk()
            ->assertSee('NIK harus 16 digit angka sesuai KTP.')
            ->assertSee('x-text="pesan(\'nik\')"', false);
    }

    public function test_halaman_verifikasi_menampilkan_pendaftar(): void
    {
        Penyewa::factory()->create(['nama_lengkap' => 'Sari Wulandari', 'nik' => '7371012345670009']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.verifikasi.index'))
            ->assertOk()
            ->assertSee('Sari Wulandari')
            ->assertSee('7371012345670009');
    }

    public function test_halaman_perpanjangan_dan_form(): void
    {
        Garage::factory()->car('C001')->used()->create();
        $sewa = Rental::factory()->periode('2026-11-21', '2026-11-23')->create(['garage_id' => 'C001', 'nomor_nota' => 'SEW-20261121-0001']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.perpanjangan.index'))
            ->assertOk()
            ->assertSee('SEW-20261121-0001')
            ->assertSee(route('admin.perpanjangan.edit', $sewa->id));

        $this->actingAs($admin)
            ->get(route('admin.perpanjangan.edit', $sewa->id))
            ->assertOk()
            ->assertSee('min="2026-11-24"', false)
            ->assertSee('Rp25.000')
            ->assertSee('pilihBukti($event)', false)
            ->assertSee(':disabled="!valid"', false);
    }

    public function test_laporan_menampilkan_nama_bulan_dan_rupiah(): void
    {
        Rental::factory()->periode('2026-03-10', '2026-03-12')->create(['total_biaya' => 1500000, 'nomor_nota' => 'SEW-20260310-0001']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.laporan.bulanan'))
            ->assertOk()
            ->assertSee('Maret')
            ->assertSee('Rp1.500.000');

        $this->actingAs($admin)->get(route('admin.laporan.harian', ['tahun' => 2026, 'bulan' => 2]))->assertOk()->assertSee('28 Februari 2026');
        $this->actingAs($admin)->get(route('admin.laporan.tipe-garasi'))->assertOk()->assertSee('Garasi Mobil Listrik');
        $this->actingAs($admin)->get(route('admin.laporan.penyewa'))->assertOk()->assertSee('Tidak ada sewa pada rentang ini.');
    }

    public function test_menu_navigasi_mengikuti_role(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('beranda'))
            ->assertSee(route('admin.laporan.bulanan'))
            ->assertSee('navbar-app sticky-top', false)
            ->assertDontSee(route('sewa.index'));

        $penyewa = Penyewa::factory()->approved()->create();
        $this->actingAs($penyewa->user)
            ->get(route('beranda'))
            ->assertSee(route('sewa.index'))
            ->assertDontSee(route('admin.laporan.bulanan'));
    }

    /**
     * Penyewa yang pernah memarkir kendaraan memilih dari daftar; kendaraan
     * yang sedang disewa tidak bisa dipilih. Penyewa baru langsung mengisi isian.
     */
    public function test_form_sewa_menampilkan_pilihan_kendaraan_yang_pernah_diparkir(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();
        $mobil = Vehicle::factory()->create(['penyewa_id' => $penyewa->id, 'nomor_plat' => 'DD 1001 AA', 'merek' => 'Toyota', 'tipe' => 'Avanza']);
        $motor = Vehicle::factory()->create(['penyewa_id' => $penyewa->id, 'nomor_plat' => 'DD 2002 BB', 'merek' => 'Honda', 'tipe' => 'Vario']);
        Rental::factory()->periode('2026-10-01', '2026-10-03')->create(['penyewa_id' => $penyewa->id, 'vehicle_id' => $mobil->id, 'nomor_nota' => 'SEW-20261001-0001']);
        Rental::factory()->periode('2026-11-20', '2026-11-23')->create(['penyewa_id' => $penyewa->id, 'vehicle_id' => $motor->id, 'nomor_nota' => 'SEW-20261120-0001']);

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertSee('id="kendaraan_tersimpan"', false)
            ->assertSee('DD 1001 AA · Toyota Avanza')
            ->assertSee('DD 2002 BB · Honda Vario (sedang disewa)')
            ->assertSee('Kendaraan baru');
    }

    public function test_penyewa_baru_tidak_melihat_pilihan_kendaraan(): void
    {
        $penyewa = Penyewa::factory()->approved()->create();

        $this->actingAs($penyewa->user)
            ->get(route('sewa.create'))
            ->assertOk()
            ->assertDontSee('id="kendaraan_tersimpan"', false)
            ->assertSee('id="nomor_plat"', false);
    }
}
