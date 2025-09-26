// @ts-expect-error - IDE module resolution
import tailwindcss from '@tailwindcss/vite';
// @ts-expect-error - IDE module resolution
import vue from '@vitejs/plugin-vue';

import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/js/moonshine-echo.js',
                'resources/js/admin-echo-listener.js',
                'resources/js/webpush.js',
                'resources/css/support-chat.css',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/index.js'),
        },
    },
});
