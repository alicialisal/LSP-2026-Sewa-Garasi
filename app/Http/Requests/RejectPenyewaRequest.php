<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi alasan penolakan pendaftaran penyewa oleh admin untuk route
 * POST /admin/verifikasi/{penyewa}/tolak (AdminVerificationController::tolak()).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class RejectPenyewaRequest extends FormRequest
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
     * Aturan validasi alasan penolakan.
     *
     * @return array<string, array<int, string>> aturan validasi per isian
     */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:1000'],
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
            'alasan.required' => 'Alasan penolakan wajib diisi.',
            'alasan.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
