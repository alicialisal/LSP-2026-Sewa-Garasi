<?php

namespace Tests\Feature\Http;

use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Garage;
use App\Domain\Models\Rental;
use App\Domain\Models\User;
use App\Support\PaymentProofStorage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 09:30:00');
        CarbonImmutable::setTestNow('2026-11-21 09:30:00');
        Storage::fake(PaymentProofStorage::DISK);

        $this->admin = User::factory()->admin()->create();
        Garage::factory()->car('C001')->used()->create();
        Garage::factory()->car('C002')->used()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function sewaAktif(): Rental
    {
        return Rental::factory()->periode('2026-11-21', '2026-11-23')->create([
            'garage_id' => 'C001',
            'nomor_nota' => 'SEW-20261121-0001',
        ]);
    }

    /**
     * FT-17 - Daftar perpanjangan hanya memuat sewa yang aktif hari itu; sewa
     * yang berakhir kemarin disinkronkan lebih dulu.
     */
    public function test_daftar_hanya_memuat_sewa_aktif_hari_ini(): void
    {
        $aktif = $this->sewaAktif();
        $kemarin = Rental::factory()->periode('2026-11-18', '2026-11-20')->create(['garage_id' => 'C002', 'nomor_nota' => 'SEW-20261118-0001']);
        Rental::factory()->periode('2026-11-21', '2026-11-23')->selesai()->create(['nomor_nota' => 'SEW-20261121-0009']);

        $this->actingAs($this->admin)
            ->get(route('admin.perpanjangan.index'))
            ->assertOk()
            ->assertViewIs('admin.perpanjangan.index')
            ->assertViewHas('sewa', fn ($daftar) => $daftar->pluck('id')->all() === [$aktif->id]);

        $this->assertSame(RentalStatus::SELESAI, $kemarin->fresh()->status);
    }

    public function test_form_perpanjangan_untuk_sewa_aktif(): void
    {
        $sewa = $this->sewaAktif();

        $this->actingAs($this->admin)
            ->get(route('admin.perpanjangan.edit', $sewa->id))
            ->assertOk()
            ->assertViewIs('admin.perpanjangan.edit')
            ->assertViewHas('sewa', fn ($s) => $s->id === $sewa->id);
    }

    public function test_form_perpanjangan_untuk_sewa_tidak_aktif_404(): void
    {
        $selesai = Rental::factory()->selesai()->create(['nomor_nota' => 'SEW-20261121-0009']);

        $this->actingAs($this->admin)
            ->get(route('admin.perpanjangan.edit', $selesai->id))
            ->assertNotFound();
    }

    public function test_perpanjangan_valid_tersimpan(): void
    {
        $sewa = $this->sewaAktif();

        $this->actingAs($this->admin)
            ->put(route('admin.perpanjangan.update', $sewa->id), [
                'tanggal_selesai_baru' => '2026-11-26',
                'bukti_bayar' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertRedirect(route('admin.perpanjangan.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Perpanjangan PJG-20261121-0001 tersimpan. Biaya tambahan Rp75.000.');

        $this->assertSame('2026-11-26', $sewa->fresh()->tanggal_selesai->toDateString());
        $this->assertDatabaseHas('payments', ['rental_id' => $sewa->id, 'diunggah_oleh' => $this->admin->id]);
    }

    public function test_tanggal_baru_tidak_setelah_tanggal_lama_ditolak(): void
    {
        $sewa = $this->sewaAktif();

        $this->actingAs($this->admin)
            ->from(route('admin.perpanjangan.edit', $sewa->id))
            ->put(route('admin.perpanjangan.update', $sewa->id), [
                'tanggal_selesai_baru' => '2026-11-23',
                'bukti_bayar' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertRedirect(route('admin.perpanjangan.edit', $sewa->id))
            ->assertSessionHasErrors(['umum' => 'Tanggal selesai baru harus setelah tanggal selesai lama.']);

        $this->assertDatabaseCount('rental_extensions', 0);
        $this->assertCount(0, Storage::disk(PaymentProofStorage::DISK)->allFiles());
    }

    public function test_bukti_bayar_wajib_gambar(): void
    {
        $sewa = $this->sewaAktif();

        $this->actingAs($this->admin)
            ->put(route('admin.perpanjangan.update', $sewa->id), [
                'tanggal_selesai_baru' => '2026-11-26',
                'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('bukti_bayar');
    }
}
