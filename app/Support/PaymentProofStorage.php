<?php

namespace App\Support;

use App\Exceptions\BuktiBayarTidakValidException;
use finfo;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Exceptions\RuntimeException as InterventionException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Penyimpanan bukti bayar (asumsi nomor 28): JPG, JPEG, atau PNG, maksimal
 * 5 MB. Jenis berkas dicek dari isinya, bukan dari ekstensi, lalu dibaca
 * dengan intervention/image sehingga berkas rusak ditolak dan gambar besar
 * diperkecil. Nama berkas diacak sehingga dua berkas bernama sama tidak
 * saling menimpa. Dipakai RentalService dan ExtensionService; batasnya juga
 * dipakai trait ValidasiBuktiBayar.
 *
 * Kode eksternal:
 * Library: intervention/image 3.11 (lisensi MIT)
 * Fungsi: membaca gambar bukti bayar (read) untuk menolak berkas rusak dan
 *         memperkecil gambar besar (scaleDown, lalu encodeByMediaType)
 * Alasan: API sederhana untuk GD, dipakai luas di ekosistem Laravel
 * Docs: https://image.intervention.io/v3
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class PaymentProofStorage
{
    /**
     * Ukuran maksimal dalam kilobyte (5 MB).
     */
    public const MAKS_KB = 5120;

    /**
     * Jenis isi berkas yang diizinkan beserta ekstensi simpannya.
     *
     * @var array<string, string>
     */
    public const TIPE_DIIZINKAN = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Sisi terpanjang maksimal (piksel). Gambar yang lebih besar diperkecil
     * dengan rasio tetap agar tidak membebani penyimpanan (bagian E).
     */
    public const MAKS_SISI_PX = 1600;

    /**
     * Disk penyimpanan. Disk publik dipilih karena url() menampilkan bukti.
     */
    public const DISK = 'public';

    public function __construct(private readonly FilesystemManager $disk)
    {
    }

    /**
     * Menyimpan berkas ke folder dan mengembalikan path relatif di disk.
     *
     * @param  UploadedFile  $berkas  berkas bukti bayar yang diunggah, maksimal MAKS_KB
     * @param  string  $folder  folder tujuan di disk publik
     * @return string path relatif berkas tersimpan
     *
     * @throws BuktiBayarTidakValidException jika ukuran atau jenis tidak sesuai, atau gambar tidak dapat dibaca
     */
    public function simpan(UploadedFile $berkas, string $folder): string
    {
        $ukuranKb = ($berkas->getSize() ?: 0) / 1024;

        if ($ukuranKb > self::MAKS_KB) {
            throw new BuktiBayarTidakValidException(
                'Ukuran bukti bayar melebihi '.(self::MAKS_KB / 1024).' MB.',
                ['ukuran_kb' => $ukuranKb],
            );
        }

        $jenis = (new finfo(FILEINFO_MIME_TYPE))->file((string) $berkas->getRealPath());

        if (! is_string($jenis) || ! array_key_exists($jenis, self::TIPE_DIIZINKAN)) {
            throw new BuktiBayarTidakValidException(
                'Bukti bayar harus berupa gambar JPG, JPEG, atau PNG.',
                ['jenis' => $jenis],
            );
        }

        $gambar = $this->bacaGambar($berkas);
        $path = trim($folder, '/').'/'.Str::uuid().'.'.self::TIPE_DIIZINKAN[$jenis];
        $penyimpanan = $this->disk->disk(self::DISK);

        if ($gambar->width() <= self::MAKS_SISI_PX && $gambar->height() <= self::MAKS_SISI_PX) {
            // Gambar sudah cukup kecil: disimpan apa adanya tanpa kompresi ulang.
            $penyimpanan->put($path, (string) file_get_contents((string) $berkas->getRealPath()));

            return $path;
        }

        $gambar->scaleDown(width: self::MAKS_SISI_PX, height: self::MAKS_SISI_PX);
        $penyimpanan->put($path, (string) $gambar->encodeByMediaType($jenis));

        return $path;
    }

    /**
     * Membaca isi berkas sebagai gambar. Berkas yang lolos pemeriksaan jenis
     * tetapi rusak ditolak di sini.
     *
     * @throws BuktiBayarTidakValidException jika intervention/image gagal membaca berkas
     */
    private function bacaGambar(UploadedFile $berkas): ImageInterface
    {
        try {
            return ImageManager::gd()->read((string) $berkas->getRealPath());
        } catch (InterventionException $e) {
            throw new BuktiBayarTidakValidException(
                'Bukti bayar tidak dapat dibaca sebagai gambar.',
                ['alasan' => $e->getMessage()],
            );
        }
    }

    /**
     * Menghapus berkas bukti bayar dari disk publik.
     *
     * @param  string  $path  path relatif berkas, hasil simpan()
     */
    public function hapus(string $path): void
    {
        $this->disk->disk(self::DISK)->delete($path);
    }

    /**
     * Alamat bukti relatif terhadap host (misalnya /storage/bukti-bayar/...).
     * Host dari APP_URL dibuang agar gambar tetap tampil walaupun aplikasi
     * dibuka di alamat lain, misalnya http://127.0.0.1:8000 saat APP_URL masih
     * http://localhost. Subfolder aplikasi tetap dipertahankan.
     *
     * @param  string  $path  path relatif berkas, hasil simpan()
     * @return string alamat bukti tanpa skema dan host
     */
    public function url(string $path): string
    {
        $url = $this->disk->disk(self::DISK)->url($path);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
