<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import BaseModal from '@/components/ui/BaseModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    total: { type: Number, default: 0 },
});
const emit = defineEmits(['paid', 'close']);

const { t } = useI18n();
const cart = useCartStore();
const auth = useAuthStore();

const paid = ref(0);
const currency = computed(() => auth.currency);
const loyalty = computed(() => auth.store?.loyalty || { enabled: false });
const redeemValue = computed(() => Number(loyalty.value.redeem_value || 0));
const maxPoints = computed(() => Number(cart.customer?.loyalty_points || 0));
const pointsValue = computed(() => Math.min(props.total, (Number(cart.pointsRedeem) || 0) * redeemValue.value));
const payable = computed(() => Math.max(0, props.total - pointsValue.value));
const change = computed(() => Math.max(0, (Number(paid.value) || 0) - payable.value));
const due = computed(() => Math.max(0, payable.value - (Number(paid.value) || 0)));

const methods = [
    { id: 'cash', label: 'cash', icon: '💵' },
    { id: 'bkash', label: 'bkash', icon: '📱' },
    { id: 'card', label: 'card', icon: '💳' },
    { id: 'mobile', label: 'mobile', icon: '📱' },
];

const quickAmounts = computed(() => {
    const total = payable.value;
    const rounded = Math.ceil(total / 100) * 100;
    return [...new Set([total, rounded, rounded + 100, rounded + 500])].filter((v) => v >= total);
});

watch(
    () => [props.show, payable.value],
    ([val]) => {
        if (val) paid.value = payable.value;
    }
);

function confirm() {
    emit('paid', Number(paid.value) || 0);
}
</script>

<template>
    <BaseModal :show="show" :title="t('pos.pay')" size="sm" @close="emit('close')">
        <div class="space-y-4">
            <div class="rounded-2xl bg-brand-50 p-4 text-center dark:bg-brand-900/30">
                <div class="text-sm text-slate-500">{{ t('pos.grand_total') }}</div>
                <div class="text-3xl font-bold text-brand-600">{{ currency }}{{ payable.toFixed(2) }}</div>
            </div>

            <div v-if="loyalty.enabled && cart.customer" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <div class="mb-2 flex justify-between text-sm">
                    <span>{{ t('loyalty.available') }}</span>
                    <span class="font-semibold">{{ maxPoints }} {{ t('loyalty.points') }}</span>
                </div>
                <label class="label">{{ t('loyalty.redeem') }}</label>
                <input
                    v-model.number="cart.pointsRedeem"
                    type="number"
                    min="0"
                    :max="maxPoints"
                    class="input h-9"
                    @change="cart.pointsRedeem = Math.max(0, Math.min(maxPoints, Number(cart.pointsRedeem) || 0))"
                />
                <div v-if="pointsValue" class="mt-1 text-xs text-emerald-600">−{{ currency }}{{ pointsValue.toFixed(2) }}</div>
            </div>

            <div>
                <label class="label">{{ t('pos.payment_method') }}</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button
                        v-for="m in methods"
                        :key="m.id"
                        class="btn py-3"
                        :class="cart.paymentMethod === m.id ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="cart.paymentMethod = m.id"
                    >
                        {{ m.icon }} {{ t('pos.' + m.label) }}
                    </button>
                </div>
            </div>

            <div>
                <label class="label">{{ t('pos.paid') }}</label>
                <input v-model.number="paid" type="number" class="input py-3 text-center text-2xl font-bold" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-for="amt in quickAmounts" :key="amt" class="btn-secondary px-3 py-1.5 text-sm" @click="paid = amt">
                        {{ currency }}{{ amt }}
                    </button>
                </div>
            </div>

            <div class="flex justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800">
                <span class="font-medium">{{ due > 0 ? t('pos.due') : t('pos.change') }}</span>
                <span class="text-lg font-bold" :class="due > 0 ? 'text-amber-600' : 'text-emerald-600'">
                    {{ currency }}{{ (due > 0 ? due : change).toFixed(2) }}
                </span>
            </div>
        </div>

        <template #footer>
            <button class="btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
            <button class="btn-primary px-6" @click="confirm">✓ {{ t('pos.complete_sale') }}</button>
        </template>
    </BaseModal>
</template>
