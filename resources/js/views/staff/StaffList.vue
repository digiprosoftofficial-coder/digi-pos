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

const users = ref([]);
const roles = ref([]);
const loading = ref(true);
const showForm = ref(false);
const editing = ref(null);
const form = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'cashier', is_active: true });

async function load() {
    loading.value = true;
    try {
        const [u, r] = await Promise.all([api.get('/users'), api.get('/roles')]);
        users.value = u.data.data;
        roles.value = r.data.data;
    } finally {
        loading.value = false;
    }
}

function create() {
    editing.value = null;
    form.value = { name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'cashier', is_active: true };
    showForm.value = true;
}
function edit(u) {
    editing.value = u;
    form.value = { name: u.name, email: u.email, phone: u.phone, password: '', password_confirmation: '', role: u.roles[0] || 'cashier', is_active: u.is_active };
    showForm.value = true;
}
async function save() {
    try {
        if (editing.value) await api.put(`/users/${editing.value.id}`, form.value);
        else await api.post('/users', form.value);
        ui.success(t('common.save'));
        showForm.value = false;
        load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}
async function remove(u) {
    if (!confirm(`${t('common.delete')}: ${u.name}?`)) return;
    try {
        await api.delete(`/users/${u.id}`);
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
            <h1 class="text-2xl font-bold">{{ t('staff.title') }}</h1>
            <button class="btn-primary" @click="create">+ {{ t('staff.new') }}</button>
        </div>

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!users.length" icon="👔" :title="t('common.no_data')" />
        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                    <tr>
                        <th class="p-3">{{ t('common.name') }}</th>
                        <th>{{ t('common.email') }}</th>
                        <th>{{ t('staff.role') }}</th>
                        <th class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" class="border-b border-slate-50 dark:border-slate-800/50">
                        <td class="p-3 font-medium">{{ u.name }}</td>
                        <td class="text-slate-500">{{ u.email }}</td>
                        <td><span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-700">{{ t('staff.roles.' + (u.roles[0] || 'cashier')) }}</span></td>
                        <td class="p-3 text-right">
                            <button class="btn-ghost h-8 px-2" @click="edit(u)">✏️</button>
                            <button v-if="!u.roles.includes('owner')" class="btn-ghost h-8 px-2 text-red-500" @click="remove(u)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BaseModal :show="showForm" :title="editing ? t('common.edit') : t('staff.new')" @close="showForm = false">
            <div class="space-y-3">
                <div><label class="label">{{ t('common.name') }}</label><input v-model="form.name" class="input" /></div>
                <div><label class="label">{{ t('common.email') }}</label><input v-model="form.email" type="email" class="input" /></div>
                <div><label class="label">{{ t('common.phone') }}</label><input v-model="form.phone" class="input" /></div>
                <div>
                    <label class="label">{{ t('staff.role') }}</label>
                    <select v-model="form.role" class="input">
                        <option v-for="r in roles" :key="r" :value="r">{{ t('staff.roles.' + r) }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ t('auth.password') }}</label><input v-model="form.password" type="password" class="input" :placeholder="editing ? '••••' : ''" /></div>
                    <div><label class="label">{{ t('auth.confirm_password') }}</label><input v-model="form.password_confirmation" type="password" class="input" /></div>
                </div>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="showForm = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" @click="save">{{ t('common.save') }}</button>
            </template>
        </BaseModal>
    </div>
</template>
