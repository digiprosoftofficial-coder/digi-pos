<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';

const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const sending = ref(false);

async function resend() {
    sending.value = true;
    try {
        await api.post('/auth/email/verification-notification');
        ui.success(t('auth.verify_sent'));
    } catch (e) {
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div
        v-if="auth.isAuthenticated && auth.user && !auth.user.email_verified && !auth.isSuperAdmin"
        class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
    >
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2">
            <span>{{ t('auth.verify_banner') }}</span>
            <button type="button" class="btn-secondary px-3 py-1 text-xs" :disabled="sending" @click="resend">
                {{ sending ? t('common.loading') : t('auth.resend_verify') }}
            </button>
        </div>
    </div>
</template>
