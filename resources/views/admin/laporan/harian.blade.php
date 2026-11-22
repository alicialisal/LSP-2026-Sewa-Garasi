@extends('layouts.app')

@section('judul', 'Laporan per Hari')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@php($namaBulan = \Carbon\CarbonImmutable::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y'))

@section('isi')
    @include('admin.laporan._navigasi')

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-sm-4 col-md-2">
            <label for="bulan" class="form-label">Bulan</label>
            <select id="bulan" name="bulan" class="form-select @error('bulan') is-invalid @enderror">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($m === $bulan)>{{ \Carbon\CarbonImmutable::create(2000, $m, 1)->locale('id')->translatedFormat('F') }}</option>
                @endfor
            </select>
            @error('bulan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
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
        <div class="card-header">Sewa per hari, {{ $namaBulan }}</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-end">Jumlah sewa</th>
                        <th class="text-end">Pendapatan</th>
                        <th class="text-end">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $b)
                        <tr>
                            <td>{{ $b['hari'] }} {{ $namaBulan }}</td>
                            <td class="angka">{{ $b['jumlah_sewa'] }}</td>
                            <td class="angka">{{ $rupiah->keRupiah($b['pendapatan']) }}</td>
                            <td class="text-end">@include('admin.laporan._tombol-rincian', ['id' => 'hari-'.$b['hari'], 'rincian' => $b['rincian']])</td>
                        </tr>
                        @if (count($b['rincian']) > 0)
                            @include('admin.laporan._rincian', ['id' => 'hari-'.$b['hari'], 'rincian' => $b['rincian'], 'kolom' => 4])
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
