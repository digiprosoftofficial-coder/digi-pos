<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ProductForm from '@/views/products/ProductForm.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import { variantSellingPrice } from '@/lib/productPricing';
import { useOfflineStore } from '@/stores/offline';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const offline = useOfflineStore();
const currency = computed(() => auth.currency);

const products = ref([]);
const loading = ref(true);
const search = ref('');
const showForm = ref(false);
const editing = ref(null);
const adjustFor = ref(null);
const adjust = ref({ quantity: 1, direction: 'add', reason: '', variant_id: '' });
const movements = ref([]);

let debounce;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(load, 250);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/products', { params: { search: search.value, per_page: 50 } });
        products.value = data.data;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    showForm.value = true;
}
function edit(p) {
    editing.value = p;
    showForm.value = true;
}
async function remove(p) {
    if (!confirm(`${t('common.delete')}: ${p.name}?`)) return;
    await api.delete(`/products/${p.id}`);
    ui.success(t('common.delete'));
    load();
}
async function onSaved() {
    showForm.value = false;
    await load();
    offline.syncCatalog().catch(() => {});
}

async function openAdjust(p) {
    adjustFor.value = p;
    adjust.value = { quantity: 1, direction: 'add', reason: '', variant_id: '' };
    try {
        const { data } = await api.get(`/products/${p.id}/stock-movements`);
        movements.value = data.data;
    } catch (e) {
        movements.value = [];
    }
}

async function saveAdjust() {
    const qty = Number(adjust.value.quantity) || 0;
    if (qty <= 0) return;
    const signed = adjust.value.direction === 'remove' ? -qty : qty;
    try {
        await api.post(`/products/${adjustFor.value.id}/adjust-stock`, {
            quantity: signed,
            reason: adjust.value.reason || 'manual',
            variant_id: adjust.value.variant_id || null,
        });
        ui.success(t('stock.adjust'));
        adjustFor.value = null;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}

function variantList(p) {
    return p.type === 'variable' ? p.variants || [] : [];
}
function totalStock(p) {
    const variants = variantList(p);
    if (variants.length) {
        return variants.reduce((sum, v) => sum + Number(v.stock_quantity || 0), 0);
    }
    return Number(p.stock_quantity || 0);
}
function priceLabel(p) {
    const variants = variantList(p);
    if (!variants.length) {
        return `${currency.value}${p.selling_price}`;
    }
    const prices = variants.map((v) => variantSellingPrice(v, p));
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    if (min === max) {
        return `${currency.value}${min}`;
    }
    return `${currency.value}${min}–${currency.value}${max}`;
}
function isLow(p) {
    if (!p.track_stock) {
        return false;
    }
    const variants = variantList(p);
    if (variants.length) {
        return variants.some((v) => Number(v.stock_quantity) <= Number(p.alert_quantity || 0));
    }
    return p.is_low_stock;
}
function isVariantLow(p, v) {
    return p.track_stock && Number(v.stock_quantity) <= Number(p.alert_quantity || 0);
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('product.title') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('product.new') }}</button>
        </div>

        <div class="mb-4">
            <input v-model="search" class="input" :placeholder="t('common.search')" @input="onSearch" />
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!products.length" icon="📦" :title="t('common.no_data')">
            <button class="btn-primary" @click="create">+ {{ t('product.new') }}</button>
        </EmptyState>
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('common.name') }}</th>
                        <th>{{ t('common.sku') }}</th>
                        <th class="text-right">{{ t('common.price') }}</th>
                        <th class="text-right">{{ t('common.stock') }}</th>
                        <th class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="p in products" :key="p.id">
                        <tr :class="variantList(p).length ? 'border-b border-slate-50/80 dark:border-slate-800/40' : 'border-b border-slate-50 dark:border-slate-800/50'">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="p.image"
                                        :src="p.image"
                                        :alt="p.name"
                                        class="h-10 w-10 shrink-0 rounded-lg object-cover"
                                    />
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ p.name }}</div>
                                        <span v-if="variantList(p).length" class="text-xs text-brand-600">{{ variantList(p).length }} {{ t('product.variants') }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-500">{{ p.sku || '—' }}</td>
                            <td class="text-right font-semibold">{{ priceLabel(p) }}</td>
                            <td class="text-right">
                                <span :class="isLow(p) ? 'font-bold text-amber-600' : ''">{{ p.track_stock ? totalStock(p) : '∞' }}</span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button v-if="p.track_stock" class="btn-ghost h-8 px-2" @click="openAdjust(p)">±</button>
                                <button class="btn-ghost h-8 px-2" @click="edit(p)">✏️</button>
                                <button class="btn-ghost h-8 px-2 text-red-500" @click="remove(p)">🗑</button>
                            </td>
                        </tr>
                        <tr
                            v-for="v in variantList(p)"
                            :key="`${p.id}-${v.id}`"
                            class="border-b border-slate-50 text-slate-500 dark:border-slate-800/50 dark:text-slate-400"
                        >
                            <td class="py-2 pr-3 pl-8">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="v.image"
                                        :src="v.image"
                                        :alt="v.name"
                                        class="h-8 w-8 shrink-0 rounded object-cover"
                                    />
                                    <span>{{ v.name }}</span>
                                </div>
                            </td>
                            <td>{{ v.sku || v.barcode || '—' }}</td>
                            <td class="text-right">{{ currency }}{{ variantSellingPrice(v, p) }}</td>
                            <td class="text-right">
                                <span :class="isVariantLow(p, v) ? 'font-bold text-amber-600' : ''">{{ p.track_stock ? v.stock_quantity : '∞' }}</span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button class="btn-ghost h-8 px-2" @click="edit(p)">✏️</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <ProductForm :show="showForm" :product="editing" @saved="onSaved" @close="showForm = false" />

        <BaseModal :show="!!adjustFor" :title="t('stock.adjust')" @close="adjustFor = null">
            <div v-if="adjustFor" class="space-y-3">
                <div class="text-sm text-slate-500">{{ adjustFor.name }} — {{ t('stock.current') }}: {{ totalStock(adjustFor) }}</div>
                <div v-if="adjustFor.variants?.length">
                    <label class="label">{{ t('product.variants') }}</label>
                    <select v-model="adjust.variant_id" class="input">
                        <option value="">—</option>
                        <option v-for="v in adjustFor.variants" :key="v.id" :value="v.id">{{ v.name }} ({{ v.stock_quantity }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button class="btn py-2" :class="adjust.direction === 'add' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="adjust.direction = 'add'">+ {{ t('stock.add') }}</button>
                    <button class="btn py-2" :class="adjust.direction === 'remove' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="adjust.direction = 'remove'">− {{ t('stock.remove') }}</button>
                </div>
                <div><label class="label">{{ t('stock.qty') }}</label><input v-model.number="adjust.quantity" type="number" min="0.01" class="input" /></div>
                <div><label class="label">{{ t('stock.reason') }}</label><input v-model="adjust.reason" class="input" /></div>
                <div v-if="movements.length" class="max-h-40 overflow-y-auto text-xs text-slate-500">
                    <div v-for="m in movements" :key="m.id" class="flex justify-between py-0.5">
                        <span>{{ m.type }} {{ m.reference }}</span>
                        <span :class="Number(m.quantity) < 0 ? 'text-red-500' : 'text-emerald-600'">{{ m.quantity }}</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="adjustFor = null">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="saveAdjust">{{ t('common.save') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
