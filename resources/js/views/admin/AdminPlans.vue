<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const ui = useUiStore();

const plans = ref([]);
const allCaps = ref([]);
const loading = ref(true);
const trialDays = ref(7);
const savingTrial = ref(false);
const showForm = ref(false);
const editing = ref(null);

const blank = () => ({
    name: '',
    price: 0,
    billing_period: 'monthly',
    max_users: null,
    max_products: null,
    max_branches: 1,
    features: [],
    is_active: true,
});
const form = ref(blank());

async function load() {
    loading.value = true;
    try {
        const [p, c, s] = await Promise.all([
            api.get('/admin/plans'),
            api.get('/admin/plans/capabilities'),
            api.get('/admin/settings'),
        ]);
        plans.value = p.data.data;
        allCaps.value = c.data.data;
        trialDays.value = s.data.data.trial_days;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    form.value = blank();
    showForm.value = true;
}
function edit(p) {
    editing.value = p;
    form.value = { ...blank(), ...p, features: [...(p.features || [])] };
    showForm.value = true;
}
function toggleFeature(key) {
    const i = form.value.features.indexOf(key);
    if (i >= 0) form.value.features.splice(i, 1);
    else form.value.features.push(key);
}
async function save() {
    try {
        if (editing.value) await api.put(`/admin/plans/${editing.value.id}`, form.value);
        else await api.post('/admin/plans', form.value);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error('Failed');
    }
}
async function remove(p) {
    if (!confirm(`${t('common.delete')}: ${p.name}?`)) return;
    await api.delete(`/admin/plans/${p.id}`);
    load();
}

async function saveTrialDays() {
    savingTrial.value = true;
    try {
        const { data } = await api.put('/admin/settings', { trial_days: Number(trialDays.value) });
        trialDays.value = data.data.trial_days;
        ui.success(t('common.save'));
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        savingTrial.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('admin.plans') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('admin.new_plan') }}</button>
        </div>

        <div class="card mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-lg">
                <label class="label">{{ t('admin.trial_days') }}</label>
                <p class="mb-2 text-sm text-slate-500">{{ t('admin.trial_days_hint') }}</p>
                <input v-model.number="trialDays" type="number" min="1" max="365" class="input max-w-32" />
            </div>
            <button class="btn-primary" :disabled="savingTrial" @click="saveTrialDays">{{ t('common.save') }}</button>
        </div>

        <Spinner v-if="loading" />
        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div v-for="p in plans" :key="p.id" class="card">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-lg font-bold">{{ p.name }}</div>
                        <div class="text-2xl font-bold text-brand-600">৳{{ p.price }}<span class="text-sm font-normal text-slate-400">/{{ p.billing_period }}</span></div>
                    </div>
                    <span v-if="!p.is_active" class="rounded-full bg-slate-200 px-2 py-0.5 text-xs dark:bg-slate-700">off</span>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-slate-500">
                    <li v-for="f in p.features" :key="f">✓ {{ (allCaps.find((c) => c.key === f) || {}).label || f }}</li>
                </ul>
                <div class="mt-4 flex gap-2">
                    <button class="btn-secondary flex-1 py-1.5 text-sm" @click="edit(p)">✏️ {{ t('common.edit') }}</button>
                    <button class="btn-ghost py-1.5 text-sm text-red-500" @click="remove(p)">🗑</button>
                </div>
            </div>
        </div>

        <BaseModal :show="showForm" :title="editing ? t('admin.edit_plan') : t('admin.new_plan')" size="lg" @close="showForm = false">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ t('common.name') }}</label><input v-model="form.name" class="input" /></div>
                    <div><label class="label">{{ t('admin.price') }}</label><input v-model.number="form.price" type="number" class="input" /></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label">{{ t('admin.billing') }}</label>
                        <select v-model="form.billing_period" class="input">
                            <option value="monthly">monthly</option>
                            <option value="yearly">yearly</option>
                            <option value="lifetime">lifetime</option>
                        </select>
                    </div>
                    <div><label class="label">Max users</label><input v-model.number="form.max_users" type="number" class="input" placeholder="∞" /></div>
                    <div><label class="label">Max products</label><input v-model.number="form.max_products" type="number" class="input" placeholder="∞" /></div>
                </div>
                <div>
                    <label class="label">{{ t('admin.features') }}</label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="c in allCaps"
                            :key="c.key"
                            class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800"
                        >
                            <input type="checkbox" :checked="form.features.includes(c.key)" class="h-4 w-4" @change="toggleFeature(c.key)" />
                            {{ c.label }}
                        </label>
                    </div>
                </div>
                <label class="flex items-center gap-2"><input v-model="form.is_active" type="checkbox" class="h-5 w-5" /> {{ t('common.active') }}</label>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="save">{{ t('common.save') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
