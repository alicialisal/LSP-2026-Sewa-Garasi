@extends('layouts.app')

@section('judul', 'Masuk')

@section('isi')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header">Masuk</div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('login.proses') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Kata sandi</label>
                            <input type="password" id="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Masuk</button>
                    </form>

                    <p class="text-center text-secondary mt-3 mb-0">
                        Belum punya akun? <a href="{{ route('daftar') }}">Daftar sebagai penyewa</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
