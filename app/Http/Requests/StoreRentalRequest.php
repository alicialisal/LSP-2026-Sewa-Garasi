<?php

namespace App\Http\Requests;

use App\Domain\Enums\RentalType;
use App\Domain\Enums\VehicleType;
use App\Domain\Rules\BatteryLevelRule;
use App\Domain\Rules\GarageCompatibilityRule;
use App\Http\Requests\Concerns\ValidasiBuktiBayar;
use App\Repositories\GarageRepository;
use App\Support\NomorPlatFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pengajuan sewa baru. Isian wajib mengikuti tipe kendaraan (asumsi nomor 10
 * dan 12). Kecocokan garasi dan batas sisa baterai memakai aturan domain yang
 * sama dengan service (GarageCompatibilityRule, BatteryLevelRule). Jumlah hari
 * di atas hari sebulan tidak ditolak; service mengubahnya menjadi bulanan.
 * Dipakai route POST /sewa (RentalController::store()).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class StoreRentalRequest extends FormRequest
{
    use ValidasiBuktiBayar;

    /**
     * Hak akses sudah dijaga middleware penyewa.verified pada route.
     *
     * @return bool selalu true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nomor plat diseragamkan ke format simpan ("DD 1234 AB") sebelum
     * divalidasi. Isian yang strukturnya tidak sesuai dibiarkan agar ditolak
     * aturan regex, sebelum bukti bayar sempat disimpan.
     */
    protected function prepareForValidation(): void
    {
        $plat = (string) $this->input('nomor_plat', '');
        $normal = $plat === '' ? null : $this->container->make(NomorPlatFormatter::class)->normalisasi($plat);

        if ($normal !== null) {
            $this->merge(['nomor_plat' => $normal]);
        }
    }

    /**
     * Aturan validasi pengajuan sewa; isian wajib bergantung tipe kendaraan
     * dan tipe sewa.
     *
     * @return array<string, array<int, mixed>> aturan validasi per isian
     */
    public function rules(): array
    {
        $kendaraan = $this->tipeKendaraan();

        return [
            'tipe_kendaraan' => ['required', Rule::enum(VehicleType::class)],
            'nomor_plat' => [Rule::requiredIf($kendaraan?->wajibNomorPlat() ?? false), 'nullable', 'string', 'max:15', 'regex:'.NomorPlatFormatter::POLA_TERSIMPAN],
            'merek' => ['required', 'string', 'max:100'],
            'tipe' => ['required', 'string', 'max:100'],
            'kapasitas_baterai_kwh' => [Rule::requiredIf($kendaraan?->wajibInfoBaterai() ?? false), 'nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'sisa_baterai_kwh' => [Rule::requiredIf($kendaraan?->wajibInfoBaterai() ?? false), 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'tipe_sewa' => ['required', Rule::enum(RentalType::class)],
            'jumlah_hari' => [Rule::requiredIf($this->input('tipe_sewa') === RentalType::HARIAN->value), 'nullable', 'integer', 'min:1'],
            'garage_id' => ['bail', 'required', 'regex:/^[SCE]\d{3}$/', 'exists:garages,id'],
            'bukti_bayar' => $this->aturanBuktiBayar(),
        ];
    }

    /**
     * Pemeriksaan lintas isian setelah aturan dasar lolos.
     *
     * @return array<int, callable> pemeriksaan kecocokan garasi dan sisa baterai
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $galat = $validator->errors();
                $kendaraan = $this->tipeKendaraan();

                if ($kendaraan === null || $galat->has('tipe_kendaraan')) {
                    return;
                }

                if (! $galat->has('garage_id')) {
                    $garasi = $this->container->make(GarageRepository::class)->cariId((string) $this->input('garage_id'));
                    $kecocokan = new GarageCompatibilityRule();

                    if ($garasi !== null && ! $kecocokan->lolos($kendaraan, $garasi->type)) {
                        $galat->add('garage_id', $kecocokan->pesan());
                    }
                }

                if ($kendaraan->wajibInfoBaterai() && ! $galat->hasAny(['kapasitas_baterai_kwh', 'sisa_baterai_kwh'])) {
                    $baterai = new BatteryLevelRule();

                    if (! $baterai->lolos((float) $this->input('kapasitas_baterai_kwh'), (float) $this->input('sisa_baterai_kwh'))) {
                        $galat->add('sisa_baterai_kwh', $baterai->pesan());
                    }
                }
            },
        ];
    }

    /**
     * Pesan galat validasi, termasuk pesan bukti bayar bersama.
     *
     * @return array<string, string> pesan per aturan isian
     */
    public function messages(): array
    {
        return array_merge([
            'tipe_kendaraan.required' => 'Tipe kendaraan wajib dipilih.',
            'nomor_plat.required' => 'Nomor plat wajib diisi untuk tipe kendaraan ini.',
            'nomor_plat.regex' => NomorPlatFormatter::PESAN,
            'nomor_plat.max' => NomorPlatFormatter::PESAN,
            'merek.required' => 'Merek kendaraan wajib diisi.',
            'tipe.required' => 'Tipe kendaraan wajib diisi.',
            'kapasitas_baterai_kwh.required' => 'Kapasitas baterai wajib diisi untuk mobil listrik.',
            'kapasitas_baterai_kwh.gt' => 'Kapasitas baterai harus lebih dari 0.',
            'sisa_baterai_kwh.required' => 'Sisa baterai wajib diisi untuk mobil listrik.',
            'sisa_baterai_kwh.min' => 'Sisa baterai tidak boleh negatif.',
            'tipe_sewa.required' => 'Tipe sewa wajib dipilih.',
            'jumlah_hari.required' => 'Jumlah hari wajib diisi untuk sewa harian.',
            'jumlah_hari.min' => 'Jumlah hari minimal 1.',
            'garage_id.required' => 'Garasi wajib dipilih.',
            'garage_id.regex' => 'Kode garasi tidak valid.',
            'garage_id.exists' => 'Garasi tidak ditemukan.',
        ], $this->pesanBuktiBayar());
    }

    /**
     * Data tervalidasi untuk service, kunci sesuai nama properti.
     *
     * @return array{penyewaId: int, tipeKendaraan: VehicleType, nomorPlat: ?string, merek: string, tipe: string, kapasitasBateraiKwh: ?float, sisaBateraiKwh: ?float, tipeSewa: RentalType, jumlahHari: int, garageId: string}
     */
    public function toData(): array
    {
        $kendaraan = VehicleType::from($this->validated('tipe_kendaraan'));
        $baterai = $kendaraan->wajibInfoBaterai();

        return [
            'penyewaId' => $this->user()->penyewa->id,
            'tipeKendaraan' => $kendaraan,
            'nomorPlat' => $kendaraan->wajibNomorPlat() ? $this->validated('nomor_plat') : null,
            'merek' => $this->validated('merek'),
            'tipe' => $this->validated('tipe'),
            'kapasitasBateraiKwh' => $baterai ? (float) $this->validated('kapasitas_baterai_kwh') : null,
            'sisaBateraiKwh' => $baterai ? (float) $this->validated('sisa_baterai_kwh') : null,
            'tipeSewa' => RentalType::from($this->validated('tipe_sewa')),
            'jumlahHari' => (int) ($this->validated('jumlah_hari') ?? 0),
            'garageId' => $this->validated('garage_id'),
        ];
    }

    private function tipeKendaraan(): ?VehicleType
    {
        return VehicleType::tryFrom((string) $this->input('tipe_kendaraan'));
    }
}
