import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import path from 'path';
export default defineConfig({
    build: {
        // Очищать папку dist перед сборкой
        minify: 'terser',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                // 1. Главные файлы JS и чанки Inertia-страниц попадают в js/
                chunkFileNames: 'js/[name]-[hash].js',
                entryFileNames: 'js/[name]-[hash].js',

                // 2. Стили, шрифты и картинки распределяются по папкам
                assetFileNames: (assetInfo) => {
                    // Стили (Tailwind, PrimeVue, ваши стили)
                    if (/\.(css)$/.test(assetInfo.name)) {
                        return 'css/[name]-[hash][extname]';
                    }

                    // Статические медиа-файлы (картинки, видео)
                    if (/\.(png|jpe?g|gif|svg|webp|avif|mp4|webm)$/.test(assetInfo.name)) {
                        return 'static/images/[name]-[hash][extname]';
                    }

                    // Шрифты
                    if (/\.(woff2?|eot|ttf|otf)$/.test(assetInfo.name)) {
                        return 'static/fonts/[name]-[hash][extname]';
                    }

                    // Остальные файлы (ico, json и т.д.)
                    return 'static/[name]-[hash][extname]';
                },

                // 3. Выделение PrimeVue, Inertia и Vue в отдельный vendor-чанк
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        // Группируем ключевые зависимости в один vendor файл
                        if (id.includes('vue') || id.includes('primevue') || id.includes('@inertiajs')) {
                            return 'vendor';
                        }
                        // Другие мелкие библиотеки из node_modules пойдут в свои чанки внутри js/
                    }
                },
            },
        },
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
