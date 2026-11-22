<?php

namespace Tests\Feature\Repositories;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Enums\UserRole;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use App\Repositories\PenyewaRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenyewaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PenyewaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new PenyewaRepository();
    }

    private function penyewaBaru(string $nik, string $email): Penyewa
    {
        $user = (new User())->forceFill([
            'name' => 'Budi',
            'email' => $email,
            'password' => 'rahasia123',
            'role' => UserRole::PENYEWA,
        ]);

        $penyewa = (new Penyewa())->forceFill([
            'nik' => $nik,
            'nama_lengkap' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'status' => RegistrationStatus::PENDING,
        ]);
        $penyewa->setRelation('user', $user);

        return $penyewa;
    }

    public function test_cari_id_dan_cari_nik(): void
    {
        $penyewa = Penyewa::factory()->create(['nik' => '7371012345670001']);

        $this->assertSame($penyewa->id, $this->repo->cariId($penyewa->id)->id);
        $this->assertSame($penyewa->id, $this->repo->cariNik('7371012345670001')->id);
        $this->assertNull($this->repo->cariId(999999));
        $this->assertNull($this->repo->cariNik('0000000000000000'));
    }

    /**
     * Email dibandingkan tanpa membedakan huruf besar-kecil (asumsi nomor 30).
     */
    public function test_email_terdaftar_tanpa_membedakan_huruf(): void
    {
        User::factory()->create(['email' => 'Budi@Example.com']);

        $this->assertTrue($this->repo->emailTerdaftar('budi@example.com'));
        $this->assertFalse($this->repo->emailTerdaftar('lain@example.com'));
    }

    public function test_menurut_status(): void
    {
        $pending = Penyewa::factory()->create();
        Penyewa::factory()->approved()->create();
        Penyewa::factory()->rejected()->create();

        $hasil = $this->repo->menurutStatus(RegistrationStatus::PENDING);

        $this->assertSame([$pending->id], $hasil->pluck('id')->all());
        $this->assertTrue($hasil->first()->relationLoaded('user'));
    }

    /**
     * Pendaftaran baru: user disimpan lebih dulu, user_id terisi, lalu penyewa.
     */
    public function test_simpan_pendaftaran_baru_menyimpan_user_dan_penyewa(): void
    {
        $hasil = $this->repo->simpan($this->penyewaBaru('7371012345670001', 'budi@example.com'));

        $this->assertNotNull($hasil->id);
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'role' => 'PENYEWA']);
        $this->assertDatabaseHas('penyewa', ['nik' => '7371012345670001', 'user_id' => $hasil->user->id, 'status' => 'PENDING']);
    }

    /**
     * Satu transaksi: bila penyewa gagal disimpan, user ikut dibatalkan.
     */
    public function test_simpan_dibatalkan_seluruhnya_bila_penyewa_gagal(): void
    {
        Penyewa::factory()->create(['nik' => '7371012345670001']);

        try {
            $this->repo->simpan($this->penyewaBaru('7371012345670001', 'baru@example.com'));
            $this->fail('QueryException tidak dilempar.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
        }
    }

    public function test_simpan_perubahan_status_penyewa_yang_sudah_ada(): void
    {
        $penyewa = Penyewa::factory()->create();
        $penyewa->status = RegistrationStatus::APPROVED;

        $this->repo->simpan($penyewa);

        $this->assertSame(RegistrationStatus::APPROVED, $penyewa->fresh()->status);
    }

    public function test_jumlah_menurut_status(): void
    {
        Penyewa::factory()->count(3)->create();
        Penyewa::factory()->approved()->create();

        $this->assertSame(3, $this->repo->jumlahMenurutStatus(RegistrationStatus::PENDING));
        $this->assertSame(1, $this->repo->jumlahMenurutStatus(RegistrationStatus::APPROVED));
        $this->assertSame(0, $this->repo->jumlahMenurutStatus(RegistrationStatus::REJECTED));
    }
}
