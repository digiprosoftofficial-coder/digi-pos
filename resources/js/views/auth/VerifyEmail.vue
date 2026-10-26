<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const status = ref('loading');
const message = ref('');

onMounted(async () => {
    try {
        const { data } = await api.get('/auth/email/verify', { params: route.query });
        status.value = 'ok';
        message.value = data.message || t('auth.verify_success');
    } catch (e) {
        status.value = 'error';
        message.value = e.response?.data?.message || t('auth.verify_failed');
    }
});

function goLogin() {
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 p-4 dark:bg-slate-950">
        <div class="card max-w-md text-center">
            <p v-if="status === 'loading'" class="text-slate-500">{{ t('common.loading') }}</p>
            <template v-else>
                <p class="mb-4 text-lg font-semibold" :class="status === 'ok' ? 'text-emerald-600' : 'text-red-500'">{{ message }}</p>
                <button class="btn-primary" @click="goLogin">{{ t('auth.back_login') }}</button>
            </template>
        </div>
    </div>
</template>
