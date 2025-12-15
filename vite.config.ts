import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command: 'docker compose exec -T laravel.test php artisan wayfinder:generate --with-form',
        })
    ],
    optimizeDeps: {
        include: ['maplibre-gl'],
    },
    esbuild: {
        jsx: 'automatic',
    },
    // server: {
    //     host: 'localhost',
    //     hmr: {
    //         host: 'localhost',
    //     },
    //     cors: true,
    // },
});
