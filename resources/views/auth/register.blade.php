@extends('layouts.app')

@section('judul', 'Daftar')

@inject('hp', 'App\Support\NomorTeleponFormatter')

@section('isi')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">Daftar sebagai Penyewa</div>
                <div class="card-body p-4">
                    <p class="text-secondary small">
                        Akun baru perlu diverifikasi admin sebelum dapat mengajukan sewa.
                    </p>

                    {{-- Isian dicek saat diketik (resources/js/form-daftar.js); server tetap memvalidasi ulang. --}}
                    <form method="POST" action="{{ route('daftar.simpan') }}" novalidate
                        x-data="formDaftar(@js([
                            'lama' => old(),
                            'hp' => $hp->tanpaAwalan((string) old('nomor_telepon', '')),
                            'galat' => $errors->toArray(),
                            'urlCek' => route('daftar.cek'),
                        ]))"
                        @input="sentuh($event.target.name)" @change="sentuh($event.target.name)" @focusout="sentuh($event.target.name)">
                        @csrf

                        <div class="mb-3">
                            <label for="nik" class="form-label">NIK</label>
                            <input type="text" id="nik" name="nik" value="{{ old('nik') }}" x-model="nik" inputmode="numeric" maxlength="16"
                                class="form-control @error('nik') is-invalid @enderror" :class="{ 'is-invalid': salah('nik') }" required>
                            <div class="form-text">16 digit angka sesuai KTP.</div>
                            <x-pesan-galat isian="nik" />
                        </div>

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" x-model="nama"
                                class="form-control @error('nama_lengkap') is-invalid @enderror" :class="{ 'is-invalid': salah('nama_lengkap') }" required>
                            <x-pesan-galat isian="nama_lengkap" />
                        </div>

                        <div class="mb-3">
                            <label for="nomor_telepon" class="form-label">Nomor HP</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">+62</span>
                                <input type="tel" id="nomor_telepon" name="nomor_telepon" inputmode="numeric"
                                    value="{{ $hp->tanpaAwalan((string) old('nomor_telepon', '')) }}" placeholder="812-3456-7890"
                                    x-model="hp" @input="rapikanHp()" aria-describedby="bantuan-hp"
                                    class="form-control @error('nomor_telepon') is-invalid @enderror" :class="{ 'is-invalid': salah('nomor_telepon') }" required>
                                <x-pesan-galat isian="nomor_telepon" />
                            </div>
                            <div id="bantuan-hp" class="form-text">Isi angka sesudah +62. Boleh juga diawali 0, nanti dirapikan otomatis.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" x-model="email"
                                class="form-control @error('email') is-invalid @enderror" :class="{ 'is-invalid': salah('email') }" required autocomplete="username">
                            <div class="form-text">Email dipakai untuk masuk.</div>
                            <x-pesan-galat isian="email" />
                        </div>

                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label for="password" class="form-label">Kata sandi</label>
                                <input type="password" id="password" name="password" x-model="sandi"
                                    class="form-control @error('password') is-invalid @enderror" :class="{ 'is-invalid': salah('password') }" required autocomplete="new-password">
                                <x-pesan-galat isian="password" />
                            </div>
                            <div class="col-sm-6 mb-4">
                                <label for="password_confirmation" class="form-label">Ulangi kata sandi</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" x-model="ulangSandi"
                                    class="form-control" :class="{ 'is-invalid': salah('password_confirmation') }" required autocomplete="new-password">
                                <x-pesan-galat isian="password_confirmation" />
                            </div>
                        </div>

                        <x-tombol-simpan class="btn btn-primary w-100">Daftar</x-tombol-simpan>
                        <div class="form-text text-center" x-show="!valid" x-cloak>Tombol aktif setelah semua isian terisi dengan benar.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
