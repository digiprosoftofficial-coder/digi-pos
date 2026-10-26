<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useOfflineStore } from '@/stores/offline';
import { localSearch, localScan } from '@/lib/sync';
import BaseModal from '@/components/ui/BaseModal.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BarcodeScanner from '@/components/BarcodeScanner.vue';
import PaymentModal from '@/views/pos/PaymentModal.vue';
import ReceiptModal from '@/views/pos/ReceiptModal.vue';
import VariantPicker from '@/views/pos/VariantPicker.vue';

const { t } = useI18n();
const cart = useCartStore();
const auth = useAuthStore();
const ui = useUiStore();
const offline = useOfflineStore();

const query = ref('');
const results = ref([]);
const loading = ref(false);
const searchBox = ref(null);
const showScanner = ref(false);
const showPayment = ref(false);
const showCartSheet = ref(false);
const lastSale = ref(null);
const showReceipt = ref(false);
const variantProduct = ref(null);
const customerQuery = ref('');
const customerResults = ref([]);
const branches = ref([]);

const currency = computed(() => auth.currency);
const showBranches = computed(() => auth.hasCapability('multi_branch') && branches.value.length > 1);

let debounce;
watch(query, (val) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => runSearch(val), 250);
});

async function runSearch(q) {
    loading.value = true;
    try {
        if (!ui.online) {
            results.value = await localSearch(q);
            return;
        }
        const { data } = await api.get('/lookup/search', { params: { q } });
        results.value = data.data;
    } catch (e) {
        // Network failed mid-request — fall back to the cached catalog.
        results.value = await localSearch(q).catch(() => []);
    } finally {
        loading.value = false;
    }
}

// USB scanner / Enter key: exact scan lookup (online API, offline cache fallback)
async function onEnter() {
    const code = query.value.trim();
    if (!code) return;
    try {
        let result;
        if (!ui.online) {
            result = await localScan(code);
            if (!result) throw new Error('not-found');
        } else {
            const { data } = await api.get('/lookup/scan', { params: { code } });
            result = data.data;
        }
        addFromLookup(result);
        query.value = '';
        results.value = [];
    } catch (e) {
        // Last resort: try the local cache before giving up.
        const local = await localScan(code).catch(() => null);
        if (local) {
            addFromLookup(local);
            query.value = '';
            results.value = [];
        } else {
            ui.error(t('common.no_data'));
        }
    }
    focusSearch();
}

function addFromLookup({ product, variant_id }) {
    if (product.type === 'variable' && !variant_id) {
        variantProduct.value = product;
        return;
    }
    cart.addProduct(product, variant_id);
    beep();
}

function addProduct(product) {
    if (product.type === 'variable') {
        variantProduct.value = product;
        return;
    }
    cart.addProduct(product);
    beep();
}

function onVariantPicked(variantId) {
    cart.addProduct(variantProduct.value, variantId);
    variantProduct.value = null;
    beep();
}

function onScannerDetected(code) {
    query.value = code;
    showScanner.value = false;
    onEnter();
}

function beep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        o.frequency.value = 880;
        o.connect(ctx.destination);
        o.start();
        setTimeout(() => {
            o.stop();
            ctx.close();
        }, 90);
    } catch (e) {
        // ignore
    }
}

function focusSearch() {
    nextTick(() => searchBox.value?.focus());
}

async function hold() {
    if (!cart.items.length) return;
    try {
        await api.post('/held-sales', {
            customer_id: cart.customer?.id || null,
            cart: { items: cart.items, customer: cart.customer, discount: cart.discount, note: cart.note },
        });
        ui.success(t('pos.hold'));
        cart.clear();
    } catch (e) {
        ui.error('Failed to hold');
    }
}

// Build a receipt object locally for offline sales (server assigns the real
// invoice number later during sync).
function buildLocalSale(payload, paidAmount) {
    const items = cart.items.map((i) => {
        const base = i.unit_price * i.quantity - (i.discount || 0);
        const tax = (base * (i.tax_rate || 0)) / 100;
        return { id: i.key, name: i.name, quantity: i.quantity, subtotal: base + tax };
    });
    return {
        offline: true,
        client_uuid: payload.client_uuid,
        invoice_no: 'OFFLINE-' + payload.client_uuid.slice(0, 8),
        sold_at: new Date().toISOString(),
        items,
        subtotal: cart.subtotal,
        discount: cart.discount,
        tax: cart.taxTotal,
        total: cart.total,
        paid: paidAmount,
        change: Math.max(0, paidAmount - cart.total),
    };
}

async function completeOffline(payload, paidAmount) {
    await offline.queue(payload);
    lastSale.value = buildLocalSale(payload, paidAmount);
    showPayment.value = false;
    cart.clear();
    ui.success(t('offline.sale_queued'));
    showReceipt.value = true;
    focusSearch();
}

async function onPaid(paidAmount) {
    const payload = cart.toPayload(paidAmount, 'completed');

    // Offline: queue immediately, print from local data.
    if (!ui.online) {
        await completeOffline(payload, paidAmount);
        return;
    }

    try {
        const { data } = await api.post('/sales', payload);
        lastSale.value = data.data;
        showPayment.value = false;
        cart.clear();
        ui.success(t('pos.sale_complete'));
        showReceipt.value = true;
        focusSearch();
    } catch (e) {
        // No server response → treat as connectivity loss and queue offline.
        if (!e.response) {
            await completeOffline(payload, paidAmount);
        } else {
            ui.error(e.response?.data?.message || 'Checkout failed');
        }
    }
}

let customerDebounce;
function searchCustomers() {
    clearTimeout(customerDebounce);
    customerDebounce = setTimeout(async () => {
        if (!customerQuery.value.trim()) {
            customerResults.value = [];
            return;
        }
        try {
            const { data } = await api.get('/customers', { params: { search: customerQuery.value, per_page: 8 } });
            customerResults.value = data.data.data || data.data;
        } catch (e) {
            customerResults.value = [];
        }
    }, 250);
}

function pickCustomer(c) {
    cart.customer = c;
    cart.pointsRedeem = 0;
    customerQuery.value = '';
    customerResults.value = [];
}

onMounted(async () => {
    focusSearch();
    if (auth.hasCapability('multi_branch')) {
        try {
            const { data } = await api.get('/branches');
            branches.value = (data.data || []).filter((b) => b.is_active !== false);
            if (!cart.branchId && branches.value.length) {
                const main = branches.value.find((b) => b.is_main) || branches.value[0];
                cart.setBranch(main.id);
            }
        } catch (e) {
            branches.value = [];
        }
    }
});
</script>

<template>
    <div class="grid h-full grid-cols-1 gap-4 lg:grid-cols-[1fr_380px]">
        <!-- Left: search + results -->
        <div class="flex flex-col gap-4">
            <div class="flex gap-2">
                <input
                    ref="searchBox"
                    v-model="query"
                    class="input flex-1 py-3 text-base"
                    :placeholder="t('pos.scan_or_search')"
                    @keyup.enter="onEnter"
                />
                <button class="btn-primary shrink-0 px-4" @click="showScanner = true" :title="t('pos.scan_camera')">📷</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading" class="py-10 text-center text-slate-400">{{ t('common.loading') }}</div>
                <EmptyState
                    v-else-if="!results.length"
                    icon="🔍"
                    :title="t('pos.scan_or_search')"
                    :subtitle="t('pos.empty_cart')"
                />
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <button
                        v-for="p in results"
                        :key="p.id"
                        class="card flex flex-col items-start gap-1 p-3 text-left transition hover:ring-2 hover:ring-brand-500"
                        @click="addProduct(p)"
                    >
                        <img
                            v-if="p.image"
                            :src="p.image"
                            :alt="p.name"
                            class="h-24 w-full rounded-lg bg-slate-100 object-cover dark:bg-slate-800"
                            @error="p.image = null"
                        />
                        <div
                            v-else
                            class="flex h-24 w-full items-center justify-center rounded-lg bg-slate-100 text-2xl dark:bg-slate-800"
                        >
                            📦
                        </div>
                        <span class="line-clamp-2 text-sm font-semibold">{{ p.name }}</span>
                        <span class="text-brand-600 font-bold">{{ currency }}{{ p.selling_price }}</span>
                        <span v-if="p.track_stock" class="text-xs text-slate-400">{{ t('common.stock') }}: {{ p.stock_quantity }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: cart (desktop) -->
        <div class="hidden flex-col lg:flex">
            <div class="card flex h-full flex-col">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-bold">🛒 {{ t('pos.cart') }}</h2>
                    <button v-if="cart.items.length" class="btn-ghost text-sm text-red-500" @click="cart.clear()">{{ t('pos.clear') }}</button>
                </div>
                <div v-if="showBranches" class="mb-2">
                    <select :value="cart.branchId" class="input h-9 py-0 text-sm" @change="cart.setBranch(Number($event.target.value) || null)">
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div class="relative mb-3">
                    <div v-if="cart.customer" class="flex items-center justify-between rounded-xl bg-brand-50 px-3 py-2 text-sm dark:bg-brand-900/30">
                        <span>👤 {{ cart.customer.name }} <span v-if="cart.customer.due_balance > 0" class="text-amber-600">({{ t('pos.due') }} {{ currency }}{{ Number(cart.customer.due_balance).toFixed(0) }})</span></span>
                        <button class="text-slate-400" @click="cart.customer = null; cart.pointsRedeem = 0">✕</button>
                    </div>
                    <input v-else v-model="customerQuery" class="input h-9 text-sm" :placeholder="t('pos.walk_in')" @input="searchCustomers" />
                    <div v-if="customerResults.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <button v-for="c in customerResults" :key="c.id" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700" @click="pickCustomer(c)">
                            {{ c.name }} <span class="text-slate-400">{{ c.phone }}</span>
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <EmptyState v-if="!cart.items.length" icon="🧺" :title="t('pos.cart')" :subtitle="t('pos.empty_cart')" />
                    <div v-else class="space-y-2">
                        <div v-for="item in cart.items" :key="item.key" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold">{{ item.name }}</span>
                                <button class="text-slate-400 hover:text-red-500" @click="cart.remove(item.key)">✕</button>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button class="btn-secondary h-8 w-8 p-0" @click="cart.decrement(item.key)">−</button>
                                    <input
                                        :value="item.quantity"
                                        type="number"
                                        class="input h-8 w-14 px-2 py-0 text-center"
                                        @change="cart.setQuantity(item.key, $event.target.value)"
                                    />
                                    <button class="btn-secondary h-8 w-8 p-0" @click="cart.increment(item.key)">+</button>
                                </div>
                                <span class="font-bold">{{ currency }}{{ (item.unit_price * item.quantity).toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totals + actions -->
                <div class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <div class="flex justify-between text-sm text-slate-500">
                        <span>{{ t('pos.subtotal') }}</span><span>{{ currency }}{{ cart.subtotal.toFixed(2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <span>{{ t('pos.discount') }}</span>
                        <input v-model.number="cart.discount" type="number" class="input h-8 w-24 px-2 py-0 text-right" />
                    </div>
                    <div v-if="cart.taxTotal > 0" class="flex justify-between text-sm text-slate-500">
                        <span>{{ t('pos.tax') }}</span><span>{{ currency }}{{ cart.taxTotal.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-xl font-bold">
                        <span>{{ t('pos.grand_total') }}</span><span class="text-brand-600">{{ currency }}{{ cart.total.toFixed(2) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button class="btn-secondary py-3" :disabled="!cart.items.length" @click="hold">⏸ {{ t('pos.hold') }}</button>
                        <button class="btn-primary py-3" :disabled="!cart.items.length" @click="showPayment = true">💰 {{ t('pos.pay') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile sticky cart bar -->
        <div v-if="cart.items.length" class="fixed inset-x-0 bottom-14 z-40 px-4 lg:hidden">
            <button class="btn-primary flex w-full items-center justify-between py-3 text-base shadow-lg" @click="showCartSheet = true">
                <span>🛒 {{ cart.count }} {{ t('pos.cart') }}</span>
                <span>{{ currency }}{{ cart.total.toFixed(2) }}</span>
            </button>
        </div>

        <!-- Mobile cart sheet -->
        <BaseModal :show="showCartSheet" :title="t('pos.cart')" @close="showCartSheet = false">
            <div class="space-y-2">
                <div v-for="item in cart.items" :key="item.key" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold">{{ item.name }}</span>
                        <button class="text-slate-400" @click="cart.remove(item.key)">✕</button>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button class="btn-secondary h-8 w-8 p-0" @click="cart.decrement(item.key)">−</button>
                            <span class="w-8 text-center">{{ item.quantity }}</span>
                            <button class="btn-secondary h-8 w-8 p-0" @click="cart.increment(item.key)">+</button>
                        </div>
                        <span class="font-bold">{{ currency }}{{ (item.unit_price * item.quantity).toFixed(2) }}</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex w-full items-center justify-between">
                    <span class="text-lg font-bold">{{ currency }}{{ cart.total.toFixed(2) }}</span>
                    <button class="btn-primary" @click="showCartSheet = false; showPayment = true">💰 {{ t('pos.pay') }}</button>
                </div>
            </template>
        </BaseModal>

        <!-- Camera scanner -->
        <BaseModal :show="showScanner" :title="t('pos.scan_camera')" @close="showScanner = false">
            <BarcodeScanner v-if="showScanner" @detected="onScannerDetected" />
        </BaseModal>

        <!-- Variant picker -->
        <VariantPicker
            v-if="variantProduct"
            :product="variantProduct"
            @pick="onVariantPicked"
            @close="variantProduct = null"
        />

        <!-- Payment -->
        <PaymentModal :show="showPayment" :total="cart.total" @paid="onPaid" @close="showPayment = false" />

        <!-- Receipt -->
        <ReceiptModal :show="showReceipt" :sale="lastSale" @close="showReceipt = false" />
    </div>
</template>
