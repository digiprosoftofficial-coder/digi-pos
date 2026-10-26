import { copyFileSync, existsSync, renameSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const out = resolve(root, 'capacitor-www');

if (!existsSync(out)) {
    console.error('Run vite build for Capacitor first (capacitor-www missing).');
    process.exit(1);
}

const builtIndex = resolve(out, 'index.capacitor.html');
const capIndex = resolve(out, 'index.html');
if (existsSync(builtIndex)) {
    renameSync(builtIndex, capIndex);
} else if (!existsSync(capIndex)) {
    console.error('Expected index.capacitor.html in capacitor-www after Vite build.');
    process.exit(1);
}

const copies = [
    ['public/manifest.webmanifest', 'manifest.webmanifest'],
    ['public/apple-touch-icon.png', 'apple-touch-icon.png'],
    ['public/favicon.ico', 'favicon.ico'],
];

for (const [from, to] of copies) {
    const src = resolve(root, from);
    if (existsSync(src)) {
        copyFileSync(src, resolve(out, to));
    }
}

for (const name of ['icon-192.png', 'icon-512.png', 'icon-maskable-192.png', 'icon-maskable-512.png']) {
    const src = resolve(root, 'public', name);
    if (existsSync(src)) {
        copyFileSync(src, resolve(out, name));
    }
}

console.log('Capacitor web assets prepared in capacitor-www/');
