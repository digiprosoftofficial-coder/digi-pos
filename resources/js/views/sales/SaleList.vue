<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();
const currency = computed(() => auth.currency);

const sales = ref([]);
const loading = ref(true);
const search = ref('');
const detail = ref(null);

let debounce;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(load, 300);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/sales', { params: { search: search.value, per_page: 50 } });
        sales.value = data.data.data || data.data;
    } finally {
        loading.value = false;
    }
}

async function open(s) {
    const { data } = await api.get(`/sales/${s.id}`);
    detail.value = data.data;
}

async function downloadPdf(s) {
    const { data } = await api.get(`/sales/${s.id}/receipt`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `invoice-${s.invoice_no}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

onMounted(load);
</script>

<template>
    <div>
        <h1 class="mb-4 text-2xl font-bold">{{ t('nav.sales') }}</h1>
        <input v-model="search" class="input mb-4" :placeholder="t('common.search') + ' (invoice)'" @input="onSearch" />

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!sales.length" icon="🧾" :title="t('common.no_data')" />
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">Invoice</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-right">{{ t('common.total') }}</th>
                        <th class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sales" :key="s.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ s.invoice_no }}</td>
                        <td class="text-slate-500">{{ new Date(s.sold_at).toLocaleDateString() }}</td>
                        <td>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="s.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ s.payment_status }}</span>
                        </td>
                        <td class="text-right font-semibold">{{ currency }}{{ Number(s.total).toFixed(2) }}</td>
                        <td class="p-3 text-right">
                            <button class="btn-ghost h-8 px-2" @click="open(s)">👁</button>
                            <button class="btn-ghost h-8 px-2" @click="downloadPdf(s)">⬇️</button>
                            <button
                                v-if="auth.hasCapability('returns') && s.status !== 'refunded'"
                                class="btn-ghost h-8 px-2"
                                @click="router.push({ name: 'returns', query: { invoice: s.invoice_no } })"
                            >↩️</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="!!detail" :title="detail?.invoice_no" @close="detail = null">
            <div v-if="detail" class="space-y-2">
                <div v-for="it in detail.items" :key="it.id" class="flex justify-between text-sm">
                    <span>{{ it.name }} × {{ Number(it.quantity) }}</span>
                    <span>{{ currency }}{{ Number(it.subtotal).toFixed(2) }}</span>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-2 text-sm dark:border-slate-800">
                    <div class="flex justify-between"><span>{{ t('pos.subtotal') }}</span><span>{{ Number(detail.subtotal).toFixed(2) }}</span></div>
                    <div class="flex justify-between"><span>{{ t('pos.discount') }}</span><span>-{{ Number(detail.discount).toFixed(2) }}</span></div>
                    <div class="flex justify-between text-lg font-bold"><span>{{ t('common.total') }}</span><span>{{ currency }}{{ Number(detail.total).toFixed(2) }}</span></div>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
