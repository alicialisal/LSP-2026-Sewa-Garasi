<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGarageBatchRequest;
use App\Services\GarageProvisioningService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengelolaan garasi oleh admin (route /admin/garasi): daftar garasi dan
 * pembuatan garasi berkelompok lewat GarageProvisioningService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AdminGarageController extends Controller
{
    public function __construct(private readonly GarageProvisioningService $service)
    {
    }

    /**
     * Daftar garasi beserta statusnya, filter per tipe, dan ringkasan
     * terpakai serta kosong. Tambahan atas permintaan pengguna; tidak ada di
     * diagram use case maupun B.4.
     *
     * @param  Request  $request  query opsional "tipe" untuk filter tipe garasi
     * @return View halaman admin.garasi.index
     */
    public function index(Request $request): View
    {
        $data = $request->validate([
            'tipe' => ['nullable', Rule::enum(GarageType::class)],
        ], [
            'tipe.enum' => 'Tipe garasi tidak dikenal.',
        ]);

        $tipeAktif = isset($data['tipe']) ? GarageType::from($data['tipe']) : null;

        // Urutan penting: daftarGarasi() menyinkronkan status lebih dulu,
        // sehingga ringkasan memakai status yang sudah mutakhir.
        $garasi = $this->service->daftarGarasi($tipeAktif, CarbonImmutable::today());

        return view('admin.garasi.index', [
            'garasi' => $garasi,
            'ringkasan' => $this->service->ringkasan(),
            'tipeGarasi' => GarageType::cases(),
            'tipeAktif' => $tipeAktif,
        ]);
    }

    /**
     * Menampilkan form pembuatan garasi beserta sisa kuota setiap tipe.
     *
     * @return View halaman admin.garasi.create
     */
    public function create(): View
    {
        $sisaKuota = [];

        foreach (GarageType::cases() as $tipe) {
            $sisaKuota[$tipe->value] = $this->service->sisaKuota($tipe);
        }

        return view('admin.garasi.create', [
            'tipeGarasi' => GarageType::cases(),
            'sisaKuota' => $sisaKuota,
        ]);
    }

    /**
     * Membuat sejumlah garasi sekaligus dan melaporkan rentang kode garasi baru.
     *
     * @param  StoreGarageBatchRequest  $request  isian tipe, jumlah, dan harga garasi
     * @return RedirectResponse kembali ke daftar garasi dengan pesan status
     */
    public function store(StoreGarageBatchRequest $request): RedirectResponse
    {
        $kode = $this->service->buatBerkelompok($request->toData())->pluck('id');

        $pesan = $kode->count() === 1
            ? "1 garasi dibuat: {$kode->first()}."
            : "{$kode->count()} garasi dibuat: {$kode->first()} sampai {$kode->last()}.";

        return redirect()->route('admin.garasi.index')->with('status', $pesan);
    }
}
