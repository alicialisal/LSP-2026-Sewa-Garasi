@extends('layouts.app')

@section('judul', 'Laporan per Penyewa')

@inject('rupiah', 'App\Support\CurrencyFormatter')

@section('isi')
    @include('admin.laporan._navigasi')
    @include('admin.laporan._rentang')

    <div class="card shadow-sm" x-data="{ buktiUrl: '', buktiNomor: '' }">
        <div class="card-header">Sewa per penyewa, {{ $dari->toDateString() }} s.d. {{ $sampai->toDateString() }}</div>

        @if ($baris->isEmpty())
            <div class="card-body text-secondary">Tidak ada sewa pada rentang ini.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Penyewa</th>
                            <th class="text-end">Jumlah sewa</th>
                            <th class="text-end">Pendapatan</th>
                            <th class="text-end">Rincian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr>
                                <td>{{ $b['nama_lengkap'] }}</td>
                                <td class="angka">{{ $b['jumlah_sewa'] }}</td>
                                <td class="angka">{{ $rupiah->keRupiah($b['pendapatan']) }}</td>
                                <td class="text-end">@include('admin.laporan._tombol-rincian', ['id' => 'penyewa-'.$b['penyewa_id'], 'rincian' => $b['rincian']])</td>
                            </tr>
                            @if (count($b['rincian']) > 0)
                                @include('admin.laporan._rincian', ['id' => 'penyewa-'.$b['penyewa_id'], 'rincian' => $b['rincian'], 'kolom' => 4])
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
        @endif

        @include('partials.modal-bukti')
    </div>
@endsection
