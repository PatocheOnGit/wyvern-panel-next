import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { globSync } from 'glob';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 1000,
    },
    plugins: [
        laravel({
            input: [
                // The wyvern/ partials are @import-ed by wyvern-theme.css, so they are inlined
                // at build time. Without this they would also each become their own entry.
                ...globSync('resources/css/**/*.css', { ignore: 'resources/css/wyvern/**' }),
                ...globSync('resources/js/**/*.js'),

                ...globSync('plugins/*/resources/css/**/*.css'),
                ...globSync('plugins/*/resources/js/**/*.js'),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
