<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const currency = computed(() => auth.currency);

const query = ref('');
const results = ref([]);
const labels = ref([]);
const barcodeType = ref('code128');
const showPrice = ref(true);
const showName = ref(true);

let debounce;
function searchProducts() {
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        if (!query.value.trim()) {
            results.value = [];
            return;
        }
        const { data } = await api.get('/lookup/search', { params: { q: query.value } });
        results.value = data.data;
    }, 250);
}

function add(product) {
    const code = product.barcode || product.sku;
    if (!code) {
        ui.error(t('common.barcode'));
        return;
    }
    const existing = labels.value.find((l) => l.id === product.id && !l.variant_id);
    if (existing) {
        existing.qty += 1;
        query.value = '';
        results.value = [];
        return;
    }
    labels.value.push({
        id: product.id,
        variant_id: null,
        name: product.name,
        code,
        price: Number(product.selling_price),
        qty: 1,
        svg: '',
    });
    fetchSvg(labels.value[labels.value.length - 1]);
    query.value = '';
    results.value = [];
}

async function fetchSvg(label) {
    try {
        const { data } = await api.get('/barcode/svg', {
            params: { code: label.code, type: barcodeType.value, h: 40, w: 2 },
            responseType: 'text',
            headers: { Accept: 'image/svg+xml' },
        });
        label.svg = data;
    } catch (e) {
        ui.error('Barcode failed');
    }
}

async function refreshSvgs() {
    await Promise.all(labels.value.map(fetchSvg));
}

const sheets = computed(() => {
    const out = [];
    for (const label of labels.value) {
        for (let i = 0; i < Number(label.qty) || 0; i++) {
            out.push(label);
        }
    }
    return out;
});

function printLabels() {
    if (!sheets.value.length) return;
    window.print();
}
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h1 class="text-2xl font-bold">{{ t('barcode.title') }}</h1>
            <button class="btn-primary" :disabled="!sheets.length" @click="printLabels">🖨️ {{ t('barcode.print') }}</button>
        </div>

        <div class="mb-4 grid gap-3 print:hidden sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative sm:col-span-2">
                <label class="label">{{ t('barcode.select') }}</label>
                <input v-model="query" class="input" :placeholder="t('purchase.search_product')" @input="searchProducts" />
                <div v-if="results.length" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                    <button v-for="p in results" :key="p.id" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700" @click="add(p)">
                        <span>{{ p.name }}</span>
                        <span class="text-slate-400">{{ p.barcode || p.sku || '—' }}</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="label">{{ t('barcode.type') }}</label>
                <select v-model="barcodeType" class="input" @change="refreshSvgs">
                    <option value="code128">Code 128</option>
                    <option value="code39">Code 39</option>
                    <option value="ean13">EAN-13</option>
                    <option value="ean8">EAN-8</option>
                </select>
            </div>
            <div class="flex items-end gap-4 pb-2">
                <label class="flex items-center gap-2 text-sm"><input v-model="showName" type="checkbox" /> {{ t('barcode.show_name') }}</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="showPrice" type="checkbox" /> {{ t('barcode.show_price') }}</label>
            </div>
        </div>

        <div v-if="labels.length" class="mb-4 space-y-2 print:hidden">
            <div v-for="(l, idx) in labels" :key="l.id + '-' + idx" class="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <span class="flex-1 truncate font-medium">{{ l.name }} · {{ l.code }}</span>
                <input v-model.number="l.qty" type="number" min="1" class="input h-9 w-20 px-2" />
                <button class="text-slate-400 hover:text-red-500" @click="labels.splice(idx, 1)">✕</button>
            </div>
        </div>

        <EmptyState v-if="!sheets.length" icon="🏷️" :title="t('barcode.empty')" class="print:hidden" />

        <div id="barcode-sheet" class="print-sheet grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div v-for="(l, i) in sheets" :key="i" class="flex flex-col items-center rounded-lg border border-slate-200 bg-white p-3 text-center dark:border-slate-700 dark:bg-white">
                <div v-if="showName" class="mb-1 text-xs font-semibold text-black">{{ l.name }}</div>
                <div class="flex h-12 w-full items-center justify-center text-black" v-html="l.svg"></div>
                <div class="mt-1 text-[10px] tracking-widest text-black">{{ l.code }}</div>
                <div v-if="showPrice" class="text-sm font-bold text-black">{{ currency }}{{ Number(l.price).toFixed(2) }}</div>
            </div>
        </div>
    </div>
</template>
