@extends('layouts.app')

@section('judul', 'Laporan per Bulan')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@section('isi')
    @include('admin.laporan._navigasi')

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-sm-4 col-md-2">
            <label for="tahun" class="form-label">Tahun</label>
            <input type="number" id="tahun" name="tahun" value="{{ $tahun }}" min="2000" max="2100"
                class="form-control @error('tahun') is-invalid @enderror">
            @error('tahun')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
    </form>

    <div class="card shadow-sm" x-data="{ buktiUrl: '', buktiNomor: '' }">
        <div class="card-header">Sewa per bulan tahun {{ $tahun }}</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="text-end">Jumlah sewa</th>
                        <th class="text-end">Pendapatan</th>
                        <th class="text-end">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $b)
                        <tr>
                            <td>{{ \Carbon\CarbonImmutable::create($tahun, $b['bulan'], 1)->locale('id')->translatedFormat('F') }}</td>
                            <td class="angka">{{ $b['jumlah_sewa'] }}</td>
                            <td class="angka">{{ $rupiah->keRupiah($b['pendapatan']) }}</td>
                            <td class="text-end">@include('admin.laporan._tombol-rincian', ['id' => 'bulan-'.$b['bulan'], 'rincian' => $b['rincian']])</td>
                        </tr>
                        @if (count($b['rincian']) > 0)
                            @include('admin.laporan._rincian', ['id' => 'bulan-'.$b['bulan'], 'rincian' => $b['rincian'], 'kolom' => 4])
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td class="angka">{{ $baris->sum('jumlah_sewa') }}</td>
                        <td class="angka">{{ $rupiah->keRupiah($total) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @include('partials.modal-bukti')
    </div>
@endsection
