/**
 * Pengecekan isian saat diketik, dipakai bersama oleh form Alpine.
 *
 * Cara kerja:
 * - Setiap form punya getter `galat` berisi pesan per nama isian, dengan
 *   aturan dan teks pesan yang sama dengan FormRequest di server.
 * - Pesan baru tampil setelah isian disentuh (diketik, diubah, atau
 *   ditinggalkan), agar form kosong tidak langsung penuh merah.
 * - Pesan dari server (setelah submit gagal) tampil sampai isiannya diubah.
 * - Tombol simpan nonaktif selama masih ada galat atau cek server berjalan.
 *
 * Server tetap memvalidasi ulang semua isian; ini hanya membantu pengguna.
 */

/** Pesan pertama per isian dari $errors Laravel ({nik: ['...']}). */
function pesanPertama(galatServer) {
    return Object.fromEntries(
        Object.entries(galatServer ?? {}).map(([isian, pesan]) => [isian, Array.isArray(pesan) ? pesan[0] : pesan]),
    );
}

/**
 * Status dan metode bersama. Disebar ke objek form dengan `...dasarValidasi()`;
 * karena itu tidak berisi getter (getter akan terbaca sekali saat disebar).
 */
export function dasarValidasi(galatServer) {
    return {
        disentuh: {},
        galatServer: pesanPertama(galatServer),
        cekServer: {},
        menungguCek: {},
        penunda: {},
        nomorCek: {},

        /** Dipanggil dari event input/change/focusout pada form. */
        sentuh(isian) {
            if (isian) {
                this.disentuh[isian] = true;
            }
        },

        /** Pesan yang ditampilkan untuk satu isian, kosong bila aman. */
        pesan(isian) {
            if (this.disentuh[isian]) {
                return this.galat[isian] ?? '';
            }

            return this.galatServer[isian] ?? '';
        },

        salah(isian) {
            return this.pesan(isian) !== '';
        },

        /**
         * Cek ke server dengan jeda (debounce) 400 ms setelah ketikan terakhir.
         * Jawaban dari permintaan lama diabaikan, sama seperti estimasi biaya.
         *
         * @param {string} kunci   nama pengecekan, misalnya 'nik'
         * @param {Function} ambil  fungsi async yang mengembalikan pesan galat atau null
         */
        cekKeServer(kunci, ambil) {
            this.batalCek(kunci);
            const nomor = this.nomorCek[kunci];
            this.menungguCek[kunci] = true;

            this.penunda[kunci] = setTimeout(async () => {
                let pesan = null;
                try {
                    pesan = await ambil();
                } catch {
                    // Gagal cek (misalnya koneksi): dibiarkan, server tetap mengecek saat simpan.
                }

                if (this.nomorCek[kunci] !== nomor) {
                    return;
                }
                if (pesan) {
                    this.cekServer[kunci] = pesan;
                }
                this.menungguCek[kunci] = false;
            }, 400);
        },

        /** Membatalkan cek yang tertunda, misalnya karena isian berubah menjadi tidak valid. */
        batalCek(kunci) {
            clearTimeout(this.penunda[kunci]);
            this.nomorCek[kunci] = (this.nomorCek[kunci] ?? 0) + 1;
            this.menungguCek[kunci] = false;
            delete this.cekServer[kunci];
        },

        /** True selama masih ada cek server yang belum dijawab. */
        masihCek() {
            return Object.values(this.menungguCek).some(Boolean);
        },
    };
}

/** Membuang isian tanpa pesan sehingga objek galat hanya berisi yang salah. */
export function saring(galat) {
    return Object.fromEntries(Object.entries(galat).filter(([, pesan]) => pesan));
}

/**
 * Aturan bukti bayar, sama dengan trait ValidasiBuktiBayar.
 *
 * @param {{name: string, size: number, type: string}|null} berkas
 * @param {number} maksKb
 */
export function galatBukti(berkas, maksKb) {
    if (!berkas) {
        return 'Bukti bayar wajib diunggah.';
    }

    const ekstensiBenar = /\.(jpe?g|png)$/i.test(berkas.name);
    const tipeBenar = ['image/jpeg', 'image/png'].includes(berkas.type);
    if (!ekstensiBenar || !tipeBenar) {
        return 'Bukti bayar harus berupa gambar JPG, JPEG, atau PNG.';
    }

    if (berkas.size > maksKb * 1024) {
        return `Ukuran bukti bayar melebihi ${maksKb / 1024} MB.`;
    }

    return '';
}

/** Angka desimal positif seperti aturan numeric|gt:0. */
export function angkaPositif(nilai) {
    return String(nilai).trim() !== '' && Number.isFinite(Number(nilai)) && Number(nilai) > 0;
}
