<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

const emit = defineEmits(['detected', 'close']);
const regionId = 'barcode-reader';
const error = ref('');
let scanner = null;

onMounted(async () => {
    scanner = new Html5Qrcode(regionId);
    try {
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 260, height: 160 } },
            (decodedText) => {
                emit('detected', decodedText);
            },
            () => {}
        );
    } catch (e) {
        error.value = 'Camera access denied or unavailable. You can still use a USB scanner or search.';
    }
});

onBeforeUnmount(async () => {
    try {
        if (scanner?.isScanning) await scanner.stop();
        scanner?.clear();
    } catch (e) {
        // ignore
    }
});
</script>

<template>
    <div>
        <div :id="regionId" class="overflow-hidden rounded-xl bg-black"></div>
        <p v-if="error" class="mt-3 text-sm text-amber-600">{{ error }}</p>
        <p v-else class="mt-3 text-center text-sm text-slate-500">Point the camera at a barcode</p>
    </div>
</template>
