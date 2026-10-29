<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useOfflineStore } from '@/stores/offline';
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue';

const auth = useAuthStore();
const ui = useUiStore();
const offline = useOfflineStore();
const router = useRouter();
const { t, locale } = useI18n();

// Manual "sync now": push queued sales, then refresh the offline catalog.
async function syncNow() {
    if (!ui.online) {
        ui.error(t('offline.offline_notice'));
        return;
    }
    try {
        const { synced } = await offline.flush();
        await offline.syncCatalog();
        ui.success(synced > 0 ? t('offline.synced', { n: synced }) : t('offline.up_to_date'));
    } catch (e) {
        ui.error(t('offline.sync_failed'));
    }
}

const nav = computed(() => [
    { name: 'pos', to: { name: 'pos' }, label: t('nav.pos'), icon: '🛒', cap: 'pos' },
    { name: 'dashboard', to: { name: 'dashboard' }, label: t('nav.dashboard'), icon: '📊' },
    { name: 'products', to: { name: 'products' }, label: t('nav.products'), icon: '📦', cap: 'products' },
    { name: 'customers', to: { name: 'customers' }, label: t('nav.customers'), icon: '👥', cap: 'customers' },
    { name: 'sales', to: { name: 'sales' }, label: t('nav.sales'), icon: '🧾' },
    { name: 'suppliers', to: { name: 'suppliers' }, label: t('nav.suppliers'), icon: '🚚', cap: 'purchases' },
    { name: 'purchases', to: { name: 'purchases' }, label: t('nav.purchases'), icon: '📥', cap: 'purchases' },
    { name: 'returns', to: { name: 'returns' }, label: t('nav.returns'), icon: '↩️', cap: 'returns' },
    { name: 'reports', to: { name: 'reports' }, label: t('nav.reports'), icon: '📈', cap: 'reports.basic' },
    { name: 'barcode', to: { name: 'barcode' }, label: t('nav.barcode'), icon: '🏷️', cap: 'barcode_print' },
    { name: 'branches', to: { name: 'branches' }, label: t('nav.branches'), icon: '🏪', cap: 'multi_branch' },
    { name: 'staff', to: { name: 'staff' }, label: t('nav.users'), icon: '👔', perm: 'users.manage', cap: 'staff' },
    { name: 'settings', to: { name: 'settings' }, label: t('nav.settings'), icon: '⚙️', perm: 'settings.manage' },
]);

const visibleNav = computed(() =>
    nav.value.filter((n) => (!n.perm || auth.can(n.perm)) && (!n.cap || auth.hasCapability(n.cap)))
);

function switchLocale() {
    const next = ui.locale === 'bn' ? 'en' : 'bn';
    ui.setLocale(next);
    locale.value = next;
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}

async function exitImpersonation() {
    await auth.stopImpersonation();
    router.push({ name: 'admin.dashboard' });
}
</script>

<template>
    <div class="flex h-full min-h-screen">
        <!-- Sidebar (desktop) -->
        <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 lg:flex">
            <div class="mb-6 flex items-center gap-2 px-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white font-bold">D</div>
                <span class="text-lg font-bold">{{ auth.store?.name || 'digipro POS' }}</span>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto">
                <RouterLink
                    v-for="item in visibleNav"
                    :key="item.name"
                    :to="item.to"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    active-class="!bg-brand-600 !text-white"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    {{ item.label }}
                </RouterLink>
            </nav>
            <button class="btn-ghost justify-start" @click="logout">🚪 {{ t('nav.logout') }}</button>
        </aside>

        <!-- Main -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Impersonation banner -->
            <EmailVerificationBanner />
            <div
                v-if="auth.isStoreExpired"
                class="flex flex-wrap items-center justify-between gap-2 bg-red-600 px-4 py-2 text-sm font-medium text-white"
            >
                <span>{{ t('admin.trial_expired_banner') }}</span>
                <RouterLink :to="{ name: 'settings' }" class="rounded-lg bg-white/20 px-3 py-1 hover:bg-white/30">
                    {{ t('settings.billing') }}
                </RouterLink>
            </div>
            <div v-if="auth.isImpersonating" class="flex items-center justify-between gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-white">
                <span>👁 {{ t('admin.impersonating') }} <strong>{{ auth.store?.name }}</strong></span>
                <button class="rounded-lg bg-white/20 px-3 py-1 hover:bg-white/30" @click="exitImpersonation">↩ {{ t('admin.back_to_admin') }}</button>
            </div>
            <!-- Topbar -->
            <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 items-center gap-1.5 rounded-full px-2 text-xs font-medium" :class="ui.online ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700'">
                        <span class="h-2 w-2 rounded-full" :class="[ui.online ? 'bg-emerald-500' : 'bg-amber-500', !ui.online && 'animate-pulse']"></span>
                        {{ ui.online ? t('offline.online') : t('offline.offline') }}
                    </div>
                    <!-- Pending offline sales -->
                    <div
                        v-if="offline.hasPending"
                        class="flex h-7 items-center gap-1 rounded-full bg-amber-100 px-2 text-xs font-semibold text-amber-700"
                        :title="t('offline.pending', { n: offline.pending })"
                    >
                        ⏳ {{ offline.pending }}
                    </div>
                    <!-- Sync now -->
                    <button
                        class="btn-ghost flex h-7 items-center gap-1 px-2 text-xs"
                        :disabled="offline.syncing || offline.flushing"
                        :title="t('offline.sync_now')"
                        @click="syncNow"
                    >
                        <span :class="(offline.syncing || offline.flushing) && 'animate-spin'">🔄</span>
                        <span class="hidden sm:inline">{{ t('offline.sync_now') }}</span>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn-ghost h-9 px-3 text-sm" @click="switchLocale">{{ ui.locale === 'bn' ? 'EN' : 'বাং' }}</button>
                    <button class="btn-ghost h-9 w-9 p-0 text-lg" @click="ui.toggleTheme">{{ ui.theme === 'dark' ? '☀️' : '🌙' }}</button>
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700 dark:bg-brand-900 dark:text-brand-200">
                        {{ (auth.user?.name || '?').charAt(0).toUpperCase() }}
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 pb-24 lg:pb-4">
                <RouterView />
            </main>
        </div>

        <!-- Bottom nav (mobile) -->
        <nav class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-slate-200 bg-white px-2 py-1.5 dark:border-slate-800 dark:bg-slate-900 lg:hidden">
            <RouterLink
                v-for="item in visibleNav.slice(0, 5)"
                :key="item.name"
                :to="item.to"
                class="flex flex-1 flex-col items-center gap-0.5 rounded-lg py-1.5 text-[11px] font-medium text-slate-500"
                active-class="!text-brand-600"
            >
                <span class="text-xl">{{ item.icon }}</span>
                {{ item.label }}
            </RouterLink>
        </nav>
    </div>
</template>
