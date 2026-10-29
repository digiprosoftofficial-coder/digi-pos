<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import Spinner from '@/components/ui/Spinner.vue';
import { getApiBase, isNative, setApiBase } from '@/lib/platform';

const { t, locale } = useI18n();
const ui = useUiStore();
const auth = useAuthStore(); // billing refresh + owner-only routes

const form = ref(null);
const loading = ref(true);
const saving = ref(false);
const serverUrl = ref(isNative ? getApiBase().replace(/\/api\/v1$/, '') : '');
const plans = ref([]);
const billingLoading = ref(false);

onMounted(async () => {
    const [settings, planRes] = await Promise.all([
        api.get('/settings/store'),
        api.get('/billing/plans').catch(() => ({ data: { data: [] } })),
    ]);
    form.value = {
        ...settings.data.data,
        loyalty: settings.data.data.loyalty || { enabled: false, earn_per_unit: 0, redeem_value: 0 },
    };
    plans.value = planRes.data.data || [];
    loading.value = false;
});

async function save() {
    saving.value = true;
    try {
        const { data } = await api.put('/settings/store', form.value);
        if (auth.user) auth.user.store = data.data;
        ui.success(t('common.save'));
    } catch (e) {
        ui.error('Failed');
    } finally {
        saving.value = false;
    }
}

function setLang(l) {
    ui.setLocale(l);
    locale.value = l;
}

async function exportData() {
    try {
        const { data } = await api.get('/settings/export', { responseType: 'blob' });
        const url = URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `digipro-export-${new Date().toISOString().slice(0, 10)}.json`;
        a.click();
        URL.revokeObjectURL(url);
        ui.success(t('settings.export_data'));
    } catch (e) {
        ui.error(e.response?.data?.message || 'Export failed');
    }
}

async function checkoutPlan(planId, gateway) {
    billingLoading.value = true;
    try {
        const { data } = await api.post('/billing/checkout', {
            subscription_plan_id: planId,
            gateway,
        });
        if (data.data?.redirect_url) {
            window.location.href = data.data.redirect_url;
            return;
        }
        ui.success(data.message || t('common.save'));
        await auth.fetchUser();
        const refreshed = await api.get('/billing/plans');
        plans.value = refreshed.data.data || [];
    } catch (e) {
        ui.error(e.response?.data?.message || 'Checkout failed');
    } finally {
        billingLoading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-2xl font-bold">{{ t('settings.title') }}</h1>
        <Spinner v-if="loading" />
        <div v-else class="space-y-4">
            <div v-if="auth.isStoreExpired" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
                {{ t('settings.trial_expired_hint') }}
            </div>
            <div class="card space-y-3">
                <div><label class="label">{{ t('auth.store_name') }}</label><input v-model="form.name" class="input" /></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ t('common.phone') }}</label><input v-model="form.phone" class="input" /></div>
                    <div><label class="label">{{ t('common.email') }}</label><input v-model="form.email" class="input" /></div>
                </div>
                <div><label class="label">{{ t('common.address') }}</label><textarea v-model="form.address" class="input" rows="2"></textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">{{ t('settings.currency') }}</label><input v-model="form.currency_symbol" class="input" /></div>
                    <div><label class="label">{{ t('settings.tax') }}</label><input v-model.number="form.default_tax_rate" type="number" class="input" /></div>
                </div>
                <div><label class="label">{{ t('settings.receipt_header') }}</label><input v-model="form.receipt_header" class="input" /></div>
                <div><label class="label">{{ t('settings.receipt_footer') }}</label><textarea v-model="form.receipt_footer" class="input" rows="2"></textarea></div>
            </div>

            <div class="card space-y-3">
                <h2 class="font-bold">{{ t('loyalty.title') }}</h2>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.loyalty.enabled" type="checkbox" /> {{ t('loyalty.enabled') }}
                </label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">{{ t('loyalty.earn_rate', { currency: form.currency_symbol || '৳' }) }}</label>
                        <input v-model.number="form.loyalty.earn_per_unit" type="number" min="0" step="0.01" class="input" />
                    </div>
                    <div>
                        <label class="label">{{ t('loyalty.redeem_value', { currency: form.currency_symbol || '৳' }) }}</label>
                        <input v-model.number="form.loyalty.redeem_value" type="number" min="0" step="0.01" class="input" />
                    </div>
                </div>
            </div>

            <button class="btn-primary" :disabled="saving" @click="save">{{ t('common.save') }}</button>

            <div v-if="plans.length" class="card space-y-3">
                <h2 class="font-bold">{{ t('settings.billing') }}</h2>
                <p class="text-sm text-slate-500">{{ t('settings.billing_hint') }}</p>
                <div v-for="p in plans" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <div>
                        <div class="font-semibold">{{ p.name }}</div>
                        <div class="text-sm text-slate-500">{{ form.currency_symbol || '৳' }}{{ p.price }} / {{ p.billing_period }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary text-xs" :disabled="billingLoading" @click="checkoutPlan(p.id, 'sandbox')">
                            {{ t('settings.checkout_sandbox') }}
                        </button>
                        <button type="button" class="btn-secondary text-xs" :disabled="billingLoading" @click="checkoutPlan(p.id, 'manual')">
                            {{ t('settings.checkout_manual') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="card space-y-2">
                <h2 class="font-bold">{{ t('settings.export_data') }}</h2>
                <p class="text-sm text-slate-500">{{ t('settings.export_hint') }}</p>
                <button type="button" class="btn-secondary" @click="exportData">⬇️ {{ t('settings.export_data') }}</button>
            </div>

            <div v-if="isNative" class="card space-y-3">
                <h2 class="font-bold">{{ t('mobile.server_section') }}</h2>
                <div>
                    <label class="label">{{ t('mobile.server_url') }}</label>
                    <input v-model="serverUrl" type="url" class="input" />
                    <p class="mt-1 text-xs text-slate-500">{{ t('mobile.server_settings_hint') }}</p>
                </div>
                <button type="button" class="btn" @click="setApiBase(serverUrl); ui.success(t('mobile.server_saved'))">
                    {{ t('mobile.save_server') }}
                </button>
            </div>

            <div class="card">
                <label class="label">{{ t('settings.language') }}</label>
                <div class="flex gap-2">
                    <button class="btn" :class="ui.locale === 'bn' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="setLang('bn')">বাংলা</button>
                    <button class="btn" :class="ui.locale === 'en' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="setLang('en')">English</button>
                </div>
            </div>
        </div>
    </div>
</template>
