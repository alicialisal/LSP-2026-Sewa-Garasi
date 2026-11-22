import rupiah from './rupiah';
import { dasarValidasi, galatBukti, saring } from './validasi';

/**
 * Form perpanjangan dengan pengecekan saat diketik. Pratinjau sisa biaya:
 * harga sewa harian x tambahan hari (asumsi nomor 17). Angka final tetap
 * dihitung ExtensionService, dan server tetap memvalidasi ulang.
 */
export default function formPerpanjangan(awal) {
    return {
        ...dasarValidasi(awal.galat),
        tanggalBaru: awal.lama ?? '',
        bukti: null,

        get tambahanHari() {
            if (!this.tanggalBaru) {
                return 0;
            }

            const lama = new Date(awal.tanggalSelesaiLama + 'T00:00:00');
            const baru = new Date(this.tanggalBaru + 'T00:00:00');

            return Math.round((baru - lama) / 86400000);
        },

        get biaya() {
            return this.tambahanHari > 0 ? this.tambahanHari * awal.hargaHarian : 0;
        },

        pilihBukti(event) {
            const berkas = event.target.files[0];
            this.bukti = berkas ? { name: berkas.name, size: berkas.size, type: berkas.type } : null;
        },

        get galat() {
            return saring({
                tanggal_selesai_baru: !this.tanggalBaru ? 'Tanggal selesai baru wajib diisi.'
                    : !(this.tambahanHari >= 1) ? 'Tanggal selesai baru harus setelah tanggal selesai lama.' : '',
                bukti_bayar: galatBukti(this.bukti, awal.maksBuktiKb),
            });
        },

        get valid() {
            return Object.keys(this.galat).length === 0;
        },

        rupiah,
    };
}
