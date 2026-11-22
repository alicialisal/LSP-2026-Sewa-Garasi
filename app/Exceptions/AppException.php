<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Induk semua pengecualian aturan bisnis aplikasi. Setiap turunan memiliki
 * kode kesalahan yang stabil dan konteks tambahan untuk log dan pesan.
 *
 * Laravel memanggil render() secara otomatis: permintaan web dialihkan kembali
 * ke form dengan pesan galat, permintaan JSON mendapat 422 dengan bentuk yang
 * sama seperti galat validasi, sehingga tampilan cukup menangani satu bentuk.
 * Penolakan dicatat oleh Handler lewat RentalAuditLogger.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
abstract class AppException extends RuntimeException
{
    /**
     * Isian yang tidak dikembalikan ke form setelah galat.
     */
    private const ISIAN_TIDAK_DIKEMBALIKAN = ['password', 'password_confirmation', 'bukti_bayar'];

    /**
     * @param  string  $message  pesan galat yang ditampilkan ke pengguna
     * @param  array<string, mixed>  $konteks  data tambahan untuk log
     */
    public function __construct(string $message, protected array $konteks = [])
    {
        parent::__construct($message);
    }

    /**
     * Kode kesalahan yang stabil, dipakai untuk log dan pengujian.
     *
     * @return string kode kesalahan dalam huruf besar, misalnya GARASI_TIDAK_TERSEDIA
     */
    abstract public function kodeKesalahan(): string;

    /**
     * Data tambahan yang menjelaskan kesalahan.
     *
     * @return array<string, mixed> konteks yang diberikan saat exception dibuat
     */
    public function konteks(): array
    {
        return $this->konteks;
    }

    /**
     * Nama isian form tempat pesan galat ditampilkan. "umum" untuk pesan yang
     * tidak terkait satu isian.
     *
     * @return string nama isian form
     */
    public function kunciGalat(): string
    {
        return 'umum';
    }

    /**
     * Kode status HTTP untuk respons JSON.
     *
     * @return int kode status, bawaannya 422
     */
    public function statusHttp(): int
    {
        return 422;
    }

    /**
     * Mengubah exception menjadi respons: JSON untuk permintaan JSON, atau
     * redirect kembali ke form beserta pesan galat.
     *
     * @param  Request  $request  permintaan yang sedang diproses
     * @return JsonResponse|RedirectResponse respons galat
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $pesan = $this->getMessage();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'kode' => $this->kodeKesalahan(),
                'message' => $pesan,
                'errors' => [$this->kunciGalat() => [$pesan]],
            ], $this->statusHttp());
        }

        return redirect()->back()
            ->withInput($request->except(self::ISIAN_TIDAK_DIKEMBALIKAN))
            ->withErrors([$this->kunciGalat() => $pesan]);
    }
}
