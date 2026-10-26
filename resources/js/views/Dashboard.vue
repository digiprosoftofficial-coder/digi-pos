<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import Spinner from '@/components/ui/Spinner.vue';

const { t } = useI18n();
const auth = useAuthStore();
const currency = computed(() => auth.currency);
const data = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await api.get('/dashboard');
        data.value = res.data.data;
    } finally {
        loading.value = false;
    }
});

const cards = computed(() => [
    { label: t('dashboard.today_sales'), value: `${currency.value}${(data.value?.today.sales_total || 0).toFixed(2)}`, icon: '💰', tone: 'bg-emerald-500' },
    { label: t('dashboard.today_orders'), value: data.value?.today.sales_count || 0, icon: '🧾', tone: 'bg-brand-500' },
    { label: t('dashboard.total_products'), value: data.value?.totals.products || 0, icon: '📦', tone: 'bg-amber-500' },
    { label: t('dashboard.total_customers'), value: data.value?.totals.customers || 0, icon: '👥', tone: 'bg-pink-500' },
]);
</script>

<template>
    <div>
        <h1 class="mb-4 text-2xl font-bold">{{ t('dashboard.title') }}</h1>
        <Spinner v-if="loading" />
        <div v-else class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="c in cards" :key="c.label" class="card flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl text-white" :class="c.tone">{{ c.icon }}</div>
                    <div>
                        <div class="text-sm text-slate-500">{{ c.label }}</div>
                        <div class="text-xl font-bold">{{ c.value }}</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="card">
                    <h3 class="mb-3 font-bold">🔥 {{ t('dashboard.top_products') }}</h3>
                    <div v-if="!data.top_products.length" class="text-sm text-slate-400">{{ t('common.no_data') }}</div>
                    <ul class="space-y-2">
                        <li v-for="p in data.top_products" :key="p.name" class="flex justify-between text-sm">
                            <span>{{ p.name }}</span>
                            <span class="font-semibold">{{ Number(p.qty) }} · {{ currency }}{{ Number(p.revenue).toFixed(0) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="card">
                    <h3 class="mb-3 font-bold text-amber-600">⚠️ {{ t('dashboard.low_stock') }}</h3>
                    <div v-if="!data.low_stock.length" class="text-sm text-slate-400">{{ t('common.no_data') }}</div>
                    <ul class="space-y-2">
                        <li v-for="p in data.low_stock" :key="p.id" class="flex justify-between text-sm">
                            <span>{{ p.name }}</span>
                            <span class="font-semibold text-amber-600">{{ Number(p.stock_quantity) }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <h3 class="mb-3 font-bold">🧾 {{ t('dashboard.recent_sales') }}</h3>
                <div v-if="!data.recent_sales.length" class="text-sm text-slate-400">{{ t('common.no_data') }}</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-400">
                            <tr>
                                <th class="py-2">Invoice</th>
                                <th>Customer</th>
                                <th class="text-right">{{ t('common.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in data.recent_sales" :key="s.id" class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-2 font-medium">{{ s.invoice_no }}</td>
                                <td>{{ s.customer?.name || t('pos.walk_in') }}</td>
                                <td class="text-right font-semibold">{{ currency }}{{ Number(s.total).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
