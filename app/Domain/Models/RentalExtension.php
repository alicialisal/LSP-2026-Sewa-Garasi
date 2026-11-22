<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Riwayat perpanjangan sewa. Dihitung dengan tarif harian.
 *
 * Kolom penting: nomor_perpanjang (unik), tanggal_selesai_lama dan
 * tanggal_selesai_baru, tambahan_hari dalam hari, biaya_tambahan dalam rupiah,
 * dan diproses_oleh (id user yang memproses).
 *
 * Relasi: milik satu sewa (Rental) dan punya satu pembayaran (Payment).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable([
    'rental_id', 'nomor_perpanjang', 'tanggal_selesai_lama', 'tanggal_selesai_baru',
    'tambahan_hari', 'biaya_tambahan', 'diproses_oleh',
])]
class RentalExtension extends Model
{
    /**
     * Tabel rental_extensions hanya memiliki created_at.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_selesai_lama' => 'date',
            'tanggal_selesai_baru' => 'date',
            'biaya_tambahan' => 'float',
        ];
    }

    /**
     * Sewa yang diperpanjang.
     *
     * @return BelongsTo sewa pemilik perpanjangan ini
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class, 'rental_id');
    }

    /**
     * Pembayaran untuk perpanjangan ini.
     *
     * @return HasOne pembayaran perpanjangan
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'rental_extension_id');
    }
}
