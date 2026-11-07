import './bootstrap';
import '@fontsource-variable/plus-jakarta-sans'; // font di-host sendiri (lihat app.blade.php)
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { http } from '@inertiajs/core';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { BASE } from './utils/basePath';

// Aplikasi di subfolder: semua permintaan Inertia ke path internal diberi awalan subfolder (URL sudah absolut di tahap ini).
if (BASE) {
    http.onRequest((config) => {
        try {
            const u = new URL(config.url, window.location.href);
            if (u.origin === window.location.origin && !(u.pathname === BASE || u.pathname.startsWith(BASE + '/'))) {
                u.pathname = BASE + u.pathname;
                config.url = u.href;
            }
        } catch (e) { /* biarkan apa adanya */ }

        return config;
    });
}

// Keep CSRF token and Axios headers continuously in sync on every Inertia navigation
router.on('navigate', (event) => {
    const token = event.detail?.page?.props?.csrf_token;
    if (token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }
});

// Modul halaman gagal dimuat (berkas lama setelah deploy, atau server membalas 429 saat sibuk):
// muat ulang halaman sekali secara otomatis, alih-alih menampilkan halaman kosong/konsol merah.
const RELOAD_FLAG = 'simod_chunk_reload_at';
const reloadOnceForStaleAssets = () => {
    try {
        const last = Number(sessionStorage.getItem(RELOAD_FLAG) || 0);
        if (Date.now() - last < 30000) return false; // sudah dicoba barusan: jangan berulang tanpa henti
        sessionStorage.setItem(RELOAD_FLAG, String(Date.now()));
    } catch (e) { /* sessionStorage tidak tersedia: tetap coba sekali */ }
    window.location.reload();
    return true;
};
window.addEventListener('vite:preloadError', (event) => {
    if (reloadOnceForStaleAssets()) event.preventDefault();
});

const toast = (message, type = 'error') => window.dispatchEvent(new CustomEvent('simod-toast', { detail: { message, type } }));

// Respons non-Inertia (mis. 429/502/503 dari server hosting) jangan ditampilkan sebagai modal putih kosong.
router.on('httpException', (event) => {
    const response = event.detail?.response;
    const headers = response?.headers || {};
    const isInertia = headers['x-inertia'] ?? headers['X-Inertia'];
    if (isInertia) return; // halaman galat milik aplikasi (403/404/500) dirender normal

    event.preventDefault();
    const status = response?.status;
    if (status === 419) {
        window.location.reload(); // sesi/CSRF kedaluwarsa
        return;
    }
    toast(status === 429
        ? 'Server sedang sibuk menerima terlalu banyak permintaan. Tunggu beberapa detik lalu coba lagi.'
        : `Server tidak merespons dengan benar (kode ${status ?? '—'}). Coba lagi sebentar lagi.`);
});

router.on('networkError', () => {
    toast('Koneksi ke server terputus. Periksa jaringan lalu coba lagi.');
});

const appName = import.meta.env.VITE_APP_NAME || 'SIMOD Enterprise';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    // Komponen halaman gagal dimuat (aset basi setelah deploy / 429): muat ulang sekali, bukan halaman kosong.
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))
        .catch((error) => {
            if (reloadOnceForStaleAssets()) return new Promise(() => {});
            toast('Halaman gagal dimuat. Muat ulang browser (Ctrl+F5) bila masalah berlanjut.');
            throw error;
        }),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
        showSpinner: true,
    },
});
