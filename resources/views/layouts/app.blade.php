<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Beranda') - Sewa Garasi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-app sticky-top shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('beranda') }}">Sewa Garasi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama"
                aria-controls="menuUtama" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuUtama">
                <ul class="navbar-nav me-auto">
                    @auth
                        @if (auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('admin.perpanjangan.*')) active @endif" href="{{ route('admin.perpanjangan.index') }}">Perpanjangan</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('admin.garasi.*')) active @endif" href="{{ route('admin.garasi.index') }}">Garasi</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('admin.verifikasi.*')) active @endif" href="{{ route('admin.verifikasi.index') }}">
                                    Verifikasi
                                    @if (($jumlahMenungguVerifikasi ?? 0) > 0)
                                        <span class="badge rounded-pill text-bg-danger ms-1" data-test="jumlah-verifikasi">{{ $jumlahMenungguVerifikasi }}</span>
                                        <span class="visually-hidden">{{ $jumlahMenungguVerifikasi }} akun menunggu verifikasi</span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('admin.laporan.*')) active @endif" href="{{ route('admin.laporan.bulanan') }}">Laporan</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link @if (request()->routeIs('sewa.*')) active @endif" href="{{ route('sewa.index') }}">Sewa Saya</a>
                            </li>
                        @endif
                    @endauth
                </ul>

                <ul class="navbar-nav">
                    @auth
                        <li class="nav-item">
                            <span class="navbar-text me-3">{{ auth()->user()->name }}</span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Masuk</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('daftar') }}">Daftar</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container pb-5">
        @include('partials.flash')
        @yield('isi')
    </main>
</body>
</html>
