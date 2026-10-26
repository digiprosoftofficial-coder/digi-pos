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
const canExport = computed(() => auth.hasCapability('reports.advanced'));

const today = new Date().toISOString().slice(0, 10);
const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
const dateFrom = ref(monthStart);
const dateTo = ref(today);
const tab = ref('overview');
const loading = ref(false);
const summary = ref(null);
const daily = ref([]);
const products = ref([]);
const inventory = ref(null);
const dues = ref(null);

const params = computed(() => ({ date_from: dateFrom.value, date_to: dateTo.value }));

async function load() {
    loading.value = true;
    try {
        const [s, d, p, inv, due] = await Promise.all([
            api.get('/reports/summary', { params: params.value }),
            api.get('/reports/sales', { params: params.value }),
            api.get('/reports/products', { params: params.value }),
            api.get('/reports/inventory'),
            api.get('/reports/dues'),
        ]);
        summary.value = s.data.data;
        daily.value = d.data.data;
        products.value = p.data.data;
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

const kpis = computed(() => {
    const s = summary.value;
    if (!s) return [];
    return [
        { label: t('report.sales_count'), value: s.sales_count, raw: true },
        { label: t('report.gross_sales'), value: s.gross_sales },
        { label: t('report.returns'), value: s.returns },
        { label: t('report.net_revenue'), value: s.net_revenue },
        { label: t('report.cogs'), value: s.cogs },
        { label: t('report.gross_profit'), value: s.gross_profit },
        { label: t('report.purchases'), value: s.purchases },
        { label: t('report.due_outstanding'), value: s.due_outstanding },
    ];
});

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('report.title') }}</h1>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="label">{{ t('report.from') }}</label>
                    <input v-model="dateFrom" type="date" class="input h-10" />
                </div>
                <div>
                    <label class="label">{{ t('report.to') }}</label>
                    <input v-model="dateTo" type="date" class="input h-10" />
                </div>
                <button class="btn-primary h-10" @click="load">{{ t('report.apply') }}</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button class="btn" :class="tab === 'overview' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="tab = 'overview'">{{ t('report.overview') }}</button>
            <button class="btn" :class="tab === 'inventory' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="tab = 'inventory'">{{ t('report.inventory') }}</button>
            <button class="btn" :class="tab === 'dues' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="tab = 'dues'">{{ t('report.dues') }}</button>
            <button v-if="canExport" class="btn-secondary" @click="exportCsv('sales')">⬇️ {{ t('report.export_csv') }}</button>
        </div>

        <Spinner v-if="loading" />

        <div v-else-if="tab === 'overview'">
            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div v-for="k in kpis" :key="k.label" class="card p-4">
                    <div class="text-xs text-slate-500">{{ k.label }}</div>
                    <div class="mt-1 text-xl font-bold">{{ k.raw ? k.value : currency + Number(k.value || 0).toFixed(2) }}</div>
                </div>
            </div>

            <div class="card overflow-x-auto p-0">
                <div class="border-b border-slate-100 px-4 py-3 font-semibold dark:border-slate-800">{{ t('report.top_products') }}</div>
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="p-3">{{ t('common.name') }}</th>
                            <th class="text-right">{{ t('report.qty') }}</th>
                            <th class="text-right">{{ t('report.revenue') }}</th>
                            <th class="text-right">{{ t('report.profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in products" :key="p.name" class="border-t border-slate-50 dark:border-slate-800/50">
                            <td class="p-3">{{ p.name }}</td>
                            <td class="text-right">{{ Number(p.qty) }}</td>
                            <td class="text-right">{{ currency }}{{ Number(p.revenue).toFixed(2) }}</td>
                            <td class="p-3 text-right font-semibold">{{ currency }}{{ Number(p.profit).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="tab === 'inventory' && inventory" class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="card p-4">
                    <div class="text-xs text-slate-500">{{ t('report.valuation_cost') }}</div>
                    <div class="text-xl font-bold">{{ currency }}{{ Number(inventory.valuation_cost).toFixed(2) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-slate-500">{{ t('report.valuation_retail') }}</div>
                    <div class="text-xl font-bold">{{ currency }}{{ Number(inventory.valuation_retail).toFixed(2) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-slate-500">{{ t('report.potential_profit') }}</div>
                    <div class="text-xl font-bold text-emerald-600">{{ currency }}{{ Number(inventory.potential_profit).toFixed(2) }}</div>
                </div>
            </div>
            <div class="card overflow-x-auto p-0">
                <div class="border-b border-slate-100 px-4 py-3 font-semibold dark:border-slate-800">{{ t('report.low_stock') }}</div>
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="p-3">{{ t('common.name') }}</th>
                            <th>{{ t('common.sku') }}</th>
                            <th class="text-right">{{ t('common.stock') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in inventory.low_stock" :key="p.id" class="border-t border-slate-50 dark:border-slate-800/50">
                            <td class="p-3">{{ p.name }}</td>
                            <td class="text-slate-500">{{ p.sku || '—' }}</td>
                            <td class="p-3 text-right font-semibold text-amber-600">{{ Number(p.stock_quantity) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="tab === 'dues' && dues" class="grid gap-4 lg:grid-cols-2">
            <div class="card overflow-x-auto p-0">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <span class="font-semibold">{{ t('report.receivable') }}</span>
                    <span class="font-bold text-amber-600">{{ currency }}{{ Number(dues.receivable).toFixed(2) }}</span>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="c in dues.customers" :key="c.id" class="border-t border-slate-50 dark:border-slate-800/50">
                            <td class="p-3">{{ c.name }} <span class="text-slate-400">{{ c.phone }}</span></td>
                            <td class="p-3 text-right font-semibold">{{ currency }}{{ Number(c.due_balance).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card overflow-x-auto p-0">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <span class="font-semibold">{{ t('report.payable') }}</span>
                    <span class="font-bold text-red-500">{{ currency }}{{ Number(dues.payable).toFixed(2) }}</span>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="s in dues.suppliers" :key="s.id" class="border-t border-slate-50 dark:border-slate-800/50">
                            <td class="p-3">{{ s.name }}</td>
                            <td class="p-3 text-right font-semibold">{{ currency }}{{ Number(s.due_balance).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
