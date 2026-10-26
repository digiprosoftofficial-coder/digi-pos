import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    base: './',
    plugins: [
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
    build: {
        outDir: 'capacitor-www',
        emptyOutDir: true,
        rollupOptions: {
            input: resolve(rootDir, 'index.capacitor.html'),
        },
    },
    resolve: {
        alias: {
            '@': resolve(rootDir, 'resources/js'),
        },
    },
});
