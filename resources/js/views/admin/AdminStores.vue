<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();

const stores = ref([]);
const loading = ref(true);
const search = ref('');

const capModal = ref(false);
const capStore = ref(null);
const caps = ref([]);
const savingCaps = ref(false);

const planModal = ref(false);
const planStore = ref(null);
const plans = ref([]);
const selectedPlan = ref(null);
const savingPlan = ref(false);

let debounce;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(load, 250);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/admin/stores', { params: { search: search.value, per_page: 50 } });
        stores.value = data.data.data || data.data;
    } finally {
        loading.value = false;
    }
}

async function setStatus(store, status) {
    await api.patch(`/admin/stores/${store.id}/status`, { status });
    ui.success(t('common.save'));
    load();
}

// Capabilities
async function openCaps(store) {
    capStore.value = store;
    capModal.value = true;
    const { data } = await api.get(`/admin/stores/${store.id}/capabilities`);
    caps.value = data.data;
}
async function saveCaps() {
    savingCaps.value = true;
    try {
        await api.put(`/admin/stores/${capStore.value.id}/capabilities`, {
            capabilities: caps.value.map((c) => ({ key: c.key, enabled: c.enabled })),
        });
        ui.success(t('common.save'));
        capModal.value = false;
    } catch (e) {
        ui.error('Failed');
    } finally {
        savingCaps.value = false;
    }
}

// Plan assignment
function currentPlanId(store) {
    return store?.current_subscription?.subscription_plan_id ?? null;
}
function selectPlan(id) {
    selectedPlan.value = Number(id);
}
async function openPlan(store) {
    planStore.value = store;
    planModal.value = true;
    selectedPlan.value = currentPlanId(store);
    try {
        const { data } = await api.get('/admin/plans');
        plans.value = Array.isArray(data.data) ? data.data : [];
        selectedPlan.value = currentPlanId(store) ?? plans.value[0]?.id ?? null;
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}
async function assignPlan() {
    if (!planStore.value || !selectedPlan.value || savingPlan.value) {
        return;
    }
    savingPlan.value = true;
    try {
        await api.post(`/admin/stores/${planStore.value.id}/plan`, {
            subscription_plan_id: Number(selectedPlan.value),
        });
        ui.success(t('common.save'));
        planModal.value = false;
        await load();
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        savingPlan.value = false;
    }
}

// Impersonation
async function impersonate(store) {
    try {
        const { data } = await api.post(`/admin/stores/${store.id}/impersonate`);
        auth.startImpersonation(data.data.token, data.data.user);
        ui.success(`${t('admin.impersonating')} ${store.name}`);
        router.push({ name: 'pos' });
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    }
}

onMounted(load);
</script>

<template>
    <div>
        <h1 class="mb-4 text-2xl font-bold">{{ t('admin.stores') }}</h1>
        <input v-model="search" class="input mb-4" :placeholder="t('common.search')" @input="onSearch" />

        <Spinner v-if="loading" />
        <EmptyState v-else-if="!stores.length" icon="🏪" :title="t('common.no_data')" />
        <div v-else class="space-y-3">
            <div v-for="s in stores" :key="s.id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold">{{ s.name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-emerald-100 text-emerald-700': s.status === 'active',
                                    'bg-amber-100 text-amber-700': s.status === 'trial',
                                    'bg-red-100 text-red-700': s.status === 'suspended',
                                }"
                            >{{ s.status }}</span>
                        </div>
                        <div class="mt-1 text-sm text-slate-500">{{ s.email || '—' }} · {{ s.phone || '—' }}</div>
                        <div class="mt-2 flex gap-4 text-xs text-slate-400">
                            <span>👥 {{ s.users_count }} {{ t('admin.users') }}</span>
                            <span>📦 {{ s.products_count }} {{ t('admin.products') }}</span>
                            <span>🧾 {{ s.sales_count }} {{ t('admin.sales') }}</span>
                            <span v-if="s.current_subscription?.plan">💳 {{ s.current_subscription.plan.name }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn-secondary px-3 py-1.5 text-sm" @click="openCaps(s)">🎛 {{ t('admin.manage_caps') }}</button>
                        <button class="btn-secondary px-3 py-1.5 text-sm" @click="openPlan(s)">💳 {{ t('admin.assign_plan') }}</button>
                        <button class="btn-secondary px-3 py-1.5 text-sm" @click="impersonate(s)">🔑 {{ t('admin.impersonate') }}</button>
                        <button
                            v-if="s.status !== 'suspended'"
                            class="btn-danger px-3 py-1.5 text-sm"
                            @click="setStatus(s, 'suspended')"
                        >⛔ {{ t('admin.suspend') }}</button>
                        <button v-else class="btn-primary px-3 py-1.5 text-sm" @click="setStatus(s, 'active')">✅ {{ t('admin.activate') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capabilities modal -->
        <BaseModal :show="capModal" :title="`${t('admin.capabilities_for')} ${capStore?.name || ''}`" @close="capModal = false">
            <div class="space-y-2">
                <label
                    v-for="c in caps"
                    :key="c.key"
                    class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800"
                >
                    <div>
                        <div class="font-medium">{{ c.label }}</div>
                        <div class="text-xs text-slate-400">{{ c.key }}<span v-if="c.is_default"> · default</span></div>
                    </div>
                    <input v-model="c.enabled" type="checkbox" class="h-6 w-6 rounded" />
                </label>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="capModal = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" :disabled="savingCaps" @click="saveCaps">{{ t('common.save') }}</button>
            </template>
        </BaseModal>

        <!-- Plan modal -->
        <BaseModal :show="planModal" :title="`${t('admin.assign_plan')} — ${planStore?.name || ''}`" size="sm" @close="planModal = false">
            <div class="space-y-2">
                <label
                    v-for="p in plans"
                    :key="p.id"
                    class="flex cursor-pointer items-center justify-between rounded-xl border-2 px-4 py-3"
                    :class="Number(selectedPlan) === Number(p.id) ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-slate-200 dark:border-slate-700'"
                    @click="selectPlan(p.id)"
                >
                    <div>
                        <div class="font-semibold">{{ p.name }}</div>
                        <div class="text-xs text-slate-400">{{ (p.features || []).length }} {{ t('admin.features') }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-brand-600">৳{{ p.price }}</span>
                        <input
                            v-model="selectedPlan"
                            type="radio"
                            name="assigned-plan"
                            :value="p.id"
                            class="h-5 w-5"
                            @click.stop="selectPlan(p.id)"
                        />
                    </div>
                </label>
            </div>
            <template #footer>
                <button class="btn-secondary" @click="planModal = false">{{ t('common.cancel') }}</button>
                <button class="btn-primary px-6" :disabled="savingPlan || !selectedPlan" @click="assignPlan">
                    {{ t('common.save') }}
                </button>
            </template>
        </BaseModal>
    </div>
</template>
