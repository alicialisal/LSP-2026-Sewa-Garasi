<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidasiBuktiBayar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Perpanjangan sewa oleh admin. Syarat "tanggal baru setelah tanggal selesai
 * lama" bergantung pada data sewa, sehingga diperiksa ExtensionService.
 * Dipakai route PUT /admin/perpanjangan/{rental} (AdminExtensionController::update()).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class StoreExtensionRequest extends FormRequest
{
    use ValidasiBuktiBayar;

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
     * Aturan validasi tanggal selesai baru dan bukti bayar.
     *
     * @return array<string, array<int, string>> aturan validasi per isian
     */
    public function rules(): array
    {
        return [
            'tanggal_selesai_baru' => ['required', 'date_format:Y-m-d'],
            'bukti_bayar' => $this->aturanBuktiBayar(),
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
            'tanggal_selesai_baru.required' => 'Tanggal selesai baru wajib diisi.',
            'tanggal_selesai_baru.date_format' => 'Tanggal selesai baru harus berformat YYYY-MM-DD.',
        ], $this->pesanBuktiBayar());
    }

    /**
     * Tanggal selesai baru yang sudah tervalidasi, pada awal hari.
     *
     * @return CarbonImmutable tanggal selesai baru pukul 00:00
     */
    public function tanggalSelesaiBaru(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->validated('tanggal_selesai_baru'))->startOfDay();
    }
}
