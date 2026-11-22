<?php

namespace App\Support;

/**
 * Nomor HP Indonesia. Disimpan seragam sebagai +62 diikuti 9-12 angka yang
 * diawali 8 (maksimal 15 karakter, sesuai kolom penyewa.nomor_telepon), dan
 * ditampilkan sebagai +62 812-3456-7890. Fungsi murni tanpa status.
 * Dipakai RegisterPenyewaRequest.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class NomorTeleponFormatter
{
    public const AWALAN = '+62';

    /**
     * Mengubah 0812..., 62812..., +62 812-..., atau 812... menjadi +62812....
     * Karakter selain angka diabaikan. Mengembalikan null bila bukan nomor HP
     * Indonesia yang valid.
     *
     * @param  string  $nomor  nomor HP sesuai isian pengguna
     * @return string|null nomor dalam format simpan +62..., atau null jika tidak valid
     */
    public function normalisasi(string $nomor): ?string
    {
        $angka = preg_replace('/\D/', '', $nomor);

        if (preg_match('/^(?:62|0)?(8\d{8,11})$/', $angka, $cocok) !== 1) {
            return null;
        }

        return self::AWALAN.$cocok[1];
    }

    /**
     * Format tampilan +62 812-3456-7890. Nilai lama (misalnya 0812...) ikut
     * dirapikan; nilai yang bukan nomor HP valid dikembalikan apa adanya.
     *
     * @param  string  $nomor  nomor HP tersimpan
     * @return string nomor siap tampil
     */
    public function tampilan(string $nomor): string
    {
        $angka = $this->tanpaAwalan($nomor);

        return $angka === $nomor ? $nomor : self::AWALAN.' '.$angka;
    }

    /**
     * Angka sesudah +62 dengan tanda hubung (812-3456-7890), untuk mengisi
     * ulang form yang menampilkan +62 secara tetap. Nilai tidak valid
     * dikembalikan apa adanya.
     *
     * @param  string  $nomor  nomor HP tersimpan atau isian pengguna
     * @return string angka tanpa +62 dengan tanda hubung
     */
    public function tanpaAwalan(string $nomor): string
    {
        $normal = $this->normalisasi($nomor);

        if ($normal === null) {
            return $nomor;
        }

        $angka = substr($normal, strlen(self::AWALAN));

        return substr($angka, 0, 3).'-'.substr($angka, 3, 4).'-'.substr($angka, 7);
    }
}
