<?php

use App\Http\Controllers\Admin\AdminExtensionController;
use App\Http\Controllers\Admin\AdminGarageController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminVerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Tenant\RentalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'beranda')->name('beranda');

// Tamu: login dan pendaftaran penyewa.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.proses');
    Route::get('/daftar', [RegistrationController::class, 'create'])->name('daftar');
    Route::post('/daftar', [RegistrationController::class, 'store'])->name('daftar.simpan');
    // Cek NIK/email saat diketik; dibatasi agar tidak dipakai menebak data secara massal.
    Route::get('/daftar/cek', [RegistrationController::class, 'cek'])->middleware('throttle:60,1')->name('daftar.cek');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Penyewa berstatus APPROVED.
Route::middleware(['auth', 'penyewa.verified'])->prefix('sewa')->name('sewa.')->group(function () {
    Route::get('/', [RentalController::class, 'index'])->name('index');
    Route::get('/baru', [RentalController::class, 'create'])->name('create');
    Route::get('/garasi-tersedia', [RentalController::class, 'garasiTersedia'])->name('garasi-tersedia');
    Route::get('/estimasi', [RentalController::class, 'estimasi'])->name('estimasi');
    Route::get('/cek-plat', [RentalController::class, 'cekPlat'])->name('cek-plat');
    Route::post('/', [RentalController::class, 'store'])->name('store');
});

// Admin.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/garasi', [AdminGarageController::class, 'index'])->name('garasi.index');
    Route::get('/garasi/buat', [AdminGarageController::class, 'create'])->name('garasi.create');
    Route::post('/garasi', [AdminGarageController::class, 'store'])->name('garasi.store');

    Route::get('/verifikasi', [AdminVerificationController::class, 'index'])->name('verifikasi.index');
    Route::post('/verifikasi/{penyewa}/terima', [AdminVerificationController::class, 'terima'])->whereNumber('penyewa')->name('verifikasi.terima');
    Route::post('/verifikasi/{penyewa}/tolak', [AdminVerificationController::class, 'tolak'])->whereNumber('penyewa')->name('verifikasi.tolak');

    Route::get('/perpanjangan', [AdminExtensionController::class, 'index'])->name('perpanjangan.index');
    Route::get('/perpanjangan/{rental}/edit', [AdminExtensionController::class, 'edit'])->whereNumber('rental')->name('perpanjangan.edit');
    Route::put('/perpanjangan/{rental}', [AdminExtensionController::class, 'update'])->whereNumber('rental')->name('perpanjangan.update');

    Route::get('/laporan/bulanan', [AdminReportController::class, 'bulanan'])->name('laporan.bulanan');
    Route::get('/laporan/harian', [AdminReportController::class, 'harian'])->name('laporan.harian');
    Route::get('/laporan/tipe-garasi', [AdminReportController::class, 'perTipeGarasi'])->name('laporan.tipe-garasi');
    Route::get('/laporan/penyewa', [AdminReportController::class, 'perPenyewa'])->name('laporan.penyewa');
});
