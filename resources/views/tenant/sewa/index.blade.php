@extends('layouts.app')

@section('judul', 'Sewa Saya')

@inject('rupiah', 'App\Support\CurrencyFormatter')
@inject('penyimpananBukti', 'App\Support\PaymentProofStorage')

@section('isi')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="judul-halaman h3 mb-0">Sewa Saya</h1>
        <a href="{{ route('sewa.create') }}" class="btn btn-primary">+ Sewa baru</a>
    </div>

    @if (session('nomor_nota'))
        <div class="card ringkasan-biaya mb-4" data-test="ringkasan-sewa">
            <div class="card-body">
                <h2 class="h5 judul-halaman">Sewa tersimpan</h2>
                @if (session('notifikasi'))
                    <div class="alert alert-warning py-2 mb-2">{{ session('notifikasi') }}</div>
                @endif
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nomor nota</dt><dd class="col-sm-8">{{ session('nomor_nota') }}</dd>
                    <dt class="col-sm-4">Tipe sewa</dt><dd class="col-sm-8">{{ session('tipe_sewa') }}</dd>
                    <dt class="col-sm-4">Tanggal selesai</dt><dd class="col-sm-8">{{ session('tanggal_selesai') }}</dd>
                    <dt class="col-sm-4">Total biaya</dt><dd class="col-sm-8 fw-semibold">{{ $rupiah->keRupiah(session('total_biaya')) }}</dd>
                </dl>
            </div>
        </div>
    @endif

    <div class="card shadow-sm" x-data="{ buktiUrl: '', buktiNomor: '' }">
        <div class="card-header">Riwayat sewa ({{ $riwayat->count() }})</div>

        @if ($riwayat->isEmpty())
            <div class="card-body text-secondary">
                Belum ada sewa. Klik <strong>Sewa baru</strong> untuk menyewa garasi.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nomor nota</th>
                            <th>Garasi</th>
                            <th>Kendaraan</th>
                            <th>Tipe sewa</th>
                            <th>Periode</th>
                            <th class="text-end">Total dibayar</th>
                            <th>Status</th>
                            <th class="text-end">Bukti bayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as $sewa)
                            @php($baru = session('nomor_nota') === $sewa->nomor_nota)
                            <tr @class(['table-primary' => $baru])>
                                <td class="font-monospace">{{ $sewa->nomor_nota }}</td>
                                <td>
                                    <span class="font-monospace">{{ $sewa->garage_id }}</span>
                                    <div class="small text-secondary">{{ $sewa->garage?->type->label() }}</div>
                                </td>
                                <td>
                                    {{ $sewa->vehicle?->merek }} {{ $sewa->vehicle?->tipe }}
                                    <div class="small text-secondary">{{ $sewa->vehicle?->nomor_plat ?? 'Tanpa plat' }}</div>
                                </td>
                                <td>{{ ucfirst(strtolower($sewa->tipe_sewa->value)) }}</td>
                                <td class="text-nowrap">
                                    {{ $sewa->tanggal_mulai->locale('id')->translatedFormat('j M Y') }}
                                    s.d. {{ $sewa->tanggal_selesai->locale('id')->translatedFormat('j M Y') }}
                                    @if ($sewa->extensions->isNotEmpty())
                                        <div class="small text-secondary">Diperpanjang {{ $sewa->extensions->count() }} kali</div>
                                    @endif
                                </td>
                                <td class="angka">{{ $rupiah->keRupiah($sewa->totalDibayar()) }}</td>
                                <td>
                                    @if ($sewa->status === \App\Domain\Enums\RentalStatus::AKTIF)
                                        <span class="badge text-bg-success">Aktif</span>
                                    @else
                                        <span class="badge text-bg-secondary">Selesai</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @php($awal = $sewa->pembayaranAwal())
                                    @if ($awal)
                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#modal-bukti"
                                            data-bukti="{{ $penyimpananBukti->url($awal->path_bukti_bayar) }}"
                                            @click="buktiUrl = $el.dataset.bukti; buktiNomor = @js($sewa->nomor_nota)">Lihat bukti</button>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                    @foreach ($sewa->extensions as $ke => $perpanjangan)
                                        @php($bayar = $sewa->pembayaranPerpanjangan($perpanjangan))
                                        @if ($bayar)
                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1"
                                                data-bs-toggle="modal" data-bs-target="#modal-bukti"
                                                data-bukti="{{ $penyimpananBukti->url($bayar->path_bukti_bayar) }}"
                                                @click="buktiUrl = $el.dataset.bukti; buktiNomor = @js($perpanjangan->nomor_perpanjang)">Perpanjangan {{ $ke + 1 }}</button>
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @include('partials.modal-bukti')
    </div>
@endsection
