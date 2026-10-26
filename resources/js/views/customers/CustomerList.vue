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

const customers = ref([]);
const loading = ref(true);
const search = ref('');
const showForm = ref(false);
const editing = ref(null);
const form = ref({ name: '', phone: '', email: '', address: '' });
const payFor = ref(null);
const payForm = ref({ amount: 0, method: 'cash' });
const ledger = ref(null);

let debounce;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(load, 250);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/customers', { params: { search: search.value, per_page: 50 } });
        customers.value = data.data.data || data.data;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    form.value = { name: '', phone: '', email: '', address: '' };
    showForm.value = true;
}
function edit(c) {
    editing.value = c;
    form.value = { name: c.name, phone: c.phone, email: c.email, address: c.address };
    showForm.value = true;
}
async function save() {
    try {
        if (editing.value) await api.put(`/customers/${editing.value.id}`, form.value);
        else await api.post('/customers', form.value);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error('Failed');
    }
}
async function remove(c) {
    if (!confirm(`${t('common.delete')}: ${c.name}?`)) return;
    await api.delete(`/customers/${c.id}`);
    load();
}
function openPay(c) {
    payFor.value = c;
    payForm.value = { amount: Number(c.due_balance) || 0, method: 'cash' };
}
async function collect() {
    try {
        await api.post(`/customers/${payFor.value.id}/collect-due`, payForm.value);
        ui.success(t('common.save'));
        payFor.value = null;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}
async function openLedger(c) {
    const { data } = await api.get(`/customers/${c.id}/ledger`);
    ledger.value = data.data;
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('nav.customers') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('common.add') }}</button>
        </div>
        <input v-model="search" class="input mb-4" :placeholder="t('common.search')" @input="onSearch" />

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!customers.length" icon="👥" :title="t('common.no_data')">
            <button class="btn-primary" @click="create">+ {{ t('common.add') }}</button>
        </EmptyState>
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('common.name') }}</th>
                        <th>{{ t('common.phone') }}</th>
                        <th class="text-right">{{ t('pos.due') }}</th>
                        <th class="text-right">{{ t('loyalty.points') }}</th>
                        <th class="p-3 text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in customers" :key="c.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ c.name }}</td>
                        <td class="text-slate-500">{{ c.phone || '—' }}</td>
                        <td class="text-right font-semibold" :class="Number(c.due_balance) > 0 ? 'text-amber-600' : ''">
                            {{ currency }}{{ Number(c.due_balance).toFixed(2) }}
                        </td>
                        <td class="text-right">{{ Number(c.loyalty_points || 0) }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button class="btn-ghost h-8 px-2" @click="openLedger(c)">📋</button>
                            <button v-if="Number(c.due_balance) > 0" class="btn-ghost h-8 px-2 text-emerald-600" @click="openPay(c)">💵</button>
                            <button class="btn-ghost h-8 px-2" @click="edit(c)">✏️</button>
                            <button class="btn-ghost h-8 px-2 text-red-500" @click="remove(c)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="showForm" :title="editing ? t('common.edit') : t('common.add')" @close="showForm = false">
            <div class="space-y-3">
                <div><label class="label">{{ t('common.name') }}</label><input v-model="form.name" class="input" /></div>
                <div><label class="label">{{ t('common.phone') }}</label><input v-model="form.phone" class="input" /></div>
                <div><label class="label">{{ t('common.email') }}</label><input v-model="form.email" class="input" /></div>
                <div><label class="label">{{ t('common.address') }}</label><textarea v-model="form.address" class="input" rows="2"></textarea></div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="save">{{ t('common.save') }}</button>
            </template>
        </BaseModal>

        <BaseModal :show="!!payFor" :title="t('loyalty.collect_due')" @close="payFor = null">
            <div v-if="payFor" class="space-y-3">
                <div class="text-sm text-slate-500">{{ payFor.name }} — {{ t('pos.due') }}: {{ currency }}{{ Number(payFor.due_balance).toFixed(2) }}</div>
                <div><label class="label">{{ t('supplier.amount') }}</label><input v-model.number="payForm.amount" type="number" class="input" /></div>
                <div>
                    <label class="label">{{ t('pos.payment_method') }}</label>
                    <select v-model="payForm.method" class="input">
                        <option value="cash">{{ t('pos.cash') }}</option>
                        <option value="card">{{ t('pos.card') }}</option>
                        <option value="mobile">{{ t('pos.mobile') }}</option>
                    </select>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="payFor = null">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="collect">{{ t('loyalty.collect') }}</button>
            </template>
        </BaseModal>

        <BaseModal :show="!!ledger" :title="t('loyalty.ledger')" size="lg" @close="ledger = null">
            <div v-if="ledger" class="space-y-4 text-sm">
                <div class="flex justify-between rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <span>{{ ledger.customer.name }}</span>
                    <span>{{ t('pos.due') }} {{ currency }}{{ Number(ledger.customer.due_balance).toFixed(2) }} · {{ t('loyalty.points') }} {{ ledger.customer.loyalty_points }}</span>
                </div>
                <div>
                    <h4 class="mb-2 font-semibold">{{ t('nav.sales') }}</h4>
                    <div v-for="s in ledger.sales" :key="s.id" class="flex justify-between py-1">
                        <span>{{ s.invoice_no }}</span>
                        <span>{{ currency }}{{ Number(s.total).toFixed(2) }}</span>
                    </div>
                </div>
                <div>
                    <h4 class="mb-2 font-semibold">{{ t('loyalty.collect') }}</h4>
                    <div v-if="!ledger.payments.length" class="text-slate-400">{{ t('common.no_data') }}</div>
                    <div v-for="p in ledger.payments" :key="p.id" class="flex justify-between py-1">
                        <span>{{ new Date(p.paid_at).toLocaleDateString() }} · {{ p.method }}</span>
                        <span>{{ currency }}{{ Number(p.amount).toFixed(2) }}</span>
                    </div>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
