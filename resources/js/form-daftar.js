import rapikanHp from './input-hp';
import { dasarValidasi, saring } from './validasi';

const POLA_NIK = /^\d{16}$/;
const POLA_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Form pendaftaran penyewa dengan pengecekan saat diketik. Aturan dan pesan
 * sama dengan RegisterPenyewaRequest. NIK dan email yang formatnya sudah
 * benar dicek ke server (GET /daftar/cek) untuk tahu apakah sudah terdaftar.
 *
 * @param {object} awal  isian lama, galat dari server, dan URL cek
 */
export default function formDaftar(awal) {
    return {
        ...dasarValidasi(awal.galat),
        nik: awal.lama.nik ?? '',
        nama: awal.lama.nama_lengkap ?? '',
        hp: awal.hp ?? '',
        email: awal.lama.email ?? '',
        sandi: '',
        ulangSandi: '',

        init() {
            this.$watch('nik', () => this.cekTerdaftar('nik', this.nik, POLA_NIK));
            this.$watch('email', () => this.cekTerdaftar('email', this.email.trim(), POLA_EMAIL));
        },

        /** NIK atau email yang formatnya benar ditanyakan ke server. */
        cekTerdaftar(isian, nilai, pola) {
            if (!pola.test(nilai)) {
                this.batalCek(isian);
                return;
            }

            this.cekKeServer(isian, async () => {
                const respons = await window.axios.get(awal.urlCek, { params: { [isian]: nilai } });

                return respons.data[isian];
            });
        },

        rapikanHp() {
            this.hp = rapikanHp(this.hp);
        },

        get galat() {
            const angkaHp = this.hp.replace(/\D/g, '');
            const email = this.email.trim();

            return saring({
                nik: this.nik === '' ? 'NIK wajib diisi.'
                    : !POLA_NIK.test(this.nik) ? 'NIK harus 16 digit angka sesuai KTP.'
                        : this.cekServer.nik,
                nama_lengkap: this.nama.trim() === '' ? 'Nama lengkap wajib diisi.'
                    : this.nama.length > 255 ? 'Nama lengkap maksimal 255 karakter.' : '',
                nomor_telepon: angkaHp === '' ? 'Nomor telepon wajib diisi.'
                    : !/^8\d{8,11}$/.test(angkaHp) ? 'Nomor HP tidak valid. Isi angka sesudah +62, contoh 812-3456-7890.' : '',
                email: email === '' ? 'Email wajib diisi.'
                    : !POLA_EMAIL.test(email) || email.length > 255 ? 'Format email tidak valid.'
                        : this.cekServer.email,
                password: this.sandi === '' ? 'Kata sandi wajib diisi.'
                    : this.sandi.length < 8 ? 'Kata sandi minimal 8 karakter.' : '',
                password_confirmation: this.ulangSandi !== this.sandi ? 'Konfirmasi kata sandi tidak sama.' : '',
            });
        },

        get valid() {
            return Object.keys(this.galat).length === 0 && !this.masihCek();
        },
    };
}
