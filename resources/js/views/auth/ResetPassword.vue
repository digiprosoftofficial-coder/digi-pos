<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const ui = useUiStore();

const form = ref({
    email: '',
    token: '',
    password: '',
    password_confirmation: '',
});
const loading = ref(false);
const error = ref('');

onMounted(() => {
    form.value.email = (route.query.email || '').toString();
    form.value.token = (route.query.token || '').toString();
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await api.post('/auth/reset-password', form.value);
        ui.success(t('auth.reset_success'));
        router.push({ name: 'login' });
    } catch (e) {
        error.value = e.response?.data?.message || e.response?.data?.errors?.email?.[0] || 'Reset failed';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 p-4">
        <form class="card w-full max-w-md space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-bold">{{ t('auth.reset_title') }}</h1>
            <div v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</div>
            <div>
                <label class="label">{{ t('common.email') }}</label>
                <input v-model="form.email" type="email" class="input" required />
            </div>
            <div>
                <label class="label">{{ t('auth.password') }}</label>
                <input v-model="form.password" type="password" class="input" required minlength="8" />
            </div>
            <div>
                <label class="label">{{ t('auth.password_confirm') }}</label>
                <input v-model="form.password_confirmation" type="password" class="input" required minlength="8" />
            </div>
            <button class="btn-primary w-full py-3" :disabled="loading">{{ loading ? t('common.loading') : t('auth.reset_password') }}</button>
        </form>
    </div>
</template>
