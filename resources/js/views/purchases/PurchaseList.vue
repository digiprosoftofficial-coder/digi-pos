<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const ui = useUiStore();
const auth = useAuthStore();
const currency = computed(() => auth.currency);

const purchases = ref([]);
const suppliers = ref([]);
const loading = ref(true);
const detail = ref(null);

// Create form
const showForm = ref(false);
const saving = ref(false);
const form = ref(blankForm());
const productQuery = ref('');
const productResults = ref([]);

function blankForm() {
    return {
        supplier_id: '',
        supplier_invoice: '',
        discount: 0,
        tax: 0,
        shipping: 0,
        paid: 0,
        payment_method: 'cash',
        status: 'received',
        update_cost: true,
        note: '',
        items: [],
    };
}

async function load() {
    loading.value = true;
    try {
        const [p, s] = await Promise.all([
            api.get('/purchases', { params: { per_page: 50 } }),
            api.get('/suppliers', { params: { per_page: 100 } }),
        ]);
        purchases.value = p.data.data.data || p.data.data;
        suppliers.value = s.data.data.data || s.data.data;
    } finally {
        loading.value = false;
    }
}

const itemsSubtotal = computed(() =>
    form.value.items.reduce((sum, i) => sum + i.unit_cost * i.quantity - (i.discount || 0), 0)
);
const grandTotal = computed(() =>
    Math.max(0, itemsSubtotal.value - form.value.discount + form.value.tax + form.value.shipping)
);

let debounce;
function searchProducts() {
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        if (!productQuery.value.trim()) {
            productResults.value = [];
            return;
        }
        const { data } = await api.get('/lookup/search', { params: { q: productQuery.value } });
        productResults.value = data.data;
    }, 250);
}

function addItem(p) {
    if (form.value.items.find((i) => i.product_id === p.id && !i.variant_id)) return;
    form.value.items.push({
        product_id: p.id,
        variant_id: null,
        name: p.name,
        quantity: 1,
        unit_cost: Number(p.cost_price) || 0,
        discount: 0,
    });
    productQuery.value = '';
    productResults.value = [];
}
function removeItem(idx) {
    form.value.items.splice(idx, 1);
}

function openForm() {
    form.value = blankForm();
    showForm.value = true;
}

async function save() {
    if (!form.value.items.length) {
        ui.error(t('purchase.none'));
        return;
    }
    saving.value = true;
    try {
        const payload = {
            ...form.value,
            supplier_id: form.value.supplier_id || null,
            items: form.value.items.map((i) => ({
                product_id: i.product_id,
                variant_id: i.variant_id,
                quantity: i.quantity,
                unit_cost: i.unit_cost,
                discount: i.discount || 0,
            })),
        };
        await api.post('/purchases', payload);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        saving.value = false;
    }
}

async function open(p) {
    const { data } = await api.get(`/purchases/${p.id}`);
    detail.value = data.data;
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('purchase.title') }}</h1>
            <button class="btn-primary" @click="openForm">+ {{ t('purchase.new') }}</button>
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!purchases.length" icon="📥" :title="t('common.no_data')">
            <button class="btn-primary" @click="openForm">+ {{ t('purchase.new') }}</button>
        </EmptyState>
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('purchase.reference') }}</th>
                        <th>{{ t('purchase.supplier') }}</th>
                        <th>{{ t('purchase.status') }}</th>
                        <th class="text-right">{{ t('common.total') }}</th>
                        <th class="text-right">{{ t('pos.due') }}</th>
                        <th class="p-3 text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in purchases" :key="p.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ p.reference }}</td>
                        <td class="text-slate-500">{{ p.supplier?.name || '—' }}</td>
                        <td><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800">{{ p.status }}</span></td>
                        <td class="text-right font-semibold">{{ currency }}{{ Number(p.total).toFixed(2) }}</td>
                        <td class="text-right" :class="Number(p.due) > 0 ? 'text-red-500' : ''">{{ currency }}{{ Number(p.due).toFixed(2) }}</td>
                        <td class="p-3 text-right"><button class="btn-ghost h-8 px-2" @click="open(p)">👁</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create purchase -->
        <BaseModal :show="showForm" :title="t('purchase.new')" size="lg" @close="showForm = false">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ t('purchase.supplier') }}</label>
                        <select v-model="form.supplier_id" class="input">
                            <option value="">—</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ t('purchase.supplier_invoice') }}</label>
                        <input v-model="form.supplier_invoice" class="input" />
                    </div>
                </div>

                <!-- Product search -->
                <div class="relative">
                    <label class="label">{{ t('purchase.items') }}</label>
                    <input v-model="productQuery" class="input" :placeholder="t('purchase.search_product')" @input="searchProducts" />
                    <div v-if="productResults.length" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <button
                            v-for="p in productResults"
                            :key="p.id"
                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                            @click="addItem(p)"
                        >
                            <span>{{ p.name }}</span>
                            <span class="text-slate-400">{{ currency }}{{ p.cost_price }}</span>
                        </button>
                    </div>
                </div>

                <!-- Items -->
                <div v-if="form.items.length" class="space-y-2">
                    <div v-for="(it, idx) in form.items" :key="idx" class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 rounded-xl bg-slate-50 p-2 dark:bg-slate-800">
                        <span class="truncate text-sm font-medium">{{ it.name }}</span>
                        <input v-model.number="it.quantity" type="number" min="1" class="input h-9 w-20 px-2" :title="t('purchase.qty')" />
                        <input v-model.number="it.unit_cost" type="number" min="0" class="input h-9 w-24 px-2" :title="t('purchase.unit_cost')" />
                        <button class="text-slate-400 hover:text-red-500" @click="removeItem(idx)">✕</button>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400">{{ t('purchase.none') }}</p>

                <!-- Totals -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div><label class="label">{{ t('pos.discount') }}</label><input v-model.number="form.discount" type="number" class="input h-9" /></div>
                    <div><label class="label">{{ t('pos.tax') }}</label><input v-model.number="form.tax" type="number" class="input h-9" /></div>
                    <div><label class="label">{{ t('purchase.shipping') }}</label><input v-model.number="form.shipping" type="number" class="input h-9" /></div>
                    <div><label class="label">{{ t('pos.paid') }}</label><input v-model.number="form.paid" type="number" class="input h-9" /></div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.update_cost" type="checkbox" /> {{ t('purchase.update_cost') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.status" type="checkbox" true-value="received" false-value="pending" />
                        {{ t('purchase.received') }}
                    </label>
                    <div class="text-lg font-bold">{{ t('purchase.grand_total') }}: {{ currency }}{{ grandTotal.toFixed(2) }}</div>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" :disabled="saving" @click="save">{{ t('purchase.save') }}</button>
            </template>
        </BaseModal>

        <!-- Detail -->
        <BaseModal :show="!!detail" :title="detail?.reference" @close="detail = null">
            <div v-if="detail" class="space-y-2 text-sm">
                <div class="text-slate-500">{{ detail.supplier?.name }} · {{ detail.status }}</div>
                <div v-for="it in detail.items" :key="it.id" class="flex justify-between">
                    <span>{{ it.name }} × {{ Number(it.quantity) }}</span>
                    <span>{{ currency }}{{ Number(it.subtotal).toFixed(2) }}</span>
                </div>
                <div class="mt-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                    <div class="flex justify-between font-bold"><span>{{ t('common.total') }}</span><span>{{ currency }}{{ Number(detail.total).toFixed(2) }}</span></div>
                    <div class="flex justify-between"><span>{{ t('pos.paid') }}</span><span>{{ currency }}{{ Number(detail.paid).toFixed(2) }}</span></div>
                    <div class="flex justify-between text-red-500"><span>{{ t('pos.due') }}</span><span>{{ currency }}{{ Number(detail.due).toFixed(2) }}</span></div>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
