<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const route = useRoute();
const ui = useUiStore();
const auth = useAuthStore();
const currency = computed(() => auth.currency);

const returns = ref([]);
const loading = ref(true);
const showForm = ref(false);
const invoice = ref('');
const sale = ref(null);
const lines = ref([]);
const refundMethod = ref('cash');
const restock = ref(true);
const note = ref('');
const saving = ref(false);
const finding = ref(false);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/sale-returns', { params: { per_page: 50 } });
        returns.value = data.data.data || data.data;
    } finally {
        loading.value = false;
    }
}

async function findSale() {
    const q = invoice.value.trim();
    if (!q) return;
    finding.value = true;
    sale.value = null;
    lines.value = [];
    try {
        const { data } = await api.get('/sales', { params: { search: q, per_page: 5 } });
        const list = data.data.data || data.data;
        if (!list.length) {
            ui.error(t('returns.no_sale'));
            return;
        }
        const detail = await api.get(`/sales/${list[0].id}`);
        sale.value = detail.data.data;
        lines.value = (sale.value.items || []).map((it) => ({
            sale_item_id: it.id,
            name: it.name,
            sold: Number(it.quantity),
            unit_price: Number(it.unit_price),
            return_qty: 0,
        }));
    } catch (e) {
        ui.error(e.response?.data?.message || t('returns.no_sale'));
    } finally {
        finding.value = false;
    }
}

const returnTotal = computed(() =>
    lines.value.reduce((sum, l) => sum + (Number(l.return_qty) || 0) * (Number(l.unit_price) || 0), 0)
);

async function process() {
    const items = lines.value
        .filter((l) => Number(l.return_qty) > 0)
        .map((l) => ({ sale_item_id: l.sale_item_id, quantity: Number(l.return_qty) }));
    if (!sale.value || !items.length) {
        ui.error(t('returns.no_sale'));
        return;
    }
    saving.value = true;
    try {
        await api.post('/sale-returns', {
            sale_id: sale.value.id,
            refund_method: refundMethod.value,
            restock: restock.value,
            note: note.value || null,
            items,
        });
        ui.success(t('returns.process'));
        showForm.value = false;
        resetForm();
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || e.response?.data?.errors?.items?.[0] || 'Failed');
    } finally {
        saving.value = false;
    }
}

function resetForm() {
    invoice.value = '';
    sale.value = null;
    lines.value = [];
    refundMethod.value = 'cash';
    restock.value = true;
    note.value = '';
}

function openForm() {
    resetForm();
    if (route.query.invoice) invoice.value = String(route.query.invoice);
    showForm.value = true;
    if (invoice.value) findSale();
}

watch(
    () => route.query.invoice,
    (val) => {
        if (val) openForm();
    }
);

onMounted(() => {
    load();
    if (route.query.invoice) openForm();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('returns.title') }}</h1>
            <button class="btn-primary" @click="openForm">+ {{ t('returns.new') }}</button>
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!returns.length" icon="↩️" :title="t('common.no_data')">
            <button class="btn-primary" @click="openForm">+ {{ t('returns.new') }}</button>
        </EmptyState>
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('returns.invoice') }}</th>
                        <th>{{ t('common.name') }}</th>
                        <th>{{ t('returns.refund_method') }}</th>
                        <th class="text-right">{{ t('common.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in returns" :key="r.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ r.reference }} · {{ r.sale?.invoice_no }}</td>
                        <td class="text-slate-500">{{ r.customer?.name || t('pos.walk_in') }}</td>
                        <td>{{ r.refund_method }}</td>
                        <td class="p-3 text-right font-semibold">{{ currency }}{{ Number(r.total).toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="showForm" :title="t('returns.new')" size="lg" @close="showForm = false">
            <div class="space-y-4">
                <div class="flex gap-2">
                    <input v-model="invoice" class="input flex-1" :placeholder="t('returns.select_sale')" @keyup.enter="findSale" />
                    <button class="btn-secondary" :disabled="finding" @click="findSale">{{ t('common.search') }}</button>
                </div>

                <p v-if="!sale" class="text-sm text-slate-400">{{ t('returns.no_sale') }}</p>
                <div v-else class="space-y-3">
                    <div class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div class="font-semibold">{{ sale.invoice_no }}</div>
                        <div class="text-slate-500">{{ sale.customer?.name || t('pos.walk_in') }} · {{ currency }}{{ Number(sale.total).toFixed(2) }}</div>
                    </div>
                    <div v-for="line in lines" :key="line.sale_item_id" class="grid grid-cols-[1fr_auto_auto] items-center gap-2 rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <span class="truncate text-sm font-medium">{{ line.name }}</span>
                        <span class="text-xs text-slate-400">{{ t('returns.sold') }} {{ line.sold }}</span>
                        <input v-model.number="line.return_qty" type="number" min="0" :max="line.sold" class="input h-9 w-20 px-2" :title="t('returns.return_qty')" />
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">{{ t('returns.refund_method') }}</label>
                            <select v-model="refundMethod" class="input">
                                <option value="cash">{{ t('returns.cash') }}</option>
                                <option value="due_adjust">{{ t('returns.due_adjust') }}</option>
                                <option value="store_credit">{{ t('returns.store_credit') }}</option>
                            </select>
                        </div>
                        <label class="mt-6 flex items-center gap-2 text-sm">
                            <input v-model="restock" type="checkbox" /> {{ t('returns.restock') }}
                        </label>
                    </div>
                    <div class="text-right text-lg font-bold">{{ t('common.total') }}: {{ currency }}{{ returnTotal.toFixed(2) }}</div>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" :disabled="saving || !sale" @click="process">{{ t('returns.process') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
