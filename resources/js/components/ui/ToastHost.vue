<script setup>
import { useUiStore } from '@/stores/ui';
const ui = useUiStore();

const styles = {
    success: 'bg-emerald-600',
    error: 'bg-red-600',
    info: 'bg-slate-800',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4">
            <TransitionGroup name="toast">
                <div
                    v-for="t in ui.toasts"
                    :key="t.id"
                    class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
                    :class="styles[t.type] || styles.info"
                    @click="ui.dismiss(t.id)"
                >
                    <span class="text-lg">{{ t.type === 'error' ? '⚠️' : '✅' }}</span>
                    <span>{{ t.message }}</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>
