@extends('layouts.app')

@section('judul', 'Sewa Baru')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@php
    $labelKendaraan = [
        'SEPEDA' => 'Sepeda',
        'SEPEDA_MOTOR' => 'Sepeda motor',
        'MOBIL' => 'Mobil bensin',
        'MOBIL_LISTRIK' => 'Mobil listrik',
    ];
@endphp

@section('isi')
    <a href="{{ route('sewa.index') }}" class="small text-decoration-none">&larr; Kembali ke Sewa Saya</a>
    <h1 class="judul-halaman h3 mb-1 mt-2">Sewa Garasi Baru</h1>
    <p class="text-secondary mb-4">
        Sewa dimulai hari ini, {{ $hariIni->locale('id')->translatedFormat('j F Y') }}.
        Bulan ini memiliki {{ $hariSebulan }} hari, sehingga sewa harian paling lama {{ $hariSebulan - 1 }} hari.
    </p>

    <form method="POST" action="{{ route('sewa.store') }}" enctype="multipart/form-data" novalidate
        x-data="formSewa(@js([
            'hariSebulan' => $hariSebulan,
            'tanggalMulai' => $hariIni->toDateString(),
            'urlGarasi' => route('sewa.garasi-tersedia'),
            'urlEstimasi' => route('sewa.estimasi'),
            'kendaraanTersimpan' => $kendaraanTersimpan,
            'lama' => old(),
            'galat' => $errors->toArray(),
            'urlCekPlat' => route('sewa.cek-plat'),
            'pesanPlat' => \App\Support\NomorPlatFormatter::PESAN,
            'maksBuktiKb' => \App\Support\PaymentProofStorage::MAKS_KB,
        ]))"
        @input="sentuh($event.target.name)" @change="sentuh($event.target.name)" @focusout="sentuh($event.target.name)">
        @csrf

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">1. Kendaraan</div>
                    <div class="card-body">
                        @if (count($kendaraanTersimpan) > 0)
                            {{-- Hanya membantu mengisi isian di bawah; tidak dikirim ke server. --}}
                            <div class="mb-3">
                                <label for="kendaraan_tersimpan" class="form-label">Pilih kendaraan</label>
                                <select id="kendaraan_tersimpan" x-model="kendaraanDipilih" @change="pilihKendaraan()" class="form-select">
                                    <option value="">+ Kendaraan baru (isi manual)</option>
                                    @foreach ($kendaraanTersimpan as $k)
                                        <option value="{{ $k['id'] }}" @disabled($k['sedang_disewa'])>{{ $k['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Kendaraan yang pernah kamu parkir. Pilih untuk mengisi data kendaraan otomatis.</div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="tipe_kendaraan" class="form-label">Tipe kendaraan</label>
                            <select id="tipe_kendaraan" name="tipe_kendaraan" x-model="tipeKendaraan" @change="muatGarasi()"
                                class="form-select @error('tipe_kendaraan') is-invalid @enderror" :class="{ 'is-invalid': salah('tipe_kendaraan') }" required>
                                <option value="">Pilih tipe kendaraan</option>
                                @foreach ($tipeKendaraan as $tipe)
                                    <option value="{{ $tipe->value }}" @selected(old('tipe_kendaraan') === $tipe->value)>{{ $labelKendaraan[$tipe->value] }}</option>
                                @endforeach
                            </select>
                            <x-pesan-galat isian="tipe_kendaraan" />
                        </div>

                        <div class="mb-3" x-show="wajibPlat" x-cloak>
                            <label for="nomor_plat" class="form-label">Nomor plat</label>
                            <input type="text" id="nomor_plat" name="nomor_plat" value="{{ old('nomor_plat') }}" x-model="nomorPlat" maxlength="15"
                                placeholder="DD 1234 AB" autocomplete="off"
                                :disabled="!wajibPlat" class="form-control text-uppercase @error('nomor_plat') is-invalid @enderror" :class="{ 'is-invalid': salah('nomor_plat') }">
                            <div class="form-text">Kode wilayah (1-2 huruf), nomor (1-4 angka), dan seri huruf (opsional, maksimal 3). Spasi dan huruf kecil dirapikan otomatis.</div>
                            <x-pesan-galat isian="nomor_plat" />
                        </div>

                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label for="merek" class="form-label">Merek</label>
                                <input type="text" id="merek" name="merek" value="{{ old('merek') }}" x-model="merek"
                                    class="form-control @error('merek') is-invalid @enderror" :class="{ 'is-invalid': salah('merek') }" required>
                                <x-pesan-galat isian="merek" />
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label for="tipe" class="form-label">Tipe</label>
                                <input type="text" id="tipe" name="tipe" value="{{ old('tipe') }}" x-model="tipe"
                                    class="form-control @error('tipe') is-invalid @enderror" :class="{ 'is-invalid': salah('tipe') }" required>
                                <x-pesan-galat isian="tipe" />
                            </div>
                        </div>

                        <div class="row" x-show="listrik" x-cloak>
                            <div class="col-sm-6 mb-3">
                                <label for="kapasitas_baterai_kwh" class="form-label">Kapasitas baterai (kWh)</label>
                                <input type="number" step="0.01" min="0" id="kapasitas_baterai_kwh" name="kapasitas_baterai_kwh"
                                    x-model="kapasitas" :disabled="!listrik"
                                    class="form-control @error('kapasitas_baterai_kwh') is-invalid @enderror" :class="{ 'is-invalid': salah('kapasitas_baterai_kwh') }">
                                <x-pesan-galat isian="kapasitas_baterai_kwh" />
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label for="sisa_baterai_kwh" class="form-label">Sisa baterai (kWh)</label>
                                <input type="number" step="0.01" min="0" id="sisa_baterai_kwh" name="sisa_baterai_kwh"
                                    x-model="sisa" :disabled="!listrik"
                                    class="form-control @error('sisa_baterai_kwh') is-invalid @enderror" :class="{ 'is-invalid': salah('sisa_baterai_kwh') }">
                                <x-pesan-galat isian="sisa_baterai_kwh" />
                            </div>
                            <div class="col-12 form-text mt-n2 mb-2">
                                Di garasi mobil listrik, baterai diisi sampai penuh dan biaya listriknya ditambahkan ke total.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">2. Garasi dan lama sewa</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="garage_id" class="form-label">Garasi tersedia</label>
                            <select id="garage_id" name="garage_id" x-model="garageId"
                                class="form-select @error('garage_id') is-invalid @enderror" :class="{ 'is-invalid': salah('garage_id') }" required>
                                <option value="" x-text="memuatGarasi ? 'Memuat garasi...' : (tipeKendaraan ? 'Pilih garasi' : 'Pilih tipe kendaraan dulu')"></option>
                                <template x-for="g in garasi" :key="g.id">
                                    <option :value="g.id" :selected="g.id === garageId"
                                        x-text="`${g.id} - ${g.label} (${rupiah(g.harga_sewa_harian)}/hari, ${rupiah(g.harga_sewa_bulanan)}/bulan)`"></option>
                                </template>
                            </select>
                            <div class="form-text" x-show="tipeKendaraan && !memuatGarasi && garasi.length === 0" x-cloak>
                                Tidak ada garasi yang tersedia hari ini untuk tipe kendaraan ini.
                            </div>
                            <x-pesan-galat isian="garage_id" />
                        </div>

                        <div class="mb-3">
                            <span class="form-label d-block">Tipe sewa</span>
                            @foreach ($tipeSewa as $tipe)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipe_sewa" id="tipe_sewa_{{ $tipe->value }}"
                                        value="{{ $tipe->value }}" x-model="tipeSewa" @checked(old('tipe_sewa', 'HARIAN') === $tipe->value)>
                                    <label class="form-check-label" for="tipe_sewa_{{ $tipe->value }}">{{ $tipe === \App\Domain\Enums\RentalType::HARIAN ? 'Harian' : 'Bulanan' }}</label>
                                </div>
                            @endforeach
                            @error('tipe_sewa')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-5" x-show="harian">
                                <label for="jumlah_hari" class="form-label">Jumlah hari</label>
                                <input type="number" min="1" id="jumlah_hari" name="jumlah_hari" x-model="jumlahHari" :disabled="!harian"
                                    class="form-control @error('jumlah_hari') is-invalid @enderror" :class="{ 'is-invalid': salah('jumlah_hari') }">
                                <x-pesan-galat isian="jumlah_hari" />
                            </div>
                            <div class="col-sm-7">
                                {{-- Tidak dikirim ke server: hanya mengisi jumlah hari (tanggal selesai inklusif). --}}
                                <label for="tanggal_selesai" class="form-label">Tanggal selesai</label>
                                <input type="date" id="tanggal_selesai" min="{{ $hariIni->toDateString() }}"
                                    :value="tanggalSelesai" @change="pilihTanggalSelesai($event.target.value)" :readonly="!harian"
                                    class="form-control">
                                <div class="form-text" x-show="!harian" x-cloak>Tanggal selesai sewa bulanan ditetapkan otomatis.</div>
                            </div>
                        </div>

                        <div class="alert alert-warning py-2" x-show="notifikasi" x-text="notifikasi" x-cloak></div>
                        <p class="text-secondary small mb-0" x-show="!harian" x-cloak>
                            Sewa bulanan berlaku {{ $hariSebulan }} hari dengan harga 25 x harga sewa harian.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">3. Estimasi biaya</div>
                    <div class="card-body">
                        <p class="text-secondary mb-0" x-show="!estimasi && !galatEstimasi">
                            Lengkapi kendaraan, garasi, dan lama sewa untuk melihat biaya.
                        </p>
                        <div class="alert alert-danger py-2 mb-0" x-show="galatEstimasi" x-text="galatEstimasi" x-cloak></div>

                        <template x-if="estimasi">
                            <dl class="row mb-0">
                                <dt class="col-6 fw-normal">Tipe sewa</dt>
                                <dd class="col-6 text-end" x-text="estimasi.tipe_sewa === 'HARIAN' ? `Harian ${estimasi.jumlah_hari} hari` : `Bulanan ${estimasi.jumlah_hari} hari`"></dd>
                                <dt class="col-6 fw-normal">Periode</dt>
                                <dd class="col-6 text-end" x-text="`${estimasi.tanggal_mulai} s.d. ${estimasi.tanggal_selesai}`"></dd>
                                <dt class="col-6 fw-normal">Biaya sewa</dt>
                                <dd class="col-6 angka" x-text="rupiah(estimasi.biaya_sewa)"></dd>
                                <template x-if="estimasi.jumlah_listrik_kwh > 0">
                                    <dt class="col-6 fw-normal" x-text="`Isi listrik ${estimasi.jumlah_listrik_kwh} kWh`"></dt>
                                </template>
                                <template x-if="estimasi.jumlah_listrik_kwh > 0">
                                    <dd class="col-6 angka" x-text="rupiah(estimasi.biaya_listrik)"></dd>
                                </template>
                                <dt class="col-6 border-top pt-2">Total</dt>
                                <dd class="col-6 angka border-top pt-2 fw-semibold fs-5" x-text="rupiah(estimasi.total)"></dd>
                            </dl>
                        </template>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">4. Bukti pembayaran</div>
                    <div class="card-body">
                        <label for="bukti_bayar" class="form-label">Unggah bukti bayar</label>
                        <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png" @change="pilihBukti($event)"
                            class="form-control @error('bukti_bayar') is-invalid @enderror" :class="{ 'is-invalid': salah('bukti_bayar') }" required>
                        <div class="form-text">Gambar JPG, JPEG, atau PNG, maksimal 5 MB.</div>
                        <x-pesan-galat isian="bukti_bayar" />

                        <x-tombol-simpan class="btn btn-primary w-100 mt-4">Simpan Sewa</x-tombol-simpan>
                        <div class="form-text text-center" x-show="!valid" x-cloak>Tombol aktif setelah semua isian terisi dengan benar dan estimasi biaya tampil.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
