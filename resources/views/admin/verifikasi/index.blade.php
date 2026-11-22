@extends('layouts.app')

@section('judul', 'Verifikasi Pendaftar')

@inject('hp', 'App\Support\NomorTeleponFormatter')

@section('isi')
    <h1 class="judul-halaman h3 mb-4">Verifikasi Pendaftar Baru</h1>

    <div class="card shadow-sm">
        <div class="card-header">Menunggu verifikasi ({{ $pendaftar->count() }})</div>

        @if ($pendaftar->isEmpty())
            <div class="card-body text-secondary">Tidak ada pendaftar yang menunggu verifikasi.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama lengkap</th>
                            <th>NIK</th>
                            <th>Telepon</th>
                            <th>Email</th>
                            <th>Mendaftar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendaftar as $penyewa)
                            <tr>
                                <td>{{ $penyewa->nama_lengkap }}</td>
                                <td class="font-monospace">{{ $penyewa->nik }}</td>
                                <td class="text-nowrap">{{ $hp->tampilan($penyewa->nomor_telepon) }}</td>
                                <td>{{ $penyewa->user->email }}</td>
                                <td>{{ $penyewa->created_at?->locale('id')->translatedFormat('j M Y H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('admin.verifikasi.terima', $penyewa->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Terima</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse"
                                        data-bs-target="#tolak-{{ $penyewa->id }}" aria-expanded="false">Tolak</button>
                                </td>
                            </tr>
                            <tr class="collapse" id="tolak-{{ $penyewa->id }}">
                                <td colspan="6" class="bg-light">
                                    <form method="POST" action="{{ route('admin.verifikasi.tolak', $penyewa->id) }}" class="row g-2 align-items-start">
                                        @csrf
                                        <div class="col-md-9">
                                            <label for="alasan-{{ $penyewa->id }}" class="visually-hidden">Alasan penolakan</label>
                                            <input type="text" id="alasan-{{ $penyewa->id }}" name="alasan" maxlength="1000"
                                                class="form-control form-control-sm" placeholder="Alasan penolakan, misalnya NIK tidak sesuai KTP" required>
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <button type="submit" class="btn btn-danger btn-sm">Kirim penolakan</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @error('alasan')
        <div class="alert alert-danger mt-3">{{ $message }}</div>
    @enderror
@endsection
