<?php

namespace App\Support;

use App\Domain\Enums\GarageType;
use App\Exceptions\KuotaGarasiHabisException;
use InvalidArgumentException;

/**
 * Format kode garasi: awalan tipe dan tiga digit nomor (S001, C010, E999).
 * Fungsi murni tanpa status. Dipakai GarageIdGenerator dan
 * GarageProvisioningService.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class GarageCodeFormatter
{
    /**
     * Kuota garasi per tipe (asumsi nomor 13).
     */
    public const KUOTA_PER_TIPE = 999;

    /**
     * Menyusun kode garasi dari tipe dan nomor urut.
     *
     * @param  GarageType  $tipe  tipe garasi, menentukan huruf awalan
     * @param  int  $nomor  nomor urut garasi, 1 sampai KUOTA_PER_TIPE
     * @return string kode garasi, misalnya S001
     *
     * @throws InvalidArgumentException jika nomor kurang dari satu
     * @throws KuotaGarasiHabisException jika nomor melebihi kuota per tipe
     */
    public function format(GarageType $tipe, int $nomor): string
    {
        if ($nomor < 1) {
            throw new InvalidArgumentException('Nomor garasi minimal 1.');
        }

        if ($nomor > self::KUOTA_PER_TIPE) {
            throw new KuotaGarasiHabisException(
                'Kuota garasi tipe ini sudah habis.',
                ['tipe' => $tipe->value, 'nomor' => $nomor],
            );
        }

        return $tipe->prefixKode().str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Menguraikan kode menjadi tipe dan nomor.
     *
     * @param  string  $kode  kode garasi, misalnya C010
     * @return array{0: GarageType, 1: int} tipe garasi dan nomor urutnya
     *
     * @throws InvalidArgumentException jika kode tidak valid
     */
    public function urai(string $kode): array
    {
        if (! $this->valid($kode)) {
            throw new InvalidArgumentException("Kode garasi tidak valid: {$kode}");
        }

        foreach (GarageType::cases() as $tipe) {
            if ($tipe->prefixKode() === $kode[0]) {
                return [$tipe, (int) substr($kode, 1)];
            }
        }

        throw new InvalidArgumentException("Kode garasi tidak valid: {$kode}");
    }

    /**
     * Kode valid bila berawalan S, C, atau E diikuti tiga digit 001-999.
     *
     * @param  string  $kode  kode garasi yang diperiksa
     * @return bool true jika format kode valid
     */
    public function valid(string $kode): bool
    {
        return preg_match('/^[SCE](?!000)\d{3}$/', $kode) === 1;
    }
}
