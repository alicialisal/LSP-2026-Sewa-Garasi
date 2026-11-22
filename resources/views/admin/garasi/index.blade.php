@extends('layouts.app')

@section('judul', 'Daftar Garasi')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@section('isi')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="judul-halaman h3 mb-0">Daftar Garasi</h1>
        <a href="{{ route('admin.garasi.create') }}" class="btn btn-primary">+ Tambah Garasi</a>
    </div>

    {{-- Ringkasan per tipe: kartu dapat diklik untuk memfilter daftar. --}}
    <div class="row g-3 mb-4">
        @foreach ($ringkasan as $r)
            <div class="col-md-4">
                <a href="{{ route('admin.garasi.index', ['tipe' => $r['tipe']->value]) }}" class="text-decoration-none">
                    <div class="card shadow-sm h-100 @if ($tipeAktif === $r['tipe']) border-primary border-2 @endif">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <h2 class="h6 judul-halaman mb-0">{{ $r['tipe']->label() }}</h2>
                                <span class="text-secondary small">{{ $r['total'] }} garasi</span>
                            </div>
                            <div class="d-flex gap-3 mt-3">
                                <div>
                                    <div class="fs-4 fw-semibold text-primary">{{ $r['terpakai'] }}</div>
                                    <div class="small text-secondary">Terpakai</div>
                                </div>
                                <div>
                                    <div class="fs-4 fw-semibold text-body">{{ $r['kosong'] }}</div>
                                    <div class="small text-secondary">Kosong</div>
                                </div>
                                <div class="ms-auto text-end">
                                    <div class="fs-6 fw-semibold text-body">{{ $r['sisa_kuota'] }}</div>
                                    <div class="small text-secondary">Sisa kuota</div>
                                </div>
                            </div>
                            @if ($r['total'] > 0)
                                <div class="progress mt-3" style="height: 6px;" role="progressbar"
                                    aria-label="Persentase terpakai {{ $r['tipe']->label() }}"
                                    aria-valuenow="{{ $r['terpakai'] }}" aria-valuemin="0" aria-valuemax="{{ $r['total'] }}">
                                    <div class="progress-bar" style="width: {{ round($r['terpakai'] / $r['total'] * 100) }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link @if ($tipeAktif === null) active @endif" href="{{ route('admin.garasi.index') }}">Semua</a>
        </li>
        @foreach ($tipeGarasi as $tipe)
            <li class="nav-item">
                <a class="nav-link @if ($tipeAktif === $tipe) active @endif" href="{{ route('admin.garasi.index', ['tipe' => $tipe->value]) }}">{{ $tipe->label() }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm">
        <div class="card-header">
            {{ $tipeAktif?->label() ?? 'Semua garasi' }} ({{ $garasi->count() }})
        </div>

        @if ($garasi->isEmpty())
            <div class="card-body text-secondary">
                Belum ada garasi untuk tipe ini. <a href="{{ route('admin.garasi.create') }}">Tambah garasi</a>.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tipe</th>
                            <th class="text-end">Harga harian</th>
                            <th class="text-end">Harga bulanan</th>
                            <th class="text-end">Listrik / kWh</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($garasi as $g)
                            @php($sewaAktif = $g->rentals->first())
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $g->id }}</td>
                                <td>{{ $g->type->label() }}</td>
                                <td class="angka">{{ $rupiah->keRupiah($g->harga_sewa_harian) }}</td>
                                <td class="angka">{{ $rupiah->keRupiah($g->hargaSewaBulanan()) }}</td>
                                <td class="angka">{{ $g->harga_listrik_per_kwh !== null ? $rupiah->keRupiah($g->harga_listrik_per_kwh) : '-' }}</td>
                                <td>
                                    @if ($g->status === \App\Domain\Enums\GarageStatus::USED)
                                        <span class="badge text-bg-primary">Terpakai</span>
                                        @if ($sewaAktif)
                                            <span class="small text-secondary ms-1">sampai {{ $sewaAktif->tanggal_selesai->toDateString() }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">Kosong</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
