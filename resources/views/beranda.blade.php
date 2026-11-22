@extends('layouts.app')

@section('judul', 'Beranda')

@section('isi')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="judul-halaman h3 mb-3">Sistem Penyewaan Garasi Kendaraan</h1>
                    <p class="text-secondary">
                        Sewa garasi untuk sepeda, sepeda motor, mobil, dan mobil listrik secara harian atau bulanan.
                        Sewa dimulai hari ini dan biaya dihitung otomatis.
                    </p>

                    @guest
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <a href="{{ route('login') }}" class="btn btn-primary">Masuk</a>
                            <a href="{{ route('daftar') }}" class="btn btn-outline-primary">Daftar sebagai Penyewa</a>
                        </div>
                    @else
                        @php($user = auth()->user())
                        @if ($user->isAdmin())
                            <a href="{{ route('admin.perpanjangan.index') }}" class="btn btn-primary mt-3">Buka halaman admin</a>
                        @elseif ($user->penyewa?->isVerified())
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="{{ route('sewa.index') }}" class="btn btn-primary">Lihat sewa saya</a>
                                <a href="{{ route('sewa.create') }}" class="btn btn-outline-primary">Ajukan sewa baru</a>
                            </div>
                        @else
                            <div class="alert alert-light border mt-3 mb-0">
                                Status akun: <strong>{{ $user->penyewa?->status->value ?? 'tidak diketahui' }}</strong>.
                                @if ($user->penyewa?->alasan_penolakan)
                                    <br>Alasan: {{ $user->penyewa->alasan_penolakan }}
                                @endif
                            </div>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </div>
@endsection
