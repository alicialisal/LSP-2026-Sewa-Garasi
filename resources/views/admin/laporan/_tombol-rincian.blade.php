{{-- Tombol buka/tutup rincian satu baris laporan. Variabel: $id, $rincian. --}}
@if (count($rincian) > 0)
    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="collapse"
        data-bs-target="#{{ $id }}" aria-expanded="false" aria-controls="{{ $id }}">
        {{ count($rincian) }} transaksi &#9662;
    </button>
@else
    <span class="text-secondary">-</span>
@endif
