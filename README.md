# Sistem Penyewaan Garasi Kendaraan

Aplikasi penyewaan garasi untuk sepeda, sepeda motor, mobil, dan mobil
listrik. Laravel 13, MySQL 8, Blade + Bootstrap 5 + Alpine.js, dengan
arsitektur layered monolith 3 lapis (Presentation, Business Logic, Data Access).

## Kebutuhan

- PHP 8.3 atau lebih baru, dengan ekstensi `gd`, `fileinfo`, `pdo_mysql`
- MySQL 8
- Composer 2 dan Node.js 20 atau lebih baru
- Xdebug 3, hanya untuk mengukur cakupan test

Di `php.ini`, `upload_max_filesize` minimal `5M` dan `post_max_size` minimal
`8M`, agar bukti bayar 5 MB dapat diunggah (asumsi nomor 28).

## Menjalankan secara lokal

```bash
composer install
npm install
cp .env.example .env          # isi DB_* bila berbeda dari bawaan Laragon
php artisan key:generate

# Buat dua database: sewa_garasi (aplikasi) dan sewa_garasi_test (test)
php artisan migrate --seed    # admin, garasi contoh, dan data demo
php artisan storage:link      # agar bukti bayar dapat ditampilkan

npm run build                 # atau `npm run dev` saat mengubah tampilan
php artisan serve
```

Samakan `APP_URL` di `.env` dengan alamat aplikasi dijalankan, misalnya
`http://127.0.0.1:8000` untuk `php artisan serve`.

## Halaman utama

| Peran | Halaman | Isi |
|---|---|---|
| Penyewa | `/sewa` Sewa Saya (halaman awal) | Riwayat sewa, status Aktif atau Selesai, total dibayar, lihat bukti bayar |
| Penyewa | `/sewa/baru` | Form sewa: pilih kendaraan yang pernah diparkir atau isi baru, jumlah hari atau tanggal selesai, estimasi biaya, unggah bukti. Isian dicek saat diketik, tombol simpan aktif setelah semua benar |
| Admin | `/admin/perpanjangan` (halaman awal) | Sewa aktif hari ini dan perpanjangannya |
| Admin | `/admin/garasi`, `/admin/verifikasi` | Kelola garasi; verifikasi pendaftar. Menu Verifikasi menampilkan angka jumlah akun yang menunggu |
| Admin | `/admin/laporan/...` | Empat laporan dengan rincian transaksi dan bukti bayar |

Nomor HP penyewa disimpan seragam sebagai `+62...` dan ditampilkan
`+62 812-3456-7890`. Nomor plat wajib mengikuti struktur Indonesia dan
disimpan seperti `DD 1234 AB`.

Akun demo (khusus lokal, ganti sebelum dipakai di tempat lain):

| Peran | Email | Kata sandi |
|---|---|---|
| Admin | `admin@garasi.test` | `password` |
| Penyewa (APPROVED) | `budi@garasi.test`, `sari@garasi.test`, `andi@garasi.test` | `password` |
| Penyewa (PENDING) | `dewi@garasi.test` | `password` |

Mengulang data dari awal: `php artisan migrate:fresh --seed`.

Menyelesaikan sewa yang sudah lewat secara manual: `php artisan garasi:sinkron-status`.

## Test dan kualitas kode

```bash
php artisan test                      # unit + feature test (MySQL sewa_garasi_test)
vendor/bin/pint --test                # pemeriksaan PSR-12
XDEBUG_MODE=coverage vendor/bin/phpunit --path-coverage --coverage-text
```
