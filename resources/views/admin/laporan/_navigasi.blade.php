<h1 class="judul-halaman h3 mb-3">Laporan Sewa</h1>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link @if (request()->routeIs('admin.laporan.bulanan')) active @endif" href="{{ route('admin.laporan.bulanan') }}">Per bulan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if (request()->routeIs('admin.laporan.harian')) active @endif" href="{{ route('admin.laporan.harian') }}">Per hari</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if (request()->routeIs('admin.laporan.tipe-garasi')) active @endif" href="{{ route('admin.laporan.tipe-garasi') }}">Per tipe garasi</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if (request()->routeIs('admin.laporan.penyewa')) active @endif" href="{{ route('admin.laporan.penyewa') }}">Per penyewa</a>
    </li>
</ul>

<p class="text-secondary small">
    Sewa dihitung pada tanggal mulainya; biaya perpanjangan dihitung pada tanggal perpanjangan diproses. Pendapatan = total biaya sewa + biaya perpanjangan.
</p>
