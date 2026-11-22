<?php

namespace App\Providers;

use App\Services\RegistrationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Repository dan service adalah kelas biasa yang dirakit otomatis oleh
 * container lewat type hint di konstruktor, jadi tidak ada pengikatan khusus.
 * Provider ini memakai RegistrationService untuk angka notifikasi menu admin.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Angka notifikasi di menu Verifikasi: jumlah pendaftar PENDING, hanya
        // dihitung (satu query COUNT) bila yang membuka halaman adalah admin.
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            if ($user?->isAdmin()) {
                $view->with('jumlahMenungguVerifikasi', $this->app->make(RegistrationService::class)->jumlahMenungguVerifikasi());
            }
        });
    }
}
