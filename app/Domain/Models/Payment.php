<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran sewa awal (rental_extension_id kosong) atau perpanjangan.
 * Pembayaran sah saat transaksi tersimpan, tanpa status pembayaran.
 *
 * Kolom penting: jumlah dalam rupiah, path_bukti_bayar (lokasi file bukti
 * bayar), diunggah_oleh (id user pengunggah), dan diunggah_pada.
 *
 * Relasi: milik satu sewa (Rental) dan, bila pembayaran perpanjangan, milik
 * satu perpanjangan (RentalExtension).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable(['rental_id', 'rental_extension_id', 'jumlah', 'path_bukti_bayar', 'diunggah_oleh', 'diunggah_pada'])]
class Payment extends Model
{
    /**
     * Tabel payments memakai diunggah_pada, bukan created_at/updated_at.
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'float',
            'diunggah_pada' => 'datetime',
        ];
    }

    /**
     * Sewa yang dibayar.
     *
     * @return BelongsTo sewa pemilik pembayaran ini
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class, 'rental_id');
    }

    /**
     * Perpanjangan yang dibayar; kosong untuk pembayaran sewa awal.
     *
     * @return BelongsTo perpanjangan pemilik pembayaran ini
     */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(RentalExtension::class, 'rental_extension_id');
    }
}
