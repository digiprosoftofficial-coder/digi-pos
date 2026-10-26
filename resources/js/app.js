import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { i18n } from './i18n';
import { isNative } from '@/lib/platform';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(i18n);

app.mount('#app');

// Register the PWA service worker (offline app shell + asset caching). Skip in Capacitor — Dexie handles offline sync.
if (!isNative && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Service worker is a progressive enhancement; ignore failures.
        });
    });
}
