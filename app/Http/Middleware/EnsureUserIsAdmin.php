<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya role ADMIN yang boleh lewat; selain itu 403 (FT-05).
 * Dipasang sebagai middleware "admin" pada semua route berawalan /admin.
 *
 * @author Alicia Lisal
 * @version 1.0
 */
class EnsureUserIsAdmin
{
    /**
     * Meneruskan permintaan jika pengguna yang login adalah admin; selain itu
     * menghentikannya dengan 403.
     *
     * @param  Request  $request  permintaan HTTP yang masuk
     * @param  Closure  $next  middleware atau controller berikutnya
     * @return Response respons dari tahap berikutnya
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403, 'Halaman ini hanya untuk admin.');

        return $next($request);
    }
}
