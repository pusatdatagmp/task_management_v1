import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    // // TAMBAHKAN BAGIAN INI
    // server: {
    //     host: '0.0.0.0', // Izinkan semua IP akses Vite
    //     cors: true,     // Mengaktifkan Header CORS
    //     hmr: {
    //         host: '192.168.1.24', // Sesuaikan dengan IP laptop kamu
    //     },
    // },
    esbuild: {
        jsx: 'automatic',
    },
});