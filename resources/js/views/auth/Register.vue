<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const { t } = useI18n();

const form = ref({
    store_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});
const loading = ref(false);
const errors = ref({});

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        await auth.register(form.value);
        ui.success('Store created! 🎉');
        router.push({ name: 'onboarding' });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        ui.error(e.response?.data?.message || 'Registration failed');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 p-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center text-white">
                <h1 class="text-2xl font-bold">{{ t('auth.register') }}</h1>
                <p class="text-brand-100">{{ t('auth.register_subtitle') }}</p>
            </div>
            <form class="card space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label">{{ t('auth.store_name') }}</label>
                    <input v-model="form.store_name" class="input" required autofocus />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">{{ t('auth.owner_name') }}</label>
                        <input v-model="form.owner_name" class="input" required />
                    </div>
                    <div>
                        <label class="label">{{ t('common.phone') }}</label>
                        <input v-model="form.phone" class="input" />
                    </div>
                </div>
                <div>
                    <label class="label">{{ t('common.email') }}</label>
                    <input v-model="form.email" type="email" class="input" required />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email[0] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">{{ t('auth.password') }}</label>
                        <input v-model="form.password" type="password" class="input" required />
                    </div>
                    <div>
                        <label class="label">{{ t('auth.confirm_password') }}</label>
                        <input v-model="form.password_confirmation" type="password" class="input" required />
                    </div>
                </div>
                <button class="btn-primary w-full py-3 text-base" :disabled="loading">
                    {{ loading ? t('common.loading') : t('auth.register') }}
                </button>
                <p class="text-center text-sm text-slate-500">
                    {{ t('auth.have_account') }}
                    <RouterLink :to="{ name: 'login' }" class="font-semibold text-brand-600">{{ t('auth.login') }}</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
