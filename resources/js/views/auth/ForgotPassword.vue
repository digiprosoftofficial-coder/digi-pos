<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';

const { t } = useI18n();
const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await api.post('/auth/forgot-password', { email: email.value });
        sent.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'Request failed';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 p-4">
        <div class="w-full max-w-md">
            <form v-if="!sent" class="card space-y-4" @submit.prevent="submit">
                <h1 class="text-xl font-bold">{{ t('auth.forgot_title') }}</h1>
                <p class="text-sm text-slate-500">{{ t('auth.forgot_hint') }}</p>
                <div v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</div>
                <div>
                    <label class="label">{{ t('common.email') }}</label>
                    <input v-model="email" type="email" class="input" required autofocus />
                </div>
                <button class="btn-primary w-full py-3" :disabled="loading">{{ loading ? t('common.loading') : t('auth.send_reset') }}</button>
                <p class="text-center text-sm">
                    <RouterLink :to="{ name: 'login' }" class="font-semibold text-brand-600">{{ t('auth.back_login') }}</RouterLink>
                </p>
            </form>
            <div v-else class="card space-y-3 text-center">
                <p class="font-semibold">{{ t('auth.reset_sent') }}</p>
                <RouterLink :to="{ name: 'login' }" class="btn-primary inline-block px-6 py-2">{{ t('auth.back_login') }}</RouterLink>
            </div>
        </div>
    </div>
</template>
