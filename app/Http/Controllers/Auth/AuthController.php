<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Login dan logout dengan email sebagai username, tanpa starter kit.
 * Penyewa PENDING tetap dapat login (asumsi nomor 16). Melayani route
 * /login dan /logout memakai fasad Auth bawaan Laravel.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class AuthController extends Controller
{
    /**
     * Menampilkan form login.
     *
     * @return View halaman auth.login
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Memproses login. Jika gagal, kembali ke form dengan galat pada email;
     * jika berhasil, sesi dibuat ulang dan pengguna diarahkan ke halaman
     * yang dituju atau halaman awal sesuai role.
     *
     * @param  LoginRequest  $request  isian email dan kata sandi
     * @return RedirectResponse ke halaman tujuan, atau kembali ke form login dengan galat
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return back()
                ->withErrors(['email' => 'Email atau kata sandi salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(self::halamanAwal($request->user()));
    }

    /**
     * Mengeluarkan pengguna, menghapus sesi, dan membuat ulang token CSRF.
     *
     * @param  Request  $request  permintaan logout yang membawa sesi aktif
     * @return RedirectResponse ke halaman login
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Halaman pertama setelah login, menurut role.
     *
     * @param  User  $user  pengguna yang baru login
     * @return string URL daftar perpanjangan untuk admin, atau riwayat sewa untuk penyewa
     */
    public static function halamanAwal(User $user): string
    {
        return $user->isAdmin()
            ? route('admin.perpanjangan.index')
            : route('sewa.index');
    }
}
