<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Enums\UserRole;
use App\Domain\Models\Penyewa;
use App\Repositories\PenyewaRepository;
use App\Services\RegistrationService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class RegistrationServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const ID_ADMIN = 2;

    private MockInterface&PenyewaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = Mockery::mock(PenyewaRepository::class);
        $this->repo->shouldReceive('cariNik')->andReturn(null)->byDefault();
        $this->repo->shouldReceive('emailTerdaftar')->andReturn(false)->byDefault();
        $this->repo->shouldReceive('simpan')->andReturnArg(0)->byDefault();
    }

    private function data(string $nik = '7371012345670001', string $email = 'budi@example.com'): array
    {
        return [
            'nik' => $nik,
            'namaLengkap' => 'Budi Santoso',
            'nomorTelepon' => '081234567890',
            'email' => $email,
            'password' => 'rahasia123',
        ];
    }

    private function penyewa(RegistrationStatus $status): Penyewa
    {
        $penyewa = (new Penyewa())->forceFill(['status' => $status, 'nik' => '7371012345670001']);
        $penyewa->id = 1;

        return $penyewa;
    }

    /**
     * UT-RG-01 - Data valid: User PENYEWA dan Penyewa PENDING tersimpan, kata sandi ter-hash.
     */
    public function test_daftar_menyimpan_user_dan_penyewa_pending(): void
    {
        $disimpan = null;
        $this->repo->shouldReceive('simpan')->once()->andReturnUsing(function (Penyewa $penyewa) use (&$disimpan) {
            $disimpan = $penyewa;

            return $penyewa;
        });

        (new RegistrationService($this->repo))->daftar($this->data());

        $this->assertSame(RegistrationStatus::PENDING, $disimpan->status);
        $this->assertSame('7371012345670001', $disimpan->nik);
        $this->assertSame('Budi Santoso', $disimpan->nama_lengkap);
        $this->assertSame('081234567890', $disimpan->nomor_telepon);

        $user = $disimpan->user;
        $this->assertSame(UserRole::PENYEWA, $user->role);
        $this->assertSame('budi@example.com', $user->email);
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    /**
     * UT-RG-02 - NIK sudah terdaftar ditolak.
     */
    public function test_nik_sudah_terdaftar_ditolak(): void
    {
        $this->repo->shouldReceive('cariNik')->with('7371012345670001')->andReturn($this->penyewa(RegistrationStatus::PENDING));
        $this->repo->shouldNotReceive('simpan');

        try {
            (new RegistrationService($this->repo))->daftar($this->data());
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nik', $e->errors());
        }
    }

    /**
     * UT-RG-07 - Email sudah terdaftar ditolak.
     */
    public function test_email_sudah_terdaftar_ditolak(): void
    {
        $this->repo->shouldReceive('emailTerdaftar')->with('budi@example.com')->andReturn(true);
        $this->repo->shouldNotReceive('simpan');

        try {
            (new RegistrationService($this->repo))->daftar($this->data());
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    /**
     * UT-RG-08 - Email dibandingkan tanpa membedakan huruf besar-kecil.
     */
    public function test_email_dinormalisasi_huruf_kecil_sebelum_dicek(): void
    {
        $this->repo->shouldReceive('emailTerdaftar')->with('a@x.com')->once()->andReturn(true);
        $this->repo->shouldNotReceive('simpan');

        $this->expectException(ValidationException::class);

        (new RegistrationService($this->repo))->daftar($this->data(email: 'A@x.com'));
    }

    /**
     * NIK dan email bentrok sekaligus: kedua isian dilaporkan bersama (FT-11).
     */
    public function test_nik_dan_email_bentrok_dilaporkan_bersama(): void
    {
        $this->repo->shouldReceive('cariNik')->andReturn($this->penyewa(RegistrationStatus::PENDING));
        $this->repo->shouldReceive('emailTerdaftar')->andReturn(true);

        try {
            (new RegistrationService($this->repo))->daftar($this->data());
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nik', $e->errors());
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    /**
     * UT-RG-03 - Penyewa PENDING diterima: APPROVED, verified_by dan verified_at terisi.
     */
    public function test_terima_penyewa_pending(): void
    {
        $this->repo->shouldReceive('cariId')->with(1)->andReturn($this->penyewa(RegistrationStatus::PENDING));
        $this->repo->shouldReceive('simpan')->once()->andReturnArg(0);

        $hasil = (new RegistrationService($this->repo))->terima(1, self::ID_ADMIN);

        $this->assertSame(RegistrationStatus::APPROVED, $hasil->status);
        $this->assertSame(self::ID_ADMIN, $hasil->verified_by);
        $this->assertNotNull($hasil->verified_at);
    }

    /**
     * UT-RG-04 - Penyewa yang sudah APPROVED tidak dapat diterima lagi.
     */
    public function test_terima_penyewa_yang_sudah_approved_ditolak(): void
    {
        $this->repo->shouldReceive('cariId')->with(1)->andReturn($this->penyewa(RegistrationStatus::APPROVED));
        $this->repo->shouldNotReceive('simpan');

        $this->expectException(DomainException::class);

        (new RegistrationService($this->repo))->terima(1, self::ID_ADMIN);
    }

    /**
     * UT-RG-05 - Penolakan tanpa alasan ditolak.
     */
    public function test_tolak_tanpa_alasan_ditolak(): void
    {
        $this->repo->shouldNotReceive('simpan');

        $this->expectException(InvalidArgumentException::class);

        (new RegistrationService($this->repo))->tolak(1, self::ID_ADMIN, '   ');
    }

    /**
     * UT-RG-06 - Penolakan beralasan: REJECTED dan alasan tersimpan.
     */
    public function test_tolak_dengan_alasan(): void
    {
        $this->repo->shouldReceive('cariId')->with(1)->andReturn($this->penyewa(RegistrationStatus::PENDING));
        $this->repo->shouldReceive('simpan')->once()->andReturnArg(0);

        $hasil = (new RegistrationService($this->repo))->tolak(1, self::ID_ADMIN, 'NIK tidak sesuai KTP');

        $this->assertSame(RegistrationStatus::REJECTED, $hasil->status);
        $this->assertSame('NIK tidak sesuai KTP', $hasil->alasan_penolakan);
        $this->assertSame(self::ID_ADMIN, $hasil->verified_by);
        $this->assertNotNull($hasil->verified_at);
    }

    public function test_tolak_penyewa_yang_bukan_pending_ditolak(): void
    {
        $this->repo->shouldReceive('cariId')->with(1)->andReturn($this->penyewa(RegistrationStatus::REJECTED));
        $this->repo->shouldNotReceive('simpan');

        $this->expectException(DomainException::class);

        (new RegistrationService($this->repo))->tolak(1, self::ID_ADMIN, 'alasan');
    }

    public function test_penyewa_tidak_ditemukan_ditolak(): void
    {
        $this->repo->shouldReceive('cariId')->with(1)->andReturn(null);

        $this->expectException(DomainException::class);

        (new RegistrationService($this->repo))->terima(1, self::ID_ADMIN);
    }

    public function test_daftar_menunggu_verifikasi_meminta_status_pending(): void
    {
        $daftar = new Collection([$this->penyewa(RegistrationStatus::PENDING)]);
        $this->repo->shouldReceive('menurutStatus')->once()->with(RegistrationStatus::PENDING)->andReturn($daftar);

        $hasil = (new RegistrationService($this->repo))->daftarMenungguVerifikasi();

        $this->assertSame($daftar, $hasil);
    }

    /**
     * Jumlah pendaftar PENDING untuk angka notifikasi di menu Verifikasi.
     */
    public function test_jumlah_menunggu_verifikasi(): void
    {
        $this->repo->shouldReceive('jumlahMenurutStatus')->once()->with(RegistrationStatus::PENDING)->andReturn(2);

        $this->assertSame(2, (new RegistrationService($this->repo))->jumlahMenungguVerifikasi());
    }

    public function test_cek_ketersediaan_melaporkan_nik_dan_email_yang_sudah_ada(): void
    {
        $this->repo->shouldReceive('cariNik')->with('7371012345670001')->andReturn(new Penyewa());
        $this->repo->shouldReceive('emailTerdaftar')->with('budi@example.com')->andReturn(true);

        $hasil = (new RegistrationService($this->repo))->cekKetersediaan('7371012345670001', ' BUDI@example.com ');

        $this->assertSame(['nik' => 'NIK sudah terdaftar.', 'email' => 'Email sudah terdaftar.'], $hasil);
    }

    public function test_cek_ketersediaan_tidak_mengecek_isian_kosong(): void
    {
        $this->repo->shouldNotReceive('cariNik');
        $this->repo->shouldNotReceive('emailTerdaftar');

        $this->assertSame(['nik' => null, 'email' => null], (new RegistrationService($this->repo))->cekKetersediaan(null, ''));
    }
}
