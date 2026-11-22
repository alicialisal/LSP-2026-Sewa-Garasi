{{--
    Baris yang dapat dibuka ke bawah: daftar sewa baru dan perpanjangan pada
    satu baris laporan. Variabel: $id (id unik baris), $rincian, $kolom (colspan).
    Tombol "Lihat bukti" membuka modal di partials/modal-bukti.blade.php.
--}}
@inject('rupiah', 'App\Support\CurrencyFormatter')
@inject('penyimpananBukti', 'App\Support\PaymentProofStorage')

<tr class="collapse" id="{{ $id }}">
    <td colspan="{{ $kolom }}" class="bg-light p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead>
                    <tr>
                        <th class="ps-4">Jenis</th>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Penyewa</th>
                        <th>Garasi</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-end pe-3">Bukti bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rincian as $item)
                        <tr>
                            <td class="ps-4">
                                <span class="badge {{ $item['jenis'] === 'Perpanjangan' ? 'text-bg-warning' : 'text-bg-primary' }}">{{ $item['jenis'] }}</span>
                            </td>
                            <td class="font-monospace">{{ $item['nomor'] }}</td>
                            <td class="text-nowrap">{{ $item['tanggal']->locale('id')->translatedFormat('j M Y') }}</td>
                            <td>{{ $item['penyewa'] }}</td>
                            <td class="font-monospace">{{ $item['garasi'] }}</td>
                            <td class="angka">{{ $rupiah->keRupiah($item['jumlah']) }}</td>
                            <td class="text-end pe-3">
                                @if ($item['bukti'])
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#modal-bukti"
                                        data-bukti="{{ $penyimpananBukti->url($item['bukti']) }}"
                                        @click="buktiUrl = $el.dataset.bukti; buktiNomor = @js($item['nomor'])">Lihat bukti</button>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </td>
</tr>
