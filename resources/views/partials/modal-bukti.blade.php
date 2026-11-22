{{-- Satu modal per halaman laporan. Diisi tombol "Lihat bukti" lewat Alpine (buktiUrl, buktiNomor). --}}
<div class="modal fade" id="modal-bukti" tabindex="-1" aria-labelledby="judul-modal-bukti" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="judul-modal-bukti">Bukti bayar <span class="font-monospace" x-text="buktiNomor"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <img :src="buktiUrl" :alt="'Bukti bayar ' + buktiNomor" class="img-fluid rounded" x-show="buktiUrl">
            </div>
            <div class="modal-footer">
                <a :href="buktiUrl" target="_blank" rel="noopener" class="btn btn-outline-primary">Buka di tab baru</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
