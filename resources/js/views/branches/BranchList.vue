<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const ui = useUiStore();

const branches = ref([]);
const loading = ref(true);
const showForm = ref(false);
const editing = ref(null);
const form = ref({ name: '', phone: '', address: '', is_main: false, is_active: true });

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/branches');
        branches.value = data.data;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    form.value = { name: '', phone: '', address: '', is_main: false, is_active: true };
    showForm.value = true;
}
function edit(b) {
    editing.value = b;
    form.value = { name: b.name, phone: b.phone, address: b.address, is_main: !!b.is_main, is_active: b.is_active !== false };
    showForm.value = true;
}
async function save() {
    try {
        if (editing.value) await api.put(`/branches/${editing.value.id}`, form.value);
        else await api.post('/branches', form.value);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}
async function remove(b) {
    if (!confirm(`${t('common.delete')}: ${b.name}?`)) return;
    try {
        await api.delete(`/branches/${b.id}`);
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
            <h1 class="text-2xl font-bold">{{ t('branch.title') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('branch.new') }}</button>
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!branches.length" icon="🏪" :title="t('common.no_data')">
            <button class="btn-primary" @click="create">+ {{ t('branch.new') }}</button>
        </EmptyState>
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="b in branches" :key="b.id" class="card space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ b.name }}</div>
                        <div class="text-sm text-slate-500">{{ b.phone || b.address || '—' }}</div>
                    </div>
                    <span v-if="b.is_main" class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/40 dark:text-brand-200">{{ t('branch.main') }}</span>
                </div>
                <div class="flex justify-end gap-1">
                    <button class="btn-ghost h-8 px-2" @click="edit(b)">✏️</button>
                    <button class="btn-ghost h-8 px-2 text-red-500" @click="remove(b)">🗑</button>
                </div>
            </div>
        </div>

        <BaseModal :show="showForm" :title="editing ? t('branch.edit') : t('branch.new')" @close="showForm = false">
            <div class="space-y-3">
                <div><label class="label">{{ t('common.name') }}</label><input v-model="form.name" class="input" /></div>
                <div><label class="label">{{ t('common.phone') }}</label><input v-model="form.phone" class="input" /></div>
                <div><label class="label">{{ t('common.address') }}</label><textarea v-model="form.address" class="input" rows="2"></textarea></div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_main" type="checkbox" /> {{ t('branch.make_main') }}
                </label>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="save">{{ t('common.save') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
