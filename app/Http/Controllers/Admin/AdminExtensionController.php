<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Services\ExtensionService;
use App\Support\CurrencyFormatter;
use App\Support\RentalAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Perpanjangan sewa oleh admin. Hanya sewa yang aktif hari itu yang tampil
 * dan dapat diperpanjang (asumsi nomor 29). Melayani route /admin/perpanjangan
 * dan memanggil ExtensionService serta RentalAuditLogger.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AdminExtensionController extends Controller
{
    private const RELASI_TAMPILAN = ['penyewa', 'garage', 'vehicle'];

    public function __construct(
        private readonly ExtensionService $service,
        private readonly RentalAuditLogger $audit,
        private readonly CurrencyFormatter $rupiah,
    ) {
    }

    /**
     * Menampilkan sewa yang aktif hari ini beserta penyewa, garasi, dan kendaraannya.
     *
     * @return View halaman admin.perpanjangan.index
     */
    public function index(): View
    {
        return view('admin.perpanjangan.index', [
            'sewa' => $this->service->sewaAktifHariIni()->load(self::RELASI_TAMPILAN),
        ]);
    }

    /**
     * Menampilkan form perpanjangan untuk satu sewa yang aktif hari ini;
     * 404 jika sewa tidak aktif atau tidak ditemukan.
     *
     * @param  int  $rental  id sewa yang diperpanjang
     * @return View halaman admin.perpanjangan.edit
     */
    public function edit(int $rental): View
    {
        $sewa = $this->service->sewaAktifHariIni()->firstWhere('id', $rental);

        abort_if($sewa === null, 404, 'Sewa tidak aktif hari ini atau tidak ditemukan.');

        return view('admin.perpanjangan.edit', [
            'sewa' => $sewa->load(self::RELASI_TAMPILAN),
        ]);
    }

    /**
     * Menyimpan perpanjangan sewa, mencatatnya di log audit, dan melaporkan
     * biaya tambahan.
     *
     * @param  StoreExtensionRequest  $request  isian tanggal selesai baru dan bukti bayar
     * @param  int  $rental  id sewa yang diperpanjang
     * @return RedirectResponse kembali ke daftar perpanjangan dengan pesan status
     */
    public function update(StoreExtensionRequest $request, int $rental): RedirectResponse
    {
        $perpanjangan = $this->service->perpanjang(
            $rental,
            $request->tanggalSelesaiBaru(),
            $request->file('bukti_bayar'),
            (int) Auth::id(),
        );
        $this->audit->catatPerpanjangan($perpanjangan);

        $biaya = $this->rupiah->keRupiah($perpanjangan->biaya_tambahan);

        return redirect()->route('admin.perpanjangan.index')
            ->with('status', "Perpanjangan {$perpanjangan->nomor_perpanjang} tersimpan. Biaya tambahan {$biaya}.");
    }
}
