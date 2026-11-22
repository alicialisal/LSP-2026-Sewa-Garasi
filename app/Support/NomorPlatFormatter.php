<?php

namespace App\Support;

/**
 * Plat nomor kendaraan Indonesia: kode wilayah (1-2 huruf) + nomor
 * registrasi (1-4 angka, tidak diawali 0) + seri huruf belakang (opsional,
 * 1-3 huruf). Disimpan seragam dalam huruf besar dengan satu spasi di antara
 * bagian, misalnya "DD 1234 AB" atau "D 777". Fungsi murni tanpa status.
 * Dipakai RentalService dan StoreRentalRequest.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class NomorPlatFormatter
{
    /**
     * Pesan galat bila struktur plat tidak sesuai, dipakai FormRequest dan service.
     */
    public const PESAN = 'Format nomor plat tidak sesuai. Contoh: DD 1234 AB (kode wilayah 1-2 huruf, nomor 1-4 angka, seri huruf maksimal 3).';

    /**
     * Pola plat yang sudah dinormalisasi, untuk aturan validasi.
     */
    public const POLA_TERSIMPAN = '/^[A-Z]{1,2} [1-9][0-9]{0,3}( [A-Z]{1,3})?$/';

    /**
     * Mengubah isian bebas (huruf kecil, tanpa spasi, spasi ganda, tanda
     * hubung) menjadi format simpan. Mengembalikan null bila strukturnya
     * tidak sesuai plat Indonesia.
     *
     * @param  string  $plat  nomor plat sesuai isian pengguna
     * @return string|null plat dalam format simpan, atau null jika tidak valid
     */
    public function normalisasi(string $plat): ?string
    {
        $rapat = strtoupper((string) preg_replace('/[\s\-]+/', '', $plat));

        if (preg_match('/^([A-Z]{1,2})([1-9][0-9]{0,3})([A-Z]{0,3})$/', $rapat, $bagian) !== 1) {
            return null;
        }

        return trim("{$bagian[1]} {$bagian[2]} {$bagian[3]}");
    }
}
