@extends('layouts.app')

@section('judul', 'Buat Garasi')

@section('isi')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="judul-halaman h3 mb-0">Tambah Garasi Berkelompok</h1>
        <a href="{{ route('admin.garasi.index') }}" class="btn btn-outline-primary btn-sm">Kembali ke daftar</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header">Data garasi</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.garasi.store') }}" novalidate
                        x-data="formGarasi(@js(['sisaKuota' => $sisaKuota, 'lama' => old(), 'galat' => $errors->toArray()]))"
                        @input="sentuh($event.target.name)" @change="sentuh($event.target.name)" @focusout="sentuh($event.target.name)">
                        @csrf

                        <div class="mb-3">
                            <label for="tipe_garasi" class="form-label">Tipe garasi</label>
                            <select id="tipe_garasi" name="tipe_garasi" x-model="tipe"
                                class="form-select @error('tipe_garasi') is-invalid @enderror" :class="{ 'is-invalid': salah('tipe_garasi') }" required>
                                <option value="">Pilih tipe garasi</option>
                                @foreach ($tipeGarasi as $tipe)
                                    <option value="{{ $tipe->value }}" @selected(old('tipe_garasi') === $tipe->value)>
                                        {{ $tipe->label() }} ({{ $tipe->prefixKode() }}001-{{ $tipe->prefixKode() }}999)
                                    </option>
                                @endforeach
                            </select>
                            <x-pesan-galat isian="tipe_garasi" />
                        </div>

                        <div class="mb-3">
                            <label for="jumlah" class="form-label">Jumlah garasi</label>
                            <input type="number" min="1" :max="sisa" id="jumlah" name="jumlah" value="{{ old('jumlah') }}" x-model="jumlah"
                                class="form-control @error('jumlah') is-invalid @enderror" :class="{ 'is-invalid': salah('jumlah') }" required>
                            <div class="form-text" x-show="sisa !== null" x-cloak x-text="`Sisa kuota tipe ini: ${sisa}`"></div>
                            <x-pesan-galat isian="jumlah" />
                        </div>

                        <div class="mb-3">
                            <label for="harga_sewa_harian" class="form-label">Harga sewa harian (Rp)</label>
                            <input type="number" min="1" step="1" id="harga_sewa_harian" name="harga_sewa_harian" value="{{ old('harga_sewa_harian') }}" x-model="harga"
                                class="form-control @error('harga_sewa_harian') is-invalid @enderror" :class="{ 'is-invalid': salah('harga_sewa_harian') }" required>
                            <div class="form-text">Harga sewa bulanan otomatis 25 x harga harian.</div>
                            <x-pesan-galat isian="harga_sewa_harian" />
                        </div>

                        <div class="mb-4" x-show="listrik" x-cloak>
                            <label for="harga_listrik_per_kwh" class="form-label">Harga listrik per kWh (Rp)</label>
                            <input type="number" min="1" step="0.01" id="harga_listrik_per_kwh" name="harga_listrik_per_kwh"
                                value="{{ old('harga_listrik_per_kwh') }}" x-model="hargaListrik" :disabled="!listrik"
                                class="form-control @error('harga_listrik_per_kwh') is-invalid @enderror" :class="{ 'is-invalid': salah('harga_listrik_per_kwh') }">
                            <x-pesan-galat isian="harga_listrik_per_kwh" />
                        </div>

                        <x-tombol-simpan>Buat Garasi</x-tombol-simpan>
                        <div class="form-text" x-show="!valid" x-cloak>Tombol aktif setelah semua isian terisi dengan benar.</div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header">Sisa kuota per tipe</div>
                <ul class="list-group list-group-flush">
                    @foreach ($tipeGarasi as $tipe)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $tipe->label() }}</span>
                            <span class="angka fw-semibold">{{ $sisaKuota[$tipe->value] }} dari 999</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
