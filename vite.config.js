import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Satukan pustaka & ikon ke beberapa berkas vendor. Tanpa ini Vite memecah setiap ikon
                // menjadi berkas kecil (70+ berkas), dan semburan permintaan per halaman memicu
                // pembatas 429 LiteSpeed di shared hosting sehingga modul gagal dimuat (halaman kosong).
                manualChunks(id) {
                    if (!id.includes('node_modules')) return undefined;
                    if (id.includes('lucide-vue-next')) return 'vendor-icons';
                    // Leaflet hanya dipakai peta Command Center: chunk terpisah agar halaman lain tidak memuatnya.
                    if (id.includes('leaflet')) return 'vendor-map';
                    if (id.includes('marked')) return 'vendor-markdown';
                    return 'vendor';
                },
            },
        },
    },
});
