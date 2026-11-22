<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Laporan sewa (UC12 beserta ekstensinya UC13-UC16). Tanpa parameter, laporan
 * memakai tahun, bulan, atau rentang bulan berjalan. Setiap baris membawa
 * rincian transaksi beserta bukti bayarnya. Melayani route /admin/laporan
 * dan memanggil ReportService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AdminReportController extends Controller
{
    public function __construct(private readonly ReportService $service)
    {
    }

    /**
     * Menampilkan laporan pendapatan per bulan dalam satu tahun.
     *
     * @param  Request  $request  query opsional "tahun" (2000-2100)
     * @return View halaman admin.laporan.bulanan
     */
    public function bulanan(Request $request): View
    {
        $data = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $tahun = (int) ($data['tahun'] ?? CarbonImmutable::today()->year);
        $baris = $this->service->perBulan($tahun);
        $total = $this->service->totalPendapatan($baris);

        return view('admin.laporan.bulanan', compact('tahun', 'baris', 'total'));
    }

    /**
     * Menampilkan laporan pendapatan per hari dalam satu bulan.
     *
     * @param  Request  $request  query opsional "tahun" dan "bulan"
     * @return View halaman admin.laporan.harian
     */
    public function harian(Request $request): View
    {
        $data = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $hariIni = CarbonImmutable::today();
        $tahun = (int) ($data['tahun'] ?? $hariIni->year);
        $bulan = (int) ($data['bulan'] ?? $hariIni->month);
        $baris = $this->service->perHari($tahun, $bulan);
        $total = $this->service->totalPendapatan($baris);

        return view('admin.laporan.harian', compact('tahun', 'bulan', 'baris', 'total'));
    }

    /**
     * Menampilkan laporan pendapatan per tipe garasi dalam rentang tanggal.
     *
     * @param  Request  $request  query opsional "dari" dan "sampai" (Y-m-d)
     * @return View halaman admin.laporan.tipe-garasi
     */
    public function perTipeGarasi(Request $request): View
    {
        [$dari, $sampai] = $this->rentang($request);
        $baris = $this->service->perTipeGarasi($dari, $sampai);
        $total = $this->service->totalPendapatan($baris);

        return view('admin.laporan.tipe-garasi', compact('dari', 'sampai', 'baris', 'total'));
    }

    /**
     * Menampilkan laporan pendapatan per penyewa dalam rentang tanggal.
     *
     * @param  Request  $request  query opsional "dari" dan "sampai" (Y-m-d)
     * @return View halaman admin.laporan.penyewa
     */
    public function perPenyewa(Request $request): View
    {
        [$dari, $sampai] = $this->rentang($request);
        $baris = $this->service->perPenyewa($dari, $sampai);
        $total = $this->service->totalPendapatan($baris);

        return view('admin.laporan.penyewa', compact('dari', 'sampai', 'baris', 'total'));
    }

    /**
     * Rentang tanggal mulai sewa; bawaannya awal sampai akhir bulan berjalan.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rentang(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('dari'), ['after_or_equal:dari'])],
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $hariIni = CarbonImmutable::today();

        return [
            isset($data['dari']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['dari'])->startOfDay() : $hariIni->startOfMonth(),
            isset($data['sampai']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['sampai'])->startOfDay() : $hariIni->endOfMonth()->startOfDay(),
        ];
    }
}
