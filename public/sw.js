/**
 * Service worker Sewa Garasi, sengaja minimal.
 *
 * Fungsinya hanya dua: memenuhi syarat agar browser menawarkan "Instal
 * aplikasi", dan menampilkan halaman pemberitahuan bila HP tidak punya
 * koneksi. Tidak ada halaman, data, atau bukti bayar yang disimpan di cache:
 * semua halaman berisi data pribadi penyewa dan token CSRF, sehingga selalu
 * diminta ke server.
 */
const HALAMAN_OFFLINE = `<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tidak ada koneksi - Sewa Garasi</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, sans-serif; background: #f4f8ff; color: #0a3d91; text-align: center; padding: 24px; }
        main { max-width: 360px; }
        h1 { font-size: 1.4rem; margin: 0 0 8px; }
        p { color: #333; margin: 0 0 20px; }
        button { background: #0a3d91; color: #fff; border: 0; border-radius: 6px; padding: 10px 20px; font-size: 1rem; }
    </style>
</head>
<body>
    <main>
        <h1>Tidak ada koneksi</h1>
        <p>Sewa Garasi membutuhkan koneksi internet. Periksa sambunganmu, lalu coba lagi.</p>
        <button type="button" onclick="location.reload()">Coba lagi</button>
    </main>
</body>
</html>`;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;

    // Hanya perpindahan halaman yang ditangani; gambar, CSS, JS, dan JSON langsung ke jaringan.
    if (permintaan.method !== 'GET' || permintaan.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(permintaan).catch(() => new Response(HALAMAN_OFFLINE, {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        })),
    );
});
