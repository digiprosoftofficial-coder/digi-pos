<script setup>
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import BaseModal from '@/components/ui/BaseModal.vue';

defineProps({
    product: { type: Object, required: true },
});
const emit = defineEmits(['pick', 'close']);

const { t } = useI18n();
const auth = useAuthStore();
</script>

<template>
    <BaseModal :show="true" :title="product.name" @close="emit('close')">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <button
                v-for="v in product.variants"
                :key="v.id"
                class="card flex flex-col items-start gap-1 p-3 text-left hover:ring-2 hover:ring-brand-500"
                @click="emit('pick', v.id)"
            >
                <div class="mb-1 flex h-14 w-full items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                    <img v-if="v.image" :src="v.image" alt="" class="h-full w-full object-cover" />
                    <span v-else class="text-2xl">📦</span>
                </div>
                <span class="text-sm font-semibold">{{ v.name }}</span>
                <span class="font-bold text-brand-600">{{ auth.currency }}{{ v.effective_selling_price ?? v.selling_price }}</span>
                <span class="text-xs text-slate-400">{{ t('common.stock') }}: {{ v.stock_quantity }}</span>
            </button>
        </div>
    </BaseModal>
</template>
