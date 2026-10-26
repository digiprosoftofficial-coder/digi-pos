<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const { t, locale } = useI18n();

const nav = computed(() => [
    { to: { name: 'admin.dashboard' }, label: t('admin.dashboard'), icon: '📊' },
    { to: { name: 'admin.stores' }, label: t('admin.stores'), icon: '🏪' },
    { to: { name: 'admin.plans' }, label: t('admin.plans'), icon: '💳' },
]);

function switchLocale() {
    const next = ui.locale === 'bn' ? 'en' : 'bn';
    ui.setLocale(next);
    locale.value = next;
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex h-full min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-slate-900 p-4 text-slate-100 lg:flex">
            <div class="mb-6 flex items-center gap-2 px-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 font-bold">D</div>
                <div>
                    <div class="text-sm font-bold leading-tight">digipro POS</div>
                    <div class="text-xs text-slate-400">{{ t('admin.panel') }}</div>
                </div>
            </div>
            <nav class="flex-1 space-y-1">
                <RouterLink
                    v-for="item in nav"
                    :key="item.label"
                    :to="item.to"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                    active-class="!bg-brand-600 !text-white"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    {{ item.label }}
                </RouterLink>
            </nav>
            <button class="btn-ghost justify-start !text-slate-300 hover:!bg-slate-800" @click="logout">🚪 {{ t('nav.logout') }}</button>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
                <span class="font-semibold text-slate-500">👑 {{ t('admin.panel') }}</span>
                <div class="flex items-center gap-2">
                    <button class="btn-ghost h-9 px-3 text-sm" @click="switchLocale">{{ ui.locale === 'bn' ? 'EN' : 'বাং' }}</button>
                    <button class="btn-ghost h-9 w-9 p-0 text-lg" @click="ui.toggleTheme">{{ ui.theme === 'dark' ? '☀️' : '🌙' }}</button>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto p-4 pb-24 lg:pb-4">
                <RouterView />
            </main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-slate-200 bg-white px-2 py-1.5 dark:border-slate-800 dark:bg-slate-900 lg:hidden">
            <RouterLink
                v-for="item in nav"
                :key="item.label"
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
