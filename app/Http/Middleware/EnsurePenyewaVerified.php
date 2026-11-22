<?php

namespace App\Http\Middleware;

use App\Domain\Enums\RegistrationStatus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya penyewa berstatus APPROVED yang dapat mengajukan sewa (asumsi nomor 16).
 * Penyewa PENDING atau REJECTED dialihkan dengan pesan (FT-01, FT-16); akun
 * yang bukan penyewa mendapat 403. Dipasang sebagai middleware
 * "penyewa.verified" pada route /sewa.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class EnsurePenyewaVerified
{
    public const PESAN_PENDING = 'Akun belum diverifikasi admin, sehingga belum dapat mengajukan sewa.';

    public const PESAN_REJECTED = 'Pendaftaran akun tidak disetujui admin, sehingga tidak dapat mengajukan sewa.';

    public const PESAN_TANPA_PROFIL = 'Profil penyewa tidak ditemukan.';

    /**
     * Meneruskan permintaan penyewa APPROVED. Penyewa lain mendapat pesan
     * JSON 403 bila meminta JSON, atau dialihkan ke beranda dengan pesan error.
     *
     * @param  Request  $request  permintaan HTTP yang masuk
     * @param  Closure  $next  middleware atau controller berikutnya
     * @return Response respons tahap berikutnya, JSON 403, atau redirect ke beranda
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isPenyewa() ?? false, 403, 'Halaman ini hanya untuk penyewa.');

        $pesan = match ($user->penyewa?->status) {
            RegistrationStatus::APPROVED => null,
            RegistrationStatus::PENDING => self::PESAN_PENDING,
            RegistrationStatus::REJECTED => self::PESAN_REJECTED,
            null => self::PESAN_TANPA_PROFIL,
        };

        if ($pesan === null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $pesan], 403);
        }

        return redirect()->route('beranda')->with('error', $pesan);
    }
}
