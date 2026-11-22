/*
 * Pengaturan awal permintaan HTTP dari browser.
 *
 * Kode eksternal yang dipakai di berkas ini:
 * - axios 1.21.1 (lisensi MIT): permintaan JSON ke server (garasi tersedia,
 *   estimasi, cek NIK, email, dan plat). Dokumentasi: https://axios-http.com
 *
 * @author Alicia Lisal
 * @version 1.0
 */
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
