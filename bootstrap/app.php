<?php

use App\Exceptions\Handler;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EnsurePenyewaVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'penyewa.verified' => EnsurePenyewaVerified::class,
        ]);

        // Pengguna yang sudah login dan membuka halaman tamu diarahkan ke
        // halaman awal sesuai role.
        $middleware->redirectUsersTo(fn (Request $request) => AuthController::halamanAwal($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aturan pelaporan dan tampilan galat ada di App\Exceptions\Handler.
    })
    // Handler aplikasi (diagram B.6) menggantikan handler bawaan Laravel.
    ->withSingletons([ExceptionHandler::class => Handler::class])
    ->create();
