/*
 * Titik masuk JavaScript aplikasi. Mendaftarkan komponen Alpine untuk form.
 *
 * Kode eksternal yang dipakai di berkas ini:
 * - alpinejs 3.17.5 (lisensi MIT): interaksi form tanpa framework berat
 *   (konversi harian ke bulanan, estimasi biaya, cek isian saat diketik).
 *   Dokumentasi: https://alpinejs.dev
 * - bootstrap 5.3.9 (lisensi MIT): komponen tampilan (navbar, modal, collapse).
 *   Dokumentasi: https://getbootstrap.com/docs/5.3
 *
 * @author Alicia Lisal
 * @version 1.0
 */
import './bootstrap';
import 'bootstrap';
import Alpine from 'alpinejs';

import formSewa from './form-sewa';
import formGarasi from './form-garasi';
import formPerpanjangan from './form-perpanjangan';
import formDaftar from './form-daftar';

Alpine.data('formSewa', formSewa);
Alpine.data('formGarasi', formGarasi);
Alpine.data('formPerpanjangan', formPerpanjangan);
Alpine.data('formDaftar', formDaftar);

window.Alpine = Alpine;
Alpine.start();
