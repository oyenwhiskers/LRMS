import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'prompt',
            manifest: {
                name: 'Legal Records Management System',
                short_name: 'LRMS',
                description: 'Physical legal file identity and movement tracking.',
                theme_color: '#111111',
                background_color: '#F8F7F3',
                display: 'standalone',
                start_url: '/',
                icons: [{ src: '/lrms-icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' }],
            },
            workbox: {
                navigateFallback: null,
                runtimeCaching: [],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
