<?php

namespace App\Repositories;

use App\Domain\Enums\RegistrationStatus;
use App\Domain\Models\Penyewa;
use App\Domain\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Akses data penyewa dan akun pendaftar (diagram B.3). Dipakai
 * RegistrationService, RentalService, dan RegisterPenyewaRequest.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class PenyewaRepository
{
    /**
     * Mencari penyewa menurut id.
     *
     * @param  int  $id  id penyewa
     * @return Penyewa|null penyewa, atau null jika tidak ada
     */
    public function cariId(int $id): ?Penyewa
    {
        return Penyewa::query()->find($id);
    }

    /**
     * Mencari penyewa menurut NIK.
     *
     * @param  string  $nik  nomor induk kependudukan penyewa
     * @return Penyewa|null penyewa, atau null jika tidak ada
     */
    public function cariNik(string $nik): ?Penyewa
    {
        return Penyewa::query()->where('nik', $nik)->first();
    }

    /**
     * Apakah email sudah dipakai akun. Pemanggil sudah menormalkan email ke
     * huruf kecil; implementasi harus membandingkan tanpa membedakan huruf.
     *
     * @param  string  $email  alamat email yang diperiksa
     * @return bool true jika email sudah terdaftar
     */
    public function emailTerdaftar(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists();
    }

    /**
     * Penyewa berstatus pendaftaran tertentu beserta akunnya, yang terlama lebih dulu.
     *
     * @param  RegistrationStatus  $status  status pendaftaran yang disaring
     * @return Collection<int, Penyewa> penyewa dengan relasi user sudah dimuat
     */
    public function menurutStatus(RegistrationStatus $status): Collection
    {
        return Penyewa::query()
            ->with('user')
            ->where('status', $status->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Jumlah penyewa berstatus tertentu (satu query COUNT).
     *
     * @param  RegistrationStatus  $status  status pendaftaran yang dihitung
     * @return int banyak penyewa berstatus tersebut
     */
    public function jumlahMenurutStatus(RegistrationStatus $status): int
    {
        return Penyewa::query()->where('status', $status->value)->count();
    }

    /**
     * Menyimpan penyewa. Bila relasi `user` pada penyewa dimuat dan belum
     * tersimpan (pendaftaran baru), implementasi menyimpan user lebih dulu,
     * mengisi user_id, lalu menyimpan penyewa, semuanya dalam satu transaksi.
     *
     * @param  Penyewa  $penyewa  penyewa baru atau yang diubah
     * @return Penyewa penyewa yang sama setelah tersimpan
     */
    public function simpan(Penyewa $penyewa): Penyewa
    {
        return DB::transaction(function () use ($penyewa) {
            $user = $penyewa->relationLoaded('user') ? $penyewa->getRelation('user') : null;

            if ($user instanceof User && ! $user->exists) {
                $user->save();
                $penyewa->user_id = $user->id;
            }

            $penyewa->save();

            return $penyewa;
        });
    }
}
