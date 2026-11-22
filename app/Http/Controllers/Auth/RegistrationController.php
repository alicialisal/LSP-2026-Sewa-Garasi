<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterPenyewaRequest;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pendaftaran akun penyewa oleh tamu (route /daftar): form, cek dini NIK dan
 * email, serta penyimpanan lewat RegistrationService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $service)
    {
    }

    /**
     * Menampilkan form pendaftaran penyewa.
     *
     * @return View halaman auth.register
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Cek dini NIK dan email saat form diketik (JSON). Form hanya memanggil
     * ini setelah format isian benar; format salah dijawab 422.
     *
     * @param  Request  $request  query opsional "nik" dan "email" yang dicek
     * @return JsonResponse hasil cek ketersediaan NIK dan email dalam format JSON
     */
    public function cek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik' => ['nullable', 'digits:16'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        return new JsonResponse($this->service->cekKetersediaan($data['nik'] ?? null, $data['email'] ?? null));
    }

    /**
     * Menyimpan pendaftaran penyewa baru yang menunggu verifikasi admin.
     *
     * @param  RegisterPenyewaRequest  $request  isian NIK, nama, nomor telepon, email, dan kata sandi
     * @return RedirectResponse ke halaman login dengan pesan status
     */
    public function store(RegisterPenyewaRequest $request): RedirectResponse
    {
        $this->service->daftar($request->toData());

        return redirect()
            ->route('login')
            ->with('status', 'Pendaftaran berhasil. Akun Anda menunggu verifikasi admin sebelum dapat mengajukan sewa.');
    }
}
