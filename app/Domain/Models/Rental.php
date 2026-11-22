<?php

namespace App\Domain\Models;

use Database\Factories\RentalFactory;
use App\Domain\Enums\RentalStatus;
use App\Domain\Enums\RentalType;
use App\Support\DateCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sewa garasi. Tanggal selesai bersifat inklusif. Sisa baterai, jumlah kWh,
 * dan biaya listrik dibekukan di sewa; harga per kWh tidak disalin dan
 * diambil dari master data garasi.
 *
 * Kolom penting: nomor_nota (unik), tipe_sewa (RentalType), jumlah_hari (0 untuk
 * sewa bulanan), biaya_sewa, biaya_listrik, dan total_biaya dalam rupiah,
 * sisa_baterai_snapshot dan jumlah_listrik_kwh dalam kWh, serta status
 * (RentalStatus).
 *
 * Relasi: milik satu penyewa, satu garasi, dan satu kendaraan; punya banyak
 * perpanjangan (RentalExtension) dan banyak pembayaran (Payment).
 *
 * @author Alicia Lisal
 * @version 1.0
 */
#[Fillable([
    'nomor_nota', 'penyewa_id', 'garage_id', 'vehicle_id', 'tipe_sewa', 'jumlah_hari',
    'tanggal_mulai', 'tanggal_selesai', 'biaya_sewa', 'sisa_baterai_snapshot',
    'jumlah_listrik_kwh', 'biaya_listrik', 'total_biaya', 'status',
])]
class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    protected static function newFactory(): RentalFactory
    {
        return RentalFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe_sewa' => RentalType::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'biaya_sewa' => 'float',
            'sisa_baterai_snapshot' => 'float',
            'jumlah_listrik_kwh' => 'float',
            'biaya_listrik' => 'float',
            'total_biaya' => 'float',
            'status' => RentalStatus::class,
        ];
    }

    /**
     * Lama sewa sebenarnya, dibaca dari tanggal (hari terakhir inklusif),
     * bukan dari kolom jumlah_hari.
     *
     * @return int lama sewa dalam hari
     */
    public function durasiHari(): int
    {
        return (new DateCalculator())->selisihHari($this->tanggal_mulai, $this->tanggal_selesai) + 1;
    }

    /**
     * Sewa aktif pada tanggal tertentu: berstatus AKTIF dan tanggal berada
     * di antara tanggal mulai dan tanggal selesai (inklusif).
     *
     * @param  CarbonInterface  $tanggal  tanggal yang diperiksa; jam diabaikan
     * @return bool true jika sewa aktif pada tanggal tersebut
     */
    public function aktifPada(CarbonInterface $tanggal): bool
    {
        if ($this->status !== RentalStatus::AKTIF) {
            return false;
        }

        $hari = $tanggal->copy()->startOfDay();

        return $hari->greaterThanOrEqualTo($this->tanggal_mulai->copy()->startOfDay())
            && $hari->lessThanOrEqualTo($this->tanggal_selesai->copy()->startOfDay());
    }

    /**
     * Pembayaran saat sewa dibuat, yaitu yang tidak terkait perpanjangan.
     * Memakai relasi payments yang sudah dimuat bila ada.
     *
     * @return Payment|null pembayaran awal, atau null bila belum ada
     */
    public function pembayaranAwal(): ?Payment
    {
        return $this->payments->first(fn (Payment $p) => $p->rental_extension_id === null);
    }

    /**
     * Pembayaran untuk perpanjangan tertentu dari sewa ini.
     *
     * @param  RentalExtension  $perpanjangan  perpanjangan yang dicari pembayarannya
     * @return Payment|null pembayaran perpanjangan, atau null bila belum ada
     */
    public function pembayaranPerpanjangan(RentalExtension $perpanjangan): ?Payment
    {
        return $this->payments->first(fn (Payment $p) => (int) $p->rental_extension_id === (int) $perpanjangan->id);
    }

    /**
     * Total yang sudah dibayar penyewa: total biaya sewa ditambah biaya
     * seluruh perpanjangannya.
     *
     * @return float total dibayar dalam rupiah
     */
    public function totalDibayar(): float
    {
        return round($this->total_biaya + (float) $this->extensions->sum('biaya_tambahan'), 2);
    }

    /**
     * Penyewa pemilik sewa.
     *
     * @return BelongsTo penyewa pemilik sewa ini
     */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }

    /**
     * Garage abstrak tidak dapat dibuat oleh Eloquent (`new Garage`), sehingga
     * relasi memakai satu kelas turunan sebagai pintu query tanpa filter tipe.
     * Hasilnya tetap berkelas sesuai kolom type lewat Garage::newFromBuilder().
     *
     * @return BelongsTo garasi yang disewa
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(CarGarage::class, 'garage_id');
    }

    /**
     * Kendaraan yang diparkir.
     *
     * @return BelongsTo kendaraan pada sewa ini
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Riwayat perpanjangan sewa.
     *
     * @return HasMany daftar perpanjangan sewa ini
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class, 'rental_id');
    }

    /**
     * Pembayaran sewa awal dan perpanjangan.
     *
     * @return HasMany daftar pembayaran sewa ini
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'rental_id');
    }
}
