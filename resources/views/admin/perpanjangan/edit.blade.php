@extends('layouts.app')

@section('judul', 'Perpanjang Sewa')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@section('isi')
    <h1 class="judul-halaman h3 mb-4">Perpanjang Sewa {{ $sewa->nomor_nota }}</h1>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header">Data sewa</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 fw-normal">Penyewa</dt><dd class="col-7">{{ $sewa->penyewa->nama_lengkap }}</dd>
                        <dt class="col-5 fw-normal">Garasi</dt><dd class="col-7">{{ $sewa->garage_id }} ({{ $sewa->garage->type->label() }})</dd>
                        <dt class="col-5 fw-normal">Kendaraan</dt><dd class="col-7">{{ $sewa->vehicle->nomor_plat ?? $sewa->vehicle->merek }}</dd>
                        <dt class="col-5 fw-normal">Tipe sewa</dt><dd class="col-7">{{ $sewa->tipe_sewa->value }}</dd>
                        <dt class="col-5 fw-normal">Mulai</dt><dd class="col-7">{{ $sewa->tanggal_mulai->toDateString() }}</dd>
                        <dt class="col-5 fw-normal">Selesai</dt><dd class="col-7">{{ $sewa->tanggal_selesai->toDateString() }}</dd>
                        <dt class="col-5 fw-normal">Harga harian</dt><dd class="col-7">{{ $rupiah->keRupiah($sewa->garage->harga_sewa_harian) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header">Perpanjangan</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.perpanjangan.update', $sewa->id) }}" enctype="multipart/form-data" novalidate
                        x-data="formPerpanjangan(@js([
                            'tanggalSelesaiLama' => $sewa->tanggal_selesai->toDateString(),
                            'hargaHarian' => $sewa->garage->harga_sewa_harian,
                            'lama' => old('tanggal_selesai_baru'),
                            'galat' => $errors->toArray(),
                            'maksBuktiKb' => \App\Support\PaymentProofStorage::MAKS_KB,
                        ]))"
                        @input="sentuh($event.target.name)" @change="sentuh($event.target.name)" @focusout="sentuh($event.target.name)">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="tanggal_selesai_baru" class="form-label">Tanggal selesai baru</label>
                            <input type="date" id="tanggal_selesai_baru" name="tanggal_selesai_baru" x-model="tanggalBaru"
                                min="{{ $sewa->tanggal_selesai->copy()->addDay()->toDateString() }}"
                                class="form-control @error('tanggal_selesai_baru') is-invalid @enderror" :class="{ 'is-invalid': salah('tanggal_selesai_baru') }" required>
                            <div class="form-text">Harus setelah {{ $sewa->tanggal_selesai->toDateString() }}. Perpanjangan dihitung harian.</div>
                            <x-pesan-galat isian="tanggal_selesai_baru" />
                        </div>

                        <div class="ringkasan-biaya p-3 mb-3" x-show="tambahanHari > 0" x-cloak>
                            <span x-text="`Tambahan ${tambahanHari} hari`"></span>:
                            <strong x-text="rupiah(biaya)"></strong>
                        </div>

                        <div class="mb-4">
                            <label for="bukti_bayar" class="form-label">Bukti bayar</label>
                            <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png" @change="pilihBukti($event)"
                                class="form-control @error('bukti_bayar') is-invalid @enderror" :class="{ 'is-invalid': salah('bukti_bayar') }" required>
                            <div class="form-text">Gambar JPG, JPEG, atau PNG, maksimal 5 MB.</div>
                            <x-pesan-galat isian="bukti_bayar" />
                        </div>

                        <div class="d-flex gap-2">
                            <x-tombol-simpan>Simpan Perpanjangan</x-tombol-simpan>
                            <a href="{{ route('admin.perpanjangan.index') }}" class="btn btn-outline-primary">Batal</a>
                        </div>
                        <div class="form-text" x-show="!valid" x-cloak>Tombol aktif setelah semua isian terisi dengan benar.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
