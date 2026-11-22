<?php

namespace App\Http\Requests;

use App\Domain\Enums\GarageType;
use App\Services\GarageProvisioningService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pembuatan garasi berkelompok. Jumlah dibatasi sisa kuota tipe (FT-04) dan
 * harga listrik wajib untuk garasi mobil listrik (FT-03).
 * Dipakai route POST /admin/garasi (AdminGarageController::store()).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class StoreGarageBatchRequest extends FormRequest
{
    /**
     * Hak akses sudah dijaga middleware admin pada route.
     *
     * @return bool selalu true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi tipe, jumlah, dan harga garasi. Jumlah dicek terhadap
     * sisa kuota lewat GarageProvisioningService.
     *
     * @return array<string, array<int, mixed>> aturan validasi per isian
     */
    public function rules(): array
    {
        $tipe = GarageType::tryFrom((string) $this->input('tipe_garasi'));

        return [
            'tipe_garasi' => ['required', Rule::enum(GarageType::class)],
            'jumlah' => ['bail', 'required', 'integer', 'min:1', function (string $atribut, mixed $nilai, Closure $gagal) use ($tipe) {
                if ($tipe === null) {
                    return;
                }

                $sisa = $this->container->make(GarageProvisioningService::class)->sisaKuota($tipe);

                if ((int) $nilai > $sisa) {
                    $gagal("Jumlah melebihi sisa kuota tipe ini (sisa kuota {$sisa}).");
                }
            }],
            'harga_sewa_harian' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'harga_listrik_per_kwh' => [Rule::requiredIf($tipe === GarageType::ELECTRIC), 'nullable', 'numeric', 'gt:0', 'max:99999999.99'],
        ];
    }

    /**
     * Pesan galat validasi dalam bahasa Indonesia.
     *
     * @return array<string, string> pesan per aturan isian
     */
    public function messages(): array
    {
        return [
            'tipe_garasi.required' => 'Tipe garasi wajib dipilih.',
            'jumlah.required' => 'Jumlah garasi wajib diisi.',
            'jumlah.min' => 'Jumlah garasi minimal 1.',
            'harga_sewa_harian.required' => 'Harga sewa harian wajib diisi.',
            'harga_sewa_harian.gt' => 'Harga sewa harian harus lebih dari 0.',
            'harga_listrik_per_kwh.required' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik.',
            'harga_listrik_per_kwh.gt' => 'Harga listrik per kWh harus lebih dari 0.',
        ];
    }

    /**
     * Data tervalidasi untuk service, kunci sesuai nama properti.
     *
     * @return array{tipeGarasi: GarageType, jumlah: int, hargaSewaHarian: float, hargaListrikPerKwh: ?float}
     */
    public function toData(): array
    {
        $listrik = $this->validated('harga_listrik_per_kwh');

        return [
            'tipeGarasi' => GarageType::from($this->validated('tipe_garasi')),
            'jumlah' => (int) $this->validated('jumlah'),
            'hargaSewaHarian' => (float) $this->validated('harga_sewa_harian'),
            'hargaListrikPerKwh' => $listrik === null ? null : (float) $listrik,
        ];
    }
}
