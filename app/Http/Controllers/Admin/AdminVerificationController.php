<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectPenyewaRequest;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Verifikasi pendaftaran penyewa oleh admin (route /admin/verifikasi):
 * menampilkan pendaftar yang menunggu, lalu menerima atau menolaknya lewat
 * RegistrationService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AdminVerificationController extends Controller
{
    public function __construct(private readonly RegistrationService $service)
    {
    }

    /**
     * Menampilkan daftar pendaftar yang menunggu verifikasi.
     *
     * @return View halaman admin.verifikasi.index
     */
    public function index(): View
    {
        return view('admin.verifikasi.index', [
            'pendaftar' => $this->service->daftarMenungguVerifikasi(),
        ]);
    }

    /**
     * Menerima pendaftaran penyewa atas nama admin yang login.
     *
     * @param  int  $penyewa  id penyewa yang diterima
     * @return RedirectResponse kembali ke daftar verifikasi dengan pesan status
     */
    public function terima(int $penyewa): RedirectResponse
    {
        $hasil = $this->service->terima($penyewa, (int) Auth::id());

        return redirect()->route('admin.verifikasi.index')
            ->with('status', "Pendaftaran {$hasil->nama_lengkap} diterima.");
    }

    /**
     * Menolak pendaftaran penyewa beserta alasannya atas nama admin yang login.
     *
     * @param  RejectPenyewaRequest  $request  isian alasan penolakan
     * @param  int  $penyewa  id penyewa yang ditolak
     * @return RedirectResponse kembali ke daftar verifikasi dengan pesan status
     */
    public function tolak(RejectPenyewaRequest $request, int $penyewa): RedirectResponse
    {
        $hasil = $this->service->tolak($penyewa, (int) Auth::id(), $request->validated('alasan'));

        return redirect()->route('admin.verifikasi.index')
            ->with('status', "Pendaftaran {$hasil->nama_lengkap} ditolak.");
    }
}
