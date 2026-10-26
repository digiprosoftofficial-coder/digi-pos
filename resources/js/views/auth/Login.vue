<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { getApiBase, isNative } from '@/lib/platform';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const route = useRoute();
const { t } = useI18n();

const form = ref({ email: '', password: '' });
const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await auth.login(form.value);
        ui.success(t('auth.welcome'));
        const fallback = auth.isSuperAdmin ? { name: 'admin.dashboard' } : { name: 'pos' };
        router.push(route.query.redirect || fallback);
    } catch (e) {
        error.value = e.response?.data?.message || 'Login failed';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 p-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center text-white">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold backdrop-blur">D</div>
                <h1 class="text-2xl font-bold">digipro POS</h1>
                <p class="text-brand-100">{{ t('auth.login_subtitle') }}</p>
            </div>
            <form class="card space-y-4" @submit.prevent="submit">
                <div v-if="isNative" class="rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800">
                    <span class="text-slate-500">{{ t('mobile.server_url') }}:</span>
                    <span class="ml-1 font-medium">{{ getApiBase().replace(/\/api\/v1$/, '') }}</span>
                    <RouterLink :to="{ name: 'connect' }" class="ml-2 font-semibold text-brand-600">{{ t('mobile.change_server') }}</RouterLink>
                </div>
                <div v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600 dark:bg-red-900/30">{{ error }}</div>
                <div>
                    <label class="label">{{ t('common.email') }}</label>
                    <input v-model="form.email" type="email" class="input" required autofocus />
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label mb-0">{{ t('auth.password') }}</label>
                        <RouterLink :to="{ name: 'forgot-password' }" class="text-xs font-semibold text-brand-600">{{ t('auth.forgot_password') }}</RouterLink>
                    </div>
                    <input v-model="form.password" type="password" class="input" required />
                </div>
                <button class="btn-primary w-full py-3 text-base" :disabled="loading">
                    {{ loading ? t('common.loading') : t('auth.login') }}
                </button>
                <p class="text-center text-sm text-slate-500">
                    {{ t('auth.no_account') }}
                    <RouterLink :to="{ name: 'register' }" class="font-semibold text-brand-600">{{ t('auth.register') }}</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
