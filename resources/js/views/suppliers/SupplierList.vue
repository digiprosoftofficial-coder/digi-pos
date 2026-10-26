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

const suppliers = ref([]);
const loading = ref(true);
const search = ref('');
const withDue = ref(false);
const showForm = ref(false);
const editing = ref(null);
const form = ref({ name: '', company: '', phone: '', email: '', address: '' });
const payFor = ref(null);
const payForm = ref({ amount: 0, method: 'cash', note: '' });

let debounce;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(load, 250);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/suppliers', {
            params: { search: search.value, with_due: withDue.value ? 1 : 0, per_page: 50 },
        });
        suppliers.value = data.data.data || data.data;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    form.value = { name: '', company: '', phone: '', email: '', address: '' };
    showForm.value = true;
}
function edit(s) {
    editing.value = s;
    form.value = { name: s.name, company: s.company, phone: s.phone, email: s.email, address: s.address };
    showForm.value = true;
}
async function save() {
    try {
        if (editing.value) await api.put(`/suppliers/${editing.value.id}`, form.value);
        else await api.post('/suppliers', form.value);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error('Failed');
    }
}
async function remove(s) {
    if (!confirm(`${t('common.delete')}: ${s.name}?`)) return;
    await api.delete(`/suppliers/${s.id}`);
    load();
}
function openPay(s) {
    payFor.value = s;
    payForm.value = { amount: Number(s.due_balance) || 0, method: 'cash', note: '' };
}
async function pay() {
    try {
        await api.post(`/suppliers/${payFor.value.id}/pay`, payForm.value);
        ui.success(t('common.save'));
        payFor.value = null;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('supplier.title') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('supplier.new') }}</button>
        </div>
        <div class="mb-4 flex items-center gap-3">
            <input v-model="search" class="input flex-1" :placeholder="t('common.search')" @input="onSearch" />
            <label class="flex items-center gap-2 text-sm whitespace-nowrap">
                <input v-model="withDue" type="checkbox" @change="load" /> {{ t('supplier.with_due') }}
            </label>
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!suppliers.length" icon="🚚" :title="t('common.no_data')">
            <button class="btn-primary" @click="create">+ {{ t('supplier.new') }}</button>
        </EmptyState>
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('common.name') }}</th>
                        <th>{{ t('supplier.company') }}</th>
                        <th>{{ t('common.phone') }}</th>
                        <th class="text-right">{{ t('supplier.due') }}</th>
                        <th class="p-3 text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in suppliers" :key="s.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ s.name }}</td>
                        <td class="text-slate-500">{{ s.company || '—' }}</td>
                        <td class="text-slate-500">{{ s.phone || '—' }}</td>
                        <td class="text-right font-semibold" :class="Number(s.due_balance) > 0 ? 'text-red-500' : ''">
                            {{ currency }}{{ Number(s.due_balance).toFixed(2) }}
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button v-if="Number(s.due_balance) > 0" class="btn-ghost h-8 px-2 text-emerald-600" @click="openPay(s)">💵 {{ t('supplier.pay') }}</button>
                            <button class="btn-ghost h-8 px-2" @click="edit(s)">✏️</button>
                            <button class="btn-ghost h-8 px-2 text-red-500" @click="remove(s)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="showForm" :title="editing ? t('supplier.edit') : t('supplier.new')" @close="showForm = false">
            <div class="space-y-3">
                <div><label class="label">{{ t('common.name') }}</label><input v-model="form.name" class="input" /></div>
                <div><label class="label">{{ t('supplier.company') }}</label><input v-model="form.company" class="input" /></div>
                <div><label class="label">{{ t('common.phone') }}</label><input v-model="form.phone" class="input" /></div>
                <div><label class="label">{{ t('common.email') }}</label><input v-model="form.email" class="input" /></div>
                <div><label class="label">{{ t('common.address') }}</label><textarea v-model="form.address" class="input" rows="2"></textarea></div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="save">{{ t('common.save') }}</button>
            </template>
        </BaseModal>

        <BaseModal :show="!!payFor" :title="t('supplier.pay_title')" @close="payFor = null">
            <div v-if="payFor" class="space-y-3">
                <div class="text-sm text-slate-500">{{ payFor.name }} — {{ t('supplier.due') }}: {{ currency }}{{ Number(payFor.due_balance).toFixed(2) }}</div>
                <div><label class="label">{{ t('supplier.amount') }}</label><input v-model.number="payForm.amount" type="number" class="input" /></div>
                <div>
                    <label class="label">{{ t('pos.payment_method') }}</label>
                    <select v-model="payForm.method" class="input">
                        <option value="cash">{{ t('pos.cash') }}</option>
                        <option value="card">{{ t('pos.card') }}</option>
                        <option value="mobile">{{ t('pos.mobile') }}</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="payFor = null">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="pay">{{ t('supplier.pay') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
