<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const currency = computed(() => auth.currency);

const today = new Date().toISOString().slice(0, 10);
const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

const dateFrom = ref(monthStart);
const dateTo = ref(today);
const tab = ref('overview');
const loading = ref(true);
const summary = ref(null);
const sales = ref([]);
const products = ref([]);
const inventory = ref(null);
const dues = ref(null);

const params = computed(() => ({ date_from: dateFrom.value, date_to: dateTo.value }));

const kpis = computed(() => {
    const s = summary.value || {};
    return [
        { label: t('report.gross_sales'), value: s.gross_sales },
        { label: t('report.net_revenue'), value: s.net_revenue },
        { label: t('report.gross_profit'), value: s.gross_profit },
        { label: t('report.due_outstanding'), value: s.due_outstanding },
        { label: t('report.returns'), value: s.returns },
        { label: t('report.purchases'), value: s.purchases },
        { label: t('report.cogs'), value: s.cogs },
        { label: t('report.sales_count'), value: s.sales_count, raw: true },
    ];
});

async function load() {
    loading.value = true;
    try {
        const [sum, trend, top, inv, due] = await Promise.all([
            api.get('/reports/summary', { params: params.value }),
            api.get('/reports/sales', { params: params.value }),
            api.get('/reports/products', { params: params.value }),
            api.get('/reports/inventory'),
            api.get('/reports/dues'),
        ]);
        summary.value = sum.data.data;
        sales.value = trend.data.data;
        products.value = top.data.data;
        inventory.value = inv.data.data;
        dues.value = due.data.data;
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        loading.value = false;
    }
}

async function exportCsv(type) {
    try {
        const { data } = await api.get('/reports/export', {
            params: { ...params.value, type },
            responseType: 'blob',
        });
        const url = URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `report-${type}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        ui.error('Export failed');
    }
}

function money(n) {
    return `${currency.value}${Number(n || 0).toFixed(2)}`;
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('report.title') }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <input v-model="dateFrom" type="date" class="input h-10 w-auto" />
                <input v-model="dateTo" type="date" class="input h-10 w-auto" />
                <button class="btn-primary h-10" @click="load">{{ t('report.apply') }}</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="id in ['overview', 'inventory', 'dues']" :key="id" class="btn" :class="tab === id ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="tab = id">
                {{ t('report.' + id) }}
            </button>
        </div>

        <Spinner v-if="loading" />
        <div v-else class="space-y-4">
            <template v-if="tab === 'overview'">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div v-for="k in kpis" :key="k.label" class="card">
                        <div class="text-sm text-slate-500">{{ k.label }}</div>
                        <div class="text-xl font-bold">{{ k.raw ? k.value : money(k.value) }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-secondary" @click="exportCsv('sales')">{{ t('report.export_csv') }} · {{ t('nav.sales') }}</button>
                    <button class="btn-secondary" @click="exportCsv('products')">{{ t('report.export_csv') }} · {{ t('report.top_products') }}</button>
                    <button class="btn-secondary" @click="exportCsv('purchases')">{{ t('report.export_csv') }} · {{ t('report.purchases') }}</button>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card overflow-x-auto p-0">
                        <h3 class="p-4 font-bold">{{ t('nav.sales') }}</h3>
                        <table class="w-full text-sm">
                            <thead class="text-left text-slate-400">
                                <tr><th class="px-4 pb-2">{{ t('report.from') }}</th><th class="text-right">{{ t('report.sales_count') }}</th><th class="px-4 text-right">{{ t('common.total') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in sales" :key="row.day" class="border-t border-slate-50 dark:border-slate-800">
                                    <td class="px-4 py-2">{{ row.day }}</td>
                                    <td class="text-right">{{ row.orders }}</td>
                                    <td class="px-4 text-right font-semibold">{{ money(row.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card overflow-x-auto p-0">
                        <h3 class="p-4 font-bold">{{ t('report.top_products') }}</h3>
                        <table class="w-full text-sm">
                            <thead class="text-left text-slate-400">
                                <tr><th class="px-4 pb-2">{{ t('common.name') }}</th><th class="text-right">{{ t('report.qty') }}</th><th class="px-4 text-right">{{ t('report.profit') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in products" :key="p.name" class="border-t border-slate-50 dark:border-slate-800">
                                    <td class="px-4 py-2">{{ p.name }}</td>
                                    <td class="text-right">{{ Number(p.qty) }}</td>
                                    <td class="px-4 text-right font-semibold">{{ money(p.profit) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-else-if="tab === 'inventory'">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.valuation_cost') }}</div><div class="text-xl font-bold">{{ money(inventory?.valuation_cost) }}</div></div>
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.valuation_retail') }}</div><div class="text-xl font-bold">{{ money(inventory?.valuation_retail) }}</div></div>
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.potential_profit') }}</div><div class="text-xl font-bold">{{ money(inventory?.potential_profit) }}</div></div>
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.low_stock') }}</div><div class="text-xl font-bold text-amber-600">{{ inventory?.low_stock_count || 0 }}</div></div>
                </div>
                <button class="btn-secondary" @click="exportCsv('inventory')">{{ t('report.export_csv') }}</button>
                <div class="card overflow-x-auto p-0">
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-400">
                            <tr>
                                <th class="p-3">{{ t('common.name') }}</th>
                                <th class="text-right">{{ t('common.stock') }}</th>
                                <th class="p-3 text-right">{{ t('product.alert_qty') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in inventory?.low_stock || []" :key="p.id" class="border-t border-slate-50 dark:border-slate-800">
                                <td class="p-3">{{ p.name }}</td>
                                <td class="text-right font-semibold text-amber-600">{{ Number(p.stock_quantity) }}</td>
                                <td class="p-3 text-right">{{ Number(p.alert_quantity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-else>
                <div class="grid grid-cols-2 gap-3">
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.receivable') }}</div><div class="text-xl font-bold text-emerald-600">{{ money(dues?.receivable) }}</div></div>
                    <div class="card"><div class="text-sm text-slate-500">{{ t('report.payable') }}</div><div class="text-xl font-bold text-red-500">{{ money(dues?.payable) }}</div></div>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card overflow-x-auto p-0">
                        <h3 class="p-4 font-bold">{{ t('nav.customers') }}</h3>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="c in dues?.customers || []" :key="c.id" class="border-t border-slate-50 dark:border-slate-800">
                                    <td class="p-3">{{ c.name }}</td>
                                    <td class="p-3 text-right font-semibold">{{ money(c.due_balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card overflow-x-auto p-0">
                        <h3 class="p-4 font-bold">{{ t('nav.suppliers') }}</h3>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="s in dues?.suppliers || []" :key="s.id" class="border-t border-slate-50 dark:border-slate-800">
                                    <td class="p-3">{{ s.name }}</td>
                                    <td class="p-3 text-right font-semibold">{{ money(s.due_balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
