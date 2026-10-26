<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/lib/api';
import BaseModal from '@/components/ui/BaseModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    sale: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const { t } = useI18n();
const auth = useAuthStore();
const store = computed(() => auth.store || {});
const currency = computed(() => auth.currency);

function printReceipt() {
    window.print();
}

async function downloadPdf() {
    if (!props.sale?.id) return;
    const { data } = await api.get(`/sales/${props.sale.id}/receipt`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `invoice-${props.sale.invoice_no}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

function fmt(n) {
    return Number(n || 0).toFixed(2);
}
</script>

<template>
    <BaseModal :show="show" :title="t('pos.print_receipt')" size="sm" @close="emit('close')">
        <div v-if="sale" id="thermal-receipt" class="mx-auto max-w-[300px] bg-white p-3 font-mono text-[12px] leading-tight text-black">
            <div class="text-center">
                <div class="text-base font-bold">{{ store.name }}</div>
                <div v-if="store.address">{{ store.address }}</div>
                <div v-if="store.phone">{{ store.phone }}</div>
                <div v-if="store.receipt_header">{{ store.receipt_header }}</div>
            </div>
            <div class="my-2 border-t border-dashed border-black"></div>
            <div>Invoice: {{ sale.invoice_no }}</div>
            <div>{{ new Date(sale.sold_at).toLocaleString() }}</div>
            <div class="my-2 border-t border-dashed border-black"></div>
            <table class="w-full">
                <thead>
                    <tr class="border-b border-dashed border-black text-left">
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Amt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in sale.items" :key="item.id">
                        <td class="py-0.5">{{ item.name }}</td>
                        <td class="text-right">{{ Number(item.quantity) }}</td>
                        <td class="text-right">{{ fmt(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="my-2 border-t border-dashed border-black"></div>
            <div class="flex justify-between"><span>Subtotal</span><span>{{ fmt(sale.subtotal) }}</span></div>
            <div class="flex justify-between"><span>Discount</span><span>-{{ fmt(sale.discount) }}</span></div>
            <div v-if="sale.tax > 0" class="flex justify-between"><span>Tax</span><span>{{ fmt(sale.tax) }}</span></div>
            <div class="flex justify-between text-sm font-bold"><span>Total</span><span>{{ currency }}{{ fmt(sale.total) }}</span></div>
            <div class="flex justify-between"><span>Paid</span><span>{{ fmt(sale.paid) }}</span></div>
            <div class="flex justify-between"><span>Change</span><span>{{ fmt(sale.change) }}</span></div>
            <div class="my-2 border-t border-dashed border-black"></div>
            <div class="text-center">{{ store.receipt_footer || 'Thank you!' }}</div>
            <div v-if="sale.offline" class="mt-1 text-center text-[10px]">⏳ {{ t('offline.pending_receipt') }}</div>
        </div>

        <template #footer>
            <button v-if="!sale?.offline" class="btn-secondary" @click="downloadPdf">⬇️ PDF</button>
            <button class="btn-secondary" @click="emit('close')">{{ t('pos.new_sale') }}</button>
            <button class="btn-primary" @click="printReceipt">🖨 {{ t('pos.print_receipt') }}</button>
        </template>
    </BaseModal>
</template>
