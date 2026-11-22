/**
 * Merapikan isian nomor HP (awalan +62 tampil tetap di form) menjadi
 * 812-3456-7890: karakter selain angka dibuang, begitu juga awalan 62 atau 0.
 * Server tetap menyeragamkan dan memvalidasi ulang (NomorTeleponFormatter).
 */
export default function rapikanHp(nilai) {
    let angka = String(nilai ?? '').replace(/\D/g, '');

    if (angka.startsWith('62')) {
        angka = angka.slice(2);
    } else if (angka.startsWith('0')) {
        angka = angka.slice(1);
    }

    angka = angka.slice(0, 12);

    return [angka.slice(0, 3), angka.slice(3, 7), angka.slice(7)]
        .filter((bagian) => bagian !== '')
        .join('-');
}
