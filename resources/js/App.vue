<script setup>
import { onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { useOfflineStore } from '@/stores/offline';
import ToastHost from '@/components/ui/ToastHost.vue';

const ui = useUiStore();
const auth = useAuthStore();
const offline = useOfflineStore();
const { t, locale } = useI18n();

// Bring up local offline storage + background catalog sync for a store user.
async function bootOffline() {
    const storeId = auth.store?.id;
    if (!storeId || auth.isSuperAdmin) return;
    await offline.init(storeId);
    if (ui.online) {
        // First run: pull the catalog down so POS works offline later.
        if (!offline.hasCatalog) offline.syncCatalog().catch(() => {});
        if (offline.hasPending) flushPending();
    }
}

async function flushPending() {
    const { synced } = await offline.flush();
    if (synced > 0) ui.success(t('offline.synced', { n: synced }));
}

onMounted(async () => {
    ui.initTheme();
    locale.value = ui.locale;

    window.addEventListener('online', async () => {
        ui.setOnline(true);
        if (offline.ready) await flushPending();
    });
    window.addEventListener('offline', () => ui.setOnline(false));

    // Refresh the current user if we have a token but no loaded user.
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchUser();
        } catch (e) {
            // interceptor handles 401
        }
    }

    if (auth.user) await bootOffline();
});

// React to login/logout happening after initial mount.
watch(
    () => auth.user?.store?.id,
    (id, prev) => {
        if (id && id !== prev) bootOffline();
    }
);
</script>

<template>
    <RouterView />
    <ToastHost />
</template>
