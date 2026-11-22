@extends('layouts.app')

@section('judul', 'Perpanjangan Sewa')

@section('isi')
    <h1 class="judul-halaman h3 mb-1">Perpanjangan Sewa</h1>
    <p class="text-secondary mb-4">Hanya sewa yang aktif hari ini yang dapat diperpanjang.</p>

    <div class="card shadow-sm">
        <div class="card-header">Sewa aktif hari ini ({{ $sewa->count() }})</div>

        @if ($sewa->isEmpty())
            <div class="card-body text-secondary">Tidak ada sewa yang aktif hari ini.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nomor nota</th>
                            <th>Penyewa</th>
                            <th>Garasi</th>
                            <th>Kendaraan</th>
                            <th>Tipe</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sewa as $s)
                            <tr>
                                <td class="font-monospace">{{ $s->nomor_nota }}</td>
                                <td>{{ $s->penyewa->nama_lengkap }}</td>
                                <td>{{ $s->garage_id }}</td>
                                <td>{{ $s->vehicle->nomor_plat ?? $s->vehicle->merek.' '.$s->vehicle->tipe }}</td>
                                <td>{{ $s->tipe_sewa->value }}</td>
                                <td>{{ $s->tanggal_mulai->toDateString() }}</td>
                                <td>{{ $s->tanggal_selesai->toDateString() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.perpanjangan.edit', $s->id) }}" class="btn btn-primary btn-sm">Perpanjang</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
