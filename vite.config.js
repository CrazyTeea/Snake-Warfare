import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import path from 'path';
export default defineConfig({
    build: {
        // Очищать папку dist перед сборкой
        //emptyOutDir: true,

    },
    plugins: [
        tailwindcss(),
        laravel({
            input: [ 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        inertia({ssr: false}),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**', '**/aistor-data/**', '**/dbdata/**'],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js/'),
        },
    },
});
