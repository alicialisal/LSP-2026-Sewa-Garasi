<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Models\Garage;
use App\Domain\Models\Vehicle;
use App\Domain\Rules\BatteryLevelRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalRequest;
use App\Services\RentalService;
use App\Support\DateCalculator;
use App\Support\RentalAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengajuan sewa oleh penyewa APPROVED (middleware penyewa.verified).
 * garasiTersedia() dan estimasi() melayani form lewat JSON; aturan periode
 * tetap dijalankan server di RentalTypeResolver (asumsi nomor 19). Melayani
 * route /sewa dan memanggil RentalService serta RentalAuditLogger.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RentalController extends Controller
{
    public function __construct(
        private readonly RentalService $service,
        private readonly RentalAuditLogger $audit,
    ) {
    }

    /**
     * Halaman awal penyewa: riwayat sewanya, terbaru lebih dulu.
     *
     * @param  Request  $request  permintaan dari penyewa yang login
     * @return View halaman tenant.sewa.index
     */
    public function index(Request $request): View
    {
        return view('tenant.sewa.index', [
            'riwayat' => $this->service->riwayat($request->user()->penyewa->id),
        ]);
    }

    /**
     * Hari sebulan dikirim ke form agar Alpine.js dapat menerapkan konversi
     * harian ke bulanan seketika (asumsi nomor 19). Kendaraan tersimpan milik
     * penyewa ikut dikirim sebagai pilihan isian.
     *
     * @param  Request  $request  permintaan dari penyewa yang login
     * @param  DateCalculator  $kalender  penghitung jumlah hari sebulan
     * @return View halaman form sewa baru (tenant.sewa.create)
     */
    public function create(Request $request, DateCalculator $kalender): View
    {
        $hariIni = CarbonImmutable::today();

        return view('tenant.sewa.create', [
            'tipeKendaraan' => VehicleType::cases(),
            'tipeSewa' => RentalType::cases(),
            'hariIni' => $hariIni,
            'hariSebulan' => $kalender->jumlahHariSebulan($hariIni),
            'kendaraanTersimpan' => $this->service->kendaraanTersimpan($request->user()->penyewa->id)
                ->map(fn (Vehicle $k) => [
                    'id' => $k->id,
                    'label' => ($k->nomor_plat ? "{$k->nomor_plat} · " : '')."{$k->merek} {$k->tipe}"
                        .($k->nomor_plat ? '' : ' (sepeda)')
                        .($k->sedang_disewa ? ' (sedang disewa)' : ''),
                    'tipe_kendaraan' => $k->type->value,
                    'nomor_plat' => $k->nomor_plat ?? '',
                    'merek' => $k->merek,
                    'tipe' => $k->tipe,
                    'kapasitas_baterai_kwh' => $k->kapasitas_baterai_kwh ?? '',
                    'sedang_disewa' => (bool) $k->sedang_disewa,
                ])->values()->all(),
        ]);
    }

    /**
     * Daftar garasi yang tersedia hari ini dan cocok dengan tipe kendaraan,
     * beserta harganya (JSON).
     *
     * @param  Request  $request  query "tipe_kendaraan" yang wajib diisi
     * @return JsonResponse daftar garasi tersedia dalam kunci "data"
     */
    public function garasiTersedia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipe_kendaraan' => ['required', Rule::enum(VehicleType::class)],
        ]);

        $garasi = $this->service->garasiTersedia(VehicleType::from($data['tipe_kendaraan']), CarbonImmutable::today());

        return new JsonResponse([
            'data' => $garasi->map(fn (Garage $g) => [
                'id' => $g->id,
                'tipe' => $g->type->value,
                'label' => $g->type->label(),
                'harga_sewa_harian' => $g->harga_sewa_harian,
                'harga_sewa_bulanan' => $g->hargaSewaBulanan(),
                'harga_listrik_per_kwh' => $g->harga_listrik_per_kwh,
            ])->values(),
        ]);
    }

    /**
     * Cek dini nomor plat saat diketik (JSON): struktur, plat milik penyewa
     * lain, dan kendaraan yang masih disewa. Sewa tetap dicek ulang saat disimpan.
     *
     * @param  Request  $request  query "nomor_plat" yang dicek
     * @return JsonResponse hasil cek nomor plat dalam format JSON
     */
    public function cekPlat(Request $request): JsonResponse
    {
        $data = $request->validate(['nomor_plat' => ['required', 'string', 'max:20']]);

        return new JsonResponse($this->service->cekNomorPlat(
            $request->user()->penyewa->id,
            $data['nomor_plat'],
            CarbonImmutable::today(),
        ));
    }

    /**
     * Estimasi periode dan biaya sewa sebelum disimpan (JSON). Sisa baterai
     * mobil listrik di bawah batas ditolak dengan galat validasi.
     *
     * @param  Request  $request  query tipe kendaraan, tipe sewa, jumlah hari, kode garasi, dan data baterai
     * @return JsonResponse estimasi periode dan biaya dalam format JSON
     *
     * @throws ValidationException jika sisa baterai mobil listrik di bawah batas
     */
    public function estimasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipe_kendaraan' => ['required', Rule::enum(VehicleType::class)],
            'tipe_sewa' => ['required', Rule::enum(RentalType::class)],
            'jumlah_hari' => [Rule::requiredIf($request->input('tipe_sewa') === RentalType::HARIAN->value), 'nullable', 'integer', 'min:1'],
            'garage_id' => ['required', 'regex:/^[SCE]\d{3}$/'],
            'kapasitas_baterai_kwh' => ['nullable', 'numeric', 'gt:0'],
            'sisa_baterai_kwh' => ['nullable', 'numeric', 'min:0'],
        ]);

        $kendaraan = VehicleType::from($data['tipe_kendaraan']);
        $kapasitas = isset($data['kapasitas_baterai_kwh']) ? (float) $data['kapasitas_baterai_kwh'] : null;
        $sisa = isset($data['sisa_baterai_kwh']) ? (float) $data['sisa_baterai_kwh'] : null;

        $baterai = new BatteryLevelRule();
        if ($kendaraan->wajibInfoBaterai() && $kapasitas !== null && $sisa !== null && ! $baterai->lolos($kapasitas, $sisa)) {
            throw ValidationException::withMessages(['sisa_baterai_kwh' => $baterai->pesan()]);
        }

        $estimasi = $this->service->hitungEstimasi([
            'penyewaId' => $request->user()->penyewa->id,
            'tipeKendaraan' => $kendaraan,
            'nomorPlat' => null,
            'merek' => '',
            'tipe' => '',
            'kapasitasBateraiKwh' => $kapasitas,
            'sisaBateraiKwh' => $sisa,
            'tipeSewa' => RentalType::from($data['tipe_sewa']),
            'jumlahHari' => (int) ($data['jumlah_hari'] ?? 0),
            'garageId' => $data['garage_id'],
        ]);

        $periode = $estimasi->periode;
        $biaya = $estimasi->biaya;

        return new JsonResponse([
            'tipe_sewa' => $periode->tipeSewa->value,
            'jumlah_hari' => $periode->jumlahHari,
            'tanggal_mulai' => $periode->tanggalMulai->toDateString(),
            'tanggal_selesai' => $periode->tanggalSelesai->toDateString(),
            'dikonversi_otomatis' => $periode->dikonversiOtomatis,
            'melebihi_batas' => $periode->melebihiBatas,
            'pesan' => $periode->pesanNotifikasi(),
            'biaya_sewa' => $biaya->biayaSewa,
            'jumlah_listrik_kwh' => $biaya->jumlahListrikKwh,
            'harga_listrik_per_kwh' => $biaya->hargaListrikPerKwh,
            'biaya_listrik' => $biaya->biayaListrik,
            'total' => $biaya->total,
        ]);
    }

    /**
     * Menyimpan sewa baru beserta bukti bayar dan mencatatnya di log audit.
     *
     * @param  StoreRentalRequest  $request  isian kendaraan, tipe sewa, garasi, dan bukti bayar
     * @return RedirectResponse ke riwayat sewa dengan nomor nota, ringkasan, dan notifikasi
     */
    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $data = $request->toData();

        // Periode dihitung ulang hanya untuk mengambil pesan notifikasi; sewa
        // yang tersimpan tetap ditentukan oleh RentalService.
        $notifikasi = $this->service->hitungEstimasi($data)->periode->pesanNotifikasi();

        $sewa = $this->service->buatSewa($data, $request->file('bukti_bayar'));
        $this->audit->catatPembuatanSewa($sewa);

        return redirect()->route('sewa.index')->with([
            'status' => "Sewa tersimpan dengan nomor nota {$sewa->nomor_nota}.",
            'nomor_nota' => $sewa->nomor_nota,
            'tipe_sewa' => $sewa->tipe_sewa->value,
            'tanggal_selesai' => $sewa->tanggal_selesai->toDateString(),
            'total_biaya' => $sewa->total_biaya,
            'notifikasi' => $notifikasi !== '' ? $notifikasi : null,
        ]);
    }
}
