<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi isian form login (email dan kata sandi) untuk route POST /login
 * yang ditangani AuthController::login().
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class LoginRequest extends FormRequest
{
    /**
     * Semua tamu boleh mengirim form login.
     *
     * @return bool selalu true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi isian login.
     *
     * @return array<string, array<int, string>> aturan validasi per isian
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
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
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }
}
