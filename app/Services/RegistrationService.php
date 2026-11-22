<?php

namespace App\Services;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Enums\UserRole;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use App\Repositories\PenyewaRepository;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Pendaftaran dan verifikasi penyewa. Penyewa baru berstatus PENDING; hanya
 * yang APPROVED dapat mengajukan sewa (asumsi nomor 16). Bergantung pada
 * PenyewaRepository; dipakai RegistrationController,
 * AdminVerificationController, dan AppServiceProvider.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RegistrationService
{
    public function __construct(private readonly PenyewaRepository $penyewaRepo)
    {
    }

    /**
     * Mendaftarkan akun User (role PENYEWA) beserta profil Penyewa berstatus
     * PENDING. NIK dan email harus unik; email dibandingkan tanpa membedakan
     * huruf besar-kecil (asumsi nomor 30).
     *
     * @param  array{nik: string, namaLengkap: string, nomorTelepon: string, email: string, password: string}  $data  isian form pendaftaran
     * @return Penyewa penyewa baru berstatus PENDING beserta akunnya
     *
     * @throws ValidationException jika NIK atau email sudah terdaftar
     */
    public function daftar(array $data): Penyewa
    {
        $email = mb_strtolower(trim($data['email']));

        $kesalahan = [];

        if ($this->penyewaRepo->cariNik($data['nik']) !== null) {
            $kesalahan['nik'] = 'NIK sudah terdaftar.';
        }

        if ($this->penyewaRepo->emailTerdaftar($email)) {
            $kesalahan['email'] = 'Email sudah terdaftar.';
        }

        if ($kesalahan !== []) {
            throw ValidationException::withMessages($kesalahan);
        }

        // Kolom users.name hanya "nama panggilan atau display name"; data pendaftaran tidak
        // membawanya, sehingga nama lengkap dipakai.
        $user = (new User())->forceFill([
            'name' => $data['namaLengkap'],
            'email' => $email,
            'password' => $data['password'],
            'role' => UserRole::PENYEWA,
        ]);

        $penyewa = (new Penyewa())->forceFill([
            'nik' => $data['nik'],
            'nama_lengkap' => $data['namaLengkap'],
            'nomor_telepon' => $data['nomorTelepon'],
            'status' => RegistrationStatus::PENDING,
        ]);
        $penyewa->setRelation('user', $user);

        return $this->penyewaRepo->simpan($penyewa);
    }

    /**
     * Cek dini saat form pendaftaran diketik: apakah NIK atau email sudah
     * terdaftar. Isian kosong tidak dicek. Ini hanya membantu pengguna;
     * pengecekan yang mengikat tetap dilakukan saat daftar().
     *
     * @param  string|null  $nik  NIK yang sedang diketik, boleh kosong
     * @param  string|null  $email  email yang sedang diketik, boleh kosong
     * @return array{nik: ?string, email: ?string} pesan galat per isian, null bila aman
     */
    public function cekKetersediaan(?string $nik, ?string $email): array
    {
        $email = mb_strtolower(trim((string) $email));

        return [
            'nik' => $nik !== null && $nik !== '' && $this->penyewaRepo->cariNik($nik) !== null
                ? 'NIK sudah terdaftar.' : null,
            'email' => $email !== '' && $this->penyewaRepo->emailTerdaftar($email)
                ? 'Email sudah terdaftar.' : null,
        ];
    }

    /**
     * Pendaftar berstatus PENDING untuk halaman verifikasi admin.
     *
     * @return Collection<int, Penyewa> pendaftar yang menunggu verifikasi, yang terlama lebih dulu
     */
    public function daftarMenungguVerifikasi(): Collection
    {
        return $this->penyewaRepo->menurutStatus(RegistrationStatus::PENDING);
    }

    /**
     * Jumlah pendaftar yang menunggu verifikasi, untuk angka notifikasi di
     * menu Verifikasi admin.
     *
     * @return int banyak penyewa berstatus PENDING
     */
    public function jumlahMenungguVerifikasi(): int
    {
        return $this->penyewaRepo->jumlahMenurutStatus(RegistrationStatus::PENDING);
    }

    /**
     * Menyetujui pendaftaran penyewa sehingga statusnya menjadi APPROVED.
     *
     * @param  int  $penyewaId  id penyewa yang diverifikasi
     * @param  int  $adminId  id user admin yang memverifikasi
     * @return Penyewa penyewa setelah disetujui
     *
     * @throws DomainException jika penyewa tidak ditemukan atau bukan PENDING
     */
    public function terima(int $penyewaId, int $adminId): Penyewa
    {
        $penyewa = $this->ambilPending($penyewaId);

        $penyewa->status = RegistrationStatus::APPROVED;
        $penyewa->alasan_penolakan = null;
        $this->catatVerifikasi($penyewa, $adminId);

        return $this->penyewaRepo->simpan($penyewa);
    }

    /**
     * Menolak pendaftaran penyewa sehingga statusnya menjadi REJECTED.
     *
     * @param  int  $penyewaId  id penyewa yang diverifikasi
     * @param  int  $adminId  id user admin yang memverifikasi
     * @param  string  $alasan  alasan penolakan, tidak boleh kosong
     * @return Penyewa penyewa setelah ditolak
     *
     * @throws InvalidArgumentException jika alasan kosong
     * @throws DomainException jika penyewa tidak ditemukan atau bukan PENDING
     */
    public function tolak(int $penyewaId, int $adminId, string $alasan): Penyewa
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $penyewa = $this->ambilPending($penyewaId);

        $penyewa->status = RegistrationStatus::REJECTED;
        $penyewa->alasan_penolakan = $alasan;
        $this->catatVerifikasi($penyewa, $adminId);

        return $this->penyewaRepo->simpan($penyewa);
    }

    /**
     * @throws DomainException jika penyewa tidak ditemukan atau bukan PENDING
     */
    private function ambilPending(int $penyewaId): Penyewa
    {
        $penyewa = $this->penyewaRepo->cariId($penyewaId)
            ?? throw new DomainException('Penyewa tidak ditemukan.');

        if ($penyewa->status !== RegistrationStatus::PENDING) {
            throw new DomainException('Hanya penyewa berstatus PENDING yang dapat diverifikasi.');
        }

        return $penyewa;
    }

    private function catatVerifikasi(Penyewa $penyewa, int $adminId): void
    {
        $penyewa->verified_by = $adminId;
        $penyewa->verified_at = now();
    }
}
