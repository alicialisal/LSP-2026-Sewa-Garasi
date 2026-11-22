import { angkaPositif, dasarValidasi, saring } from './validasi';

/**
 * Form tambah garasi berkelompok dengan pengecekan saat diketik. Aturan dan
 * pesan sama dengan StoreGarageBatchRequest, termasuk batas sisa kuota per tipe.
 *
 * @param {object} awal  sisa kuota per tipe, isian lama, dan galat dari server
 */
export default function formGarasi(awal) {
    return {
        ...dasarValidasi(awal.galat),
        tipe: awal.lama.tipe_garasi ?? '',
        jumlah: awal.lama.jumlah ?? '',
        harga: awal.lama.harga_sewa_harian ?? '',
        hargaListrik: awal.lama.harga_listrik_per_kwh ?? '',
        sisaKuota: awal.sisaKuota,

        get listrik() {
            return this.tipe === 'ELECTRIC';
        },

        get sisa() {
            return this.tipe ? this.sisaKuota[this.tipe] : null;
        },

        get galat() {
            const jumlah = String(this.jumlah).trim();

            return saring({
                tipe_garasi: this.tipe === '' ? 'Tipe garasi wajib dipilih.' : '',
                jumlah: jumlah === '' ? 'Jumlah garasi wajib diisi.'
                    : !/^\d+$/.test(jumlah) ? 'Jumlah garasi harus bilangan bulat.'
                        : Number(jumlah) < 1 ? 'Jumlah garasi minimal 1.'
                            : this.sisa !== null && Number(jumlah) > this.sisa ? `Jumlah melebihi sisa kuota tipe ini (sisa kuota ${this.sisa}).` : '',
                harga_sewa_harian: String(this.harga).trim() === '' ? 'Harga sewa harian wajib diisi.'
                    : !angkaPositif(this.harga) ? 'Harga sewa harian harus lebih dari 0.' : '',
                harga_listrik_per_kwh: !this.listrik ? ''
                    : String(this.hargaListrik).trim() === '' ? 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik.'
                        : !angkaPositif(this.hargaListrik) ? 'Harga listrik per kWh harus lebih dari 0.' : '',
            });
        },

        get valid() {
            return Object.keys(this.galat).length === 0;
        },
    };
}
