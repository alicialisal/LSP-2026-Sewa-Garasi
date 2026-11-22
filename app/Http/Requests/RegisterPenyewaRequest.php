<?php

namespace App\Http\Requests;

use App\Repositories\PenyewaRepository;
use App\Support\NomorTeleponFormatter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pendaftaran penyewa. Keunikan NIK dan email dicek di sini (asumsi nomor 30)
 * lewat repository yang sama dengan RegistrationService, dan diperkuat
 * constraint UNIQUE pada tabel. Dipakai route POST /daftar
 * (RegistrationController::store()).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RegisterPenyewaRequest extends FormRequest
{
    /**
     * Semua tamu boleh mengirim form pendaftaran.
     *
     * @return bool selalu true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nomor HP diseragamkan menjadi +62... sebelum divalidasi. Nilai yang
     * tidak dapat diseragamkan dibiarkan agar ditolak aturan regex.
     */
    protected function prepareForValidation(): void
    {
        $nomor = $this->container->make(NomorTeleponFormatter::class)
            ->normalisasi((string) $this->input('nomor_telepon', ''));

        if ($nomor !== null) {
            $this->merge(['nomor_telepon' => $nomor]);
        }
    }

    /**
     * Aturan validasi data pendaftaran, termasuk cek NIK dan email yang
     * sudah terdaftar.
     *
     * @return array<string, array<int, mixed>> aturan validasi per isian
     */
    public function rules(): array
    {
        $repo = $this->container->make(PenyewaRepository::class);

        return [
            'nik' => ['bail', 'required', 'digits:16', function (string $atribut, mixed $nilai, Closure $gagal) use ($repo) {
                if ($repo->cariNik((string) $nilai) !== null) {
                    $gagal('NIK sudah terdaftar.');
                }
            }],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_telepon' => ['required', 'string', 'regex:/^\+628\d{8,11}$/'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $atribut, mixed $nilai, Closure $gagal) use ($repo) {
                if ($repo->emailTerdaftar(mb_strtolower(trim((string) $nilai)))) {
                    $gagal('Email sudah terdaftar.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Pesan galat validasi dalam bahasa Indonesia.
     *
     * @return array<string, string> pesan per aturan isian
     */
    public function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka sesuai KTP.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nomor_telepon.required' => 'Nomor telepon wajib diisi.',
            'nomor_telepon.regex' => 'Nomor HP tidak valid. Isi angka sesudah +62, contoh 812-3456-7890.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }

    /**
     * Data tervalidasi untuk service, kunci sesuai nama properti.
     *
     * @return array{nik: string, namaLengkap: string, nomorTelepon: string, email: string, password: string}
     */
    public function toData(): array
    {
        return [
            'nik' => $this->validated('nik'),
            'namaLengkap' => $this->validated('nama_lengkap'),
            'nomorTelepon' => $this->validated('nomor_telepon'),
            'email' => $this->validated('email'),
            'password' => $this->validated('password'),
        ];
    }
}
