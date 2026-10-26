<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import Spinner from '@/components/ui/Spinner.vue';

const { t } = useI18n();
const data = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await api.get('/admin/dashboard');
        data.value = res.data.data;
    } finally {
        loading.value = false;
    }
});

const cards = computed(() => [
    { label: t('admin.total_stores'), value: data.value?.stores.total || 0, icon: '🏪', tone: 'bg-brand-500' },
    { label: t('admin.active'), value: data.value?.stores.active || 0, icon: '✅', tone: 'bg-emerald-500' },
    { label: t('admin.trial'), value: data.value?.stores.trial || 0, icon: '⏳', tone: 'bg-amber-500' },
    { label: t('admin.suspended'), value: data.value?.stores.suspended || 0, icon: '⛔', tone: 'bg-red-500' },
    { label: t('admin.total_users'), value: data.value?.users || 0, icon: '👥', tone: 'bg-pink-500' },
    { label: t('admin.platform_revenue'), value: '৳' + (data.value?.sales.revenue || 0).toFixed(0), icon: '💰', tone: 'bg-slate-700' },
]);
</script>

<template>
    <div>
        <h1 class="mb-4 text-2xl font-bold">{{ t('admin.dashboard') }}</h1>
        <Spinner v-if="loading" />
        <div v-else class="space-y-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                <div v-for="c in cards" :key="c.label" class="card flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl text-xl text-white" :class="c.tone">{{ c.icon }}</div>
                    <div>
                        <div class="text-xs text-slate-500">{{ c.label }}</div>
                        <div class="text-lg font-bold">{{ c.value }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="mb-3 font-bold">🏪 {{ t('admin.recent_stores') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-400">
                            <tr><th class="py-2">{{ t('common.name') }}</th><th>{{ t('admin.status') }}</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in data.recent_stores" :key="s.id" class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-2 font-medium">{{ s.name }}</td>
                                <td><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800">{{ s.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
