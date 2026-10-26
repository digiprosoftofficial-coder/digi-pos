<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';

const router = useRouter();
const { t } = useI18n();
const ui = useUiStore();

const step = ref(1);
const product = ref({ name: '', type: 'simple', barcode: '', selling_price: 0, stock_quantity: 0, track_stock: true });
const saving = ref(false);

async function saveProduct() {
    if (!product.value.name) {
        step.value = 3;
        return;
    }
    saving.value = true;
    try {
        await api.post('/products', product.value);
        ui.success(t('common.save'));
        step.value = 3;
    } catch (e) {
        ui.error('Failed');
    } finally {
        saving.value = false;
    }
}

function finish() {
    router.push({ name: 'pos' });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800 p-4">
        <div class="w-full max-w-md">
            <!-- Progress -->
            <div class="mb-6 flex justify-center gap-2">
                <div v-for="n in 3" :key="n" class="h-2 w-10 rounded-full" :class="n <= step ? 'bg-white' : 'bg-white/30'"></div>
            </div>

            <div class="card">
                <!-- Step 1: welcome -->
                <div v-if="step === 1" class="text-center">
                    <div class="mb-3 text-5xl">🎉</div>
                    <h1 class="text-2xl font-bold">{{ t('onboarding.welcome') }}</h1>
                    <p class="mt-2 text-slate-500">{{ t('onboarding.subtitle') }}</p>
                    <button class="btn-primary mt-6 w-full py-3" @click="step = 2">{{ t('common.add') }} →</button>
                </div>

                <!-- Step 2: first product -->
                <div v-else-if="step === 2">
                    <h2 class="mb-4 text-xl font-bold">📦 {{ t('onboarding.step_product') }}</h2>
                    <div class="space-y-3">
                        <div><label class="label">{{ t('common.name') }}</label><input v-model="product.name" class="input" autofocus /></div>
                        <div><label class="label">{{ t('common.barcode') }}</label><input v-model="product.barcode" class="input" /></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="label">{{ t('product.selling_price') }}</label><input v-model.number="product.selling_price" type="number" class="input" /></div>
                            <div><label class="label">{{ t('common.stock') }}</label><input v-model.number="product.stock_quantity" type="number" class="input" /></div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-2">
                        <button class="btn-secondary flex-1" @click="step = 3">{{ t('common.cancel') }}</button>
                        <button class="btn-primary flex-1" :disabled="saving" @click="saveProduct">{{ t('common.save') }}</button>
                    </div>
                </div>

                <!-- Step 3: done -->
                <div v-else class="text-center">
                    <div class="mb-3 text-5xl">✅</div>
                    <h1 class="text-2xl font-bold">{{ t('onboarding.step_done') }}</h1>
                    <button class="btn-primary mt-6 w-full py-3" @click="finish">🛒 {{ t('onboarding.finish') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
