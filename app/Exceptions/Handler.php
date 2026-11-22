<?php

namespace App\Exceptions;

use App\Support\RentalAuditLogger;
use DomainException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Penanganan kesalahan aplikasi (diagram B.6). Dipasang menggantikan handler
 * bawaan lewat withSingletons() di bootstrap/app.php.
 *
 * - AppException: penolakan aturan bisnis yang wajar, dicatat sebagai
 *   peringatan lewat RentalAuditLogger (bukan galat dengan stack trace).
 *   Tampilannya diatur oleh AppException::render().
 * - DomainException dari service (misalnya sewa tidak aktif, penyewa bukan
 *   PENDING): dicatat dengan cara yang sama dan ditampilkan sebagai pesan umum.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class Handler extends ExceptionHandler
{
    private const ISIAN_TIDAK_DIKEMBALIKAN = ['password', 'password_confirmation', 'bukti_bayar'];

    /**
     * Mendaftarkan pencatatan AppException dan DomainException serta tampilan pesan umum DomainException.
     */
    public function register(): void
    {
        $this->reportable(function (AppException $e) {
            $this->audit()->catatKegagalan($e->kodeKesalahan(), $e->konteks());
        })->stop();

        $this->reportable(function (DomainException $e) {
            $this->audit()->catatKegagalan('DOMAIN', ['pesan' => $e->getMessage()]);
        })->stop();

        $this->renderable(fn (DomainException $e, Request $request) => $this->tampilkanPesanUmum($e, $request));
    }

    private function audit(): RentalAuditLogger
    {
        return $this->container->make(RentalAuditLogger::class);
    }

    private function tampilkanPesanUmum(DomainException $e, Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'errors' => ['umum' => [$e->getMessage()]],
            ], 422);
        }

        return redirect()->back()
            ->withInput($request->except(self::ISIAN_TIDAK_DIKEMBALIKAN))
            ->withErrors(['umum' => $e->getMessage()]);
    }
}
