<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { getApiBase, isNative, setApiBase } from '@/lib/platform';

const router = useRouter();
const { t } = useI18n();

const serverUrl = ref('');
const loading = ref(false);
const error = ref('');

if (getApiBase()) {
    serverUrl.value = getApiBase().replace(/\/api\/v1$/, '');
}

async function testAndSave() {
    loading.value = true;
    error.value = '';
    try {
        const base = setApiBase(serverUrl.value);
        await axios.get(`${base}/auth/me`, {
            headers: { Accept: 'application/json' },
            validateStatus: (s) => s === 401 || s === 200,
        });
        router.push({ name: 'login' });
    } catch (e) {
        error.value = t('mobile.server_unreachable');
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
                <p class="text-brand-100">{{ t('mobile.connect_subtitle') }}</p>
            </div>
            <form class="card space-y-4" @submit.prevent="testAndSave">
                <div v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600 dark:bg-red-900/30">{{ error }}</div>
                <div>
                    <label class="label">{{ t('mobile.server_url') }}</label>
                    <input
                        v-model="serverUrl"
                        type="url"
                        class="input"
                        required
                        :placeholder="t('mobile.server_placeholder')"
                        autocapitalize="off"
                        autocorrect="off"
                    />
                    <p class="mt-2 text-xs text-slate-500">{{ t('mobile.server_hint') }}</p>
                </div>
                <button class="btn-primary w-full py-3 text-base" :disabled="loading">
                    {{ loading ? t('common.loading') : t('mobile.save_server') }}
                </button>
                <p v-if="isNative" class="text-center text-xs text-slate-400">{{ t('mobile.emulator_hint') }}</p>
            </form>
        </div>
    </div>
</template>
