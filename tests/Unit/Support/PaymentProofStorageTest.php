<?php

namespace Tests\Unit\Support;

use App\Exceptions\BuktiBayarTidakValidException;
use App\Support\PaymentProofStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Memakai Storage::fake(). Batas ukuran 5 MB = 5120 KB.
 */
class PaymentProofStorageTest extends TestCase
{
    private PaymentProofStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PaymentProofStorage::DISK);
        $this->storage = $this->app->make(PaymentProofStorage::class);
    }

    /**
     * Alamat bukti relatif terhadap host, agar gambar tetap tampil walaupun
     * APP_URL (misalnya http://localhost) berbeda dengan alamat aplikasi
     * dijalankan (misalnya http://127.0.0.1:8000 lewat php artisan serve).
     */
    public function test_url_bukti_relatif_terhadap_host(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Storage::forgetDisk(PaymentProofStorage::DISK);

        $url = $this->app->make(PaymentProofStorage::class)->url('bukti-bayar/2026/11/bukti.png');

        $this->assertSame('/storage/bukti-bayar/2026/11/bukti.png', $url);
    }

    public function test_url_bukti_mempertahankan_subfolder_aplikasi(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/garasi-sewa/public/storage']);
        Storage::forgetDisk(PaymentProofStorage::DISK);

        $url = $this->app->make(PaymentProofStorage::class)->url('bukti-bayar/bukti.png');

        $this->assertSame('/garasi-sewa/public/storage/bukti-bayar/bukti.png', $url);
    }

    /**
     * UT-PS-01 - PNG 1 MB berhasil dan berada di folder yang diminta.
     */
    public function test_png_1_mb_berhasil(): void
    {
        $path = $this->storage->simpan(UploadedFile::fake()->image('bukti.png')->size(1024), 'bukti-bayar/2026/11');

        $this->assertStringStartsWith('bukti-bayar/2026/11/', $path);
        Storage::disk(PaymentProofStorage::DISK)->assertExists($path);
    }

    /**
     * UT-PS-02 - JPEG tepat 5120 KB berhasil.
     */
    public function test_jpeg_tepat_di_batas_berhasil(): void
    {
        $path = $this->storage->simpan(UploadedFile::fake()->image('bukti.jpg')->size(5120), 'bukti-bayar');

        Storage::disk(PaymentProofStorage::DISK)->assertExists($path);
    }

    /**
     * UT-PS-03 - JPEG 5121 KB ditolak.
     */
    public function test_jpeg_melebihi_batas_ditolak(): void
    {
        $this->expectException(BuktiBayarTidakValidException::class);

        $this->storage->simpan(UploadedFile::fake()->image('bukti.jpg')->size(5121), 'bukti-bayar');
    }

    /**
     * UT-PS-04 - PDF ditolak.
     */
    public function test_pdf_ditolak(): void
    {
        $this->expectException(BuktiBayarTidakValidException::class);

        $this->storage->simpan(UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'), 'bukti-bayar');
    }

    /**
     * UT-PS-05 - Berkas berekstensi .jpg tetapi isinya teks ditolak (jenis dicek dari isi).
     */
    public function test_ekstensi_jpg_dengan_isi_teks_ditolak(): void
    {
        $this->expectException(BuktiBayarTidakValidException::class);

        $this->storage->simpan(UploadedFile::fake()->createWithContent('bukti.jpg', 'ini bukan gambar'), 'bukti-bayar');
    }

    /**
     * UT-PS-06 - Dua berkas bernama sama menghasilkan dua path berbeda.
     */
    public function test_nama_sama_tidak_saling_menimpa(): void
    {
        $pertama = $this->storage->simpan(UploadedFile::fake()->image('bukti.png'), 'bukti-bayar');
        $kedua = $this->storage->simpan(UploadedFile::fake()->image('bukti.png'), 'bukti-bayar');

        $this->assertNotSame($pertama, $kedua);
        Storage::disk(PaymentProofStorage::DISK)->assertExists($pertama);
        Storage::disk(PaymentProofStorage::DISK)->assertExists($kedua);
    }

    /**
     * UT-PS-07 - hapus() menghapus berkas dari disk.
     */
    public function test_hapus_menghapus_berkas(): void
    {
        $path = $this->storage->simpan(UploadedFile::fake()->image('bukti.png'), 'bukti-bayar');

        $this->storage->hapus($path);

        Storage::disk(PaymentProofStorage::DISK)->assertMissing($path);
    }

    /**
     * Gambar lebih besar dari 1600 px diperkecil dengan rasio tetap (intervention/image).
     */
    public function test_gambar_besar_diperkecil_dengan_rasio_tetap(): void
    {
        $path = $this->storage->simpan(UploadedFile::fake()->image('bukti.jpg', 3200, 2000), 'bukti-bayar');

        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk(PaymentProofStorage::DISK)->get($path));

        $this->assertSame(PaymentProofStorage::MAKS_SISI_PX, $lebar);
        $this->assertSame(1000, $tinggi);
    }

    public function test_gambar_kecil_disimpan_apa_adanya(): void
    {
        $berkas = UploadedFile::fake()->image('bukti.png', 800, 600);
        $isiAsli = file_get_contents($berkas->getRealPath());

        $path = $this->storage->simpan($berkas, 'bukti-bayar');

        $this->assertSame($isiAsli, Storage::disk(PaymentProofStorage::DISK)->get($path));
    }

    /**
     * Berkas yang lolos pemeriksaan jenis tetapi rusak sehingga tidak dapat dibaca sebagai gambar ditolak.
     */
    public function test_gambar_rusak_ditolak(): void
    {
        $rusak = "\xFF\xD8\xFF\xE0".str_repeat("\x00", 64);

        $this->expectException(BuktiBayarTidakValidException::class);

        $this->storage->simpan(UploadedFile::fake()->createWithContent('bukti.jpg', $rusak), 'bukti-bayar');
    }
}
