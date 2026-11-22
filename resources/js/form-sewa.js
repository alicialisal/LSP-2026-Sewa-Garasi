import rupiah from './rupiah';
import { angkaPositif, dasarValidasi, galatBukti, saring } from './validasi';

const SEHARI_MS = 86400000;

/**
 * Sama dengan NomorPlatFormatter::normalisasi(): "dd1234ab" menjadi
 * "DD 1234 AB"; null bila struktur bukan plat Indonesia.
 */
function normalPlat(nilai) {
    const rapat = String(nilai).replace(/[\s-]+/g, '').toUpperCase();
    const bagian = rapat.match(/^([A-Z]{1,2})([1-9][0-9]{0,3})([A-Z]{0,3})$/);

    return bagian ? [bagian[1], bagian[2], bagian[3]].filter(Boolean).join(' ') : null;
}

/** 'YYYY-MM-DD' ke milidetik UTC, agar selisih hari tidak terganggu zona waktu. */
function keMs(iso) {
    const [tahun, bulan, hari] = iso.split('-').map(Number);

    return Date.UTC(tahun, bulan - 1, hari);
}

function keIso(ms) {
    return new Date(ms).toISOString().slice(0, 10);
}

/**
 * Form sewa baru (asumsi nomor 19). Alpine memindahkan tipe sewa dan
 * menampilkan notifikasi seketika saat jumlah hari diketik; server tetap
 * menjalankan aturan yang sama di RentalTypeResolver. Semua isian dicek saat
 * diketik dengan aturan StoreRentalRequest; nomor plat juga dicek ke server
 * (GET /sewa/cek-plat) untuk plat milik penyewa lain atau kendaraan yang
 * masih disewa.
 *
 * @param {object} awal  data dari server: hariSebulan, url, isian lama, dan galat
 */
export default function formSewa(awal) {
    return {
        ...dasarValidasi(awal.galat),
        bukti: null,
        hariSebulan: awal.hariSebulan,
        tanggalMulai: awal.tanggalMulai,
        tipeKendaraan: awal.lama.tipe_kendaraan ?? '',
        tipeSewa: awal.lama.tipe_sewa ?? 'HARIAN',
        jumlahHari: awal.lama.jumlah_hari ?? '',
        garageId: awal.lama.garage_id ?? '',
        kapasitas: awal.lama.kapasitas_baterai_kwh ?? '',
        sisa: awal.lama.sisa_baterai_kwh ?? '',
        nomorPlat: awal.lama.nomor_plat ?? '',
        merek: awal.lama.merek ?? '',
        tipe: awal.lama.tipe ?? '',
        kendaraanTersimpan: awal.kendaraanTersimpan ?? [],
        kendaraanDipilih: '',
        garasi: [],
        memuatGarasi: false,
        notifikasi: '',
        estimasi: null,
        galatEstimasi: '',
        nomorPermintaan: 0,
        penundaEstimasi: null,

        init() {
            if (this.tipeKendaraan) {
                this.muatGarasi();
            }

            this.$watch('jumlahHari', () => this.terapkanAturanHari());
            this.$watch('tipeSewa', () => this.hitungEstimasi());
            this.$watch('garageId', () => this.hitungEstimasi());
            this.$watch('kapasitas', () => this.hitungEstimasi());
            this.$watch('sisa', () => this.hitungEstimasi());
            this.$watch('nomorPlat', () => this.cekPlat());
            this.$watch('tipeKendaraan', () => this.cekPlat());
        },

        /** Plat yang strukturnya benar ditanyakan ke server. */
        cekPlat() {
            if (!this.wajibPlat || normalPlat(this.nomorPlat) === null) {
                this.batalCek('nomor_plat');
                return;
            }

            const plat = this.nomorPlat;
            this.cekKeServer('nomor_plat', async () => {
                const respons = await window.axios.get(awal.urlCekPlat, { params: { nomor_plat: plat } });

                return respons.data.pesan;
            });
        },

        pilihBukti(event) {
            const berkas = event.target.files[0];
            this.bukti = berkas ? { name: berkas.name, size: berkas.size, type: berkas.type } : null;
        },

        get galat() {
            const plat = this.nomorPlat.trim();
            const kapasitasBenar = angkaPositif(this.kapasitas);
            const hari = String(this.jumlahHari).trim();

            return saring({
                tipe_kendaraan: this.tipeKendaraan === '' ? 'Tipe kendaraan wajib dipilih.' : '',
                nomor_plat: !this.wajibPlat ? ''
                    : plat === '' ? 'Nomor plat wajib diisi untuk tipe kendaraan ini.'
                        : normalPlat(plat) === null ? awal.pesanPlat
                            : this.cekServer.nomor_plat,
                merek: this.merek.trim() === '' ? 'Merek kendaraan wajib diisi.'
                    : this.merek.length > 100 ? 'Merek maksimal 100 karakter.' : '',
                tipe: this.tipe.trim() === '' ? 'Tipe kendaraan wajib diisi.'
                    : this.tipe.length > 100 ? 'Tipe maksimal 100 karakter.' : '',
                kapasitas_baterai_kwh: !this.listrik ? ''
                    : String(this.kapasitas).trim() === '' ? 'Kapasitas baterai wajib diisi untuk mobil listrik.'
                        : !kapasitasBenar ? 'Kapasitas baterai harus lebih dari 0.' : '',
                sisa_baterai_kwh: !this.listrik ? ''
                    : String(this.sisa).trim() === '' ? 'Sisa baterai wajib diisi untuk mobil listrik.'
                        : !(Number(this.sisa) >= 0) ? 'Sisa baterai tidak boleh negatif.'
                            : kapasitasBenar && Number(this.sisa) > Number(this.kapasitas) ? 'Sisa baterai harus antara 0 dan kapasitas baterai.' : '',
                garage_id: this.garageId === '' ? 'Garasi wajib dipilih.' : '',
                jumlah_hari: !this.harian ? ''
                    : hari === '' ? 'Jumlah hari wajib diisi untuk sewa harian.'
                        : !/^\d+$/.test(hari) || Number(hari) < 1 ? 'Jumlah hari minimal 1.' : '',
                bukti_bayar: galatBukti(this.bukti, awal.maksBuktiKb),
            });
        },

        /** Tombol simpan aktif bila semua isian benar dan estimasi berhasil dihitung. */
        get valid() {
            return Object.keys(this.galat).length === 0 && !this.masihCek()
                && !this.memuatGarasi && !this.galatEstimasi;
        },

        get wajibPlat() {
            return this.tipeKendaraan !== '' && this.tipeKendaraan !== 'SEPEDA';
        },

        get listrik() {
            return this.tipeKendaraan === 'MOBIL_LISTRIK';
        },

        get harian() {
            return this.tipeSewa === 'HARIAN';
        },

        /**
         * Memilih kendaraan yang pernah diparkir mengisi data kendaraan.
         * Server tetap mencocokkan lewat nomor plat (RentalService), jadi
         * record kendaraan lama dipakai ulang. Memilih "Kendaraan baru"
         * mengosongkan isian agar diisi manual. Sisa baterai selalu diisi ulang
         * karena berubah setiap kali.
         */
        pilihKendaraan() {
            const k = this.kendaraanTersimpan.find((item) => String(item.id) === String(this.kendaraanDipilih));

            this.tipeKendaraan = k ? k.tipe_kendaraan : '';
            this.nomorPlat = k ? k.nomor_plat : '';
            this.merek = k ? k.merek : '';
            this.tipe = k ? k.tipe : '';
            this.kapasitas = k ? String(k.kapasitas_baterai_kwh) : '';
            this.sisa = '';

            // Isian terisi otomatis dianggap sudah disentuh agar pesan lama dari server hilang.
            ['tipe_kendaraan', 'nomor_plat', 'merek', 'tipe', 'kapasitas_baterai_kwh'].forEach((isian) => this.sentuh(isian));

            this.muatGarasi();
        },

        /**
         * Tanggal selesai (inklusif) = tanggal mulai + lama sewa - 1, sama
         * dengan perhitungan server. Sewa bulanan memakai hari sebulan.
         */
        get tanggalSelesai() {
            const hari = this.harian ? parseInt(this.jumlahHari, 10) : this.hariSebulan;

            if (!(hari >= 1)) {
                return '';
            }

            return keIso(keMs(this.tanggalMulai) + (hari - 1) * SEHARI_MS);
        },

        /**
         * Memilih tanggal selesai mengisi jumlah hari. Aturan hari sebulan
         * lalu berjalan seperti saat jumlah hari diketik (watch jumlahHari),
         * termasuk pindah ke bulanan beserta notifikasinya.
         */
        pilihTanggalSelesai(iso) {
            if (!iso || !this.harian) {
                return;
            }

            const hari = Math.round((keMs(iso) - keMs(this.tanggalMulai)) / SEHARI_MS) + 1;
            this.jumlahHari = hari >= 1 ? String(hari) : '';
        },

        /**
         * Aturan yang sama dengan RentalTypeResolver: harian sebanyak atau
         * lebih dari hari sebulan berpindah ke bulanan; lebih dari hari
         * sebulan disertai notifikasi.
         */
        terapkanAturanHari() {
            const hari = parseInt(this.jumlahHari, 10);
            this.notifikasi = '';

            if (this.harian && hari >= this.hariSebulan) {
                if (hari > this.hariSebulan) {
                    this.notifikasi = `Sewa tidak dapat melebihi 1 bulan. Tipe sewa diubah menjadi Bulanan (${this.hariSebulan} hari).`;
                }
                this.tipeSewa = 'BULANAN';
            }

            this.hitungEstimasi();
        },

        async muatGarasi() {
            this.garasi = [];
            this.estimasi = null;

            if (!this.tipeKendaraan) {
                return;
            }

            this.memuatGarasi = true;
            try {
                const respons = await window.axios.get(awal.urlGarasi, { params: { tipe_kendaraan: this.tipeKendaraan } });
                this.garasi = respons.data.data;

                if (!this.garasi.some((g) => g.id === this.garageId)) {
                    this.garageId = '';
                }
            } finally {
                this.memuatGarasi = false;
            }

            this.hitungEstimasi();
        },

        /**
         * Estimasi dihitung 300 ms setelah isian terakhir berubah, agar tidak
         * mengirim satu permintaan per ketikan.
         */
        hitungEstimasi() {
            clearTimeout(this.penundaEstimasi);
            this.penundaEstimasi = setTimeout(() => this.mintaEstimasi(), 300);
        },

        /**
         * Setiap permintaan diberi nomor urut. Jawaban yang datang dari
         * permintaan lama diabaikan, sehingga jawaban gagal dari isian
         * setengah jadi (misalnya kapasitas "6" sebelum "60") tidak menimpa
         * estimasi dari isian terbaru.
         */
        async mintaEstimasi() {
            const nomor = ++this.nomorPermintaan;
            this.galatEstimasi = '';

            // Isian lama sewa atau baterai yang masih salah sudah diberi pesan
            // di bawah isiannya; estimasi tidak diminta agar pesan tidak ganda.
            const galat = this.galat;
            const belumLengkap = galat.jumlah_hari || galat.kapasitas_baterai_kwh || galat.sisa_baterai_kwh;

            if (!this.tipeKendaraan || !this.garageId || belumLengkap) {
                this.estimasi = null;
                return;
            }

            const params = {
                tipe_kendaraan: this.tipeKendaraan,
                tipe_sewa: this.tipeSewa,
                garage_id: this.garageId,
            };
            if (this.harian) {
                params.jumlah_hari = this.jumlahHari;
            }
            if (this.listrik && this.kapasitas !== '' && this.sisa !== '') {
                params.kapasitas_baterai_kwh = this.kapasitas;
                params.sisa_baterai_kwh = this.sisa;
            }

            try {
                const respons = await window.axios.get(awal.urlEstimasi, { params });
                if (nomor !== this.nomorPermintaan) {
                    return;
                }
                this.estimasi = respons.data;
            } catch (galat) {
                if (nomor !== this.nomorPermintaan) {
                    return;
                }
                this.estimasi = null;
                this.galatEstimasi = galat.response?.data?.message ?? 'Estimasi biaya belum dapat dihitung.';
            }
        },

        rupiah,
    };
}
