<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm, md, lg, xl
});
const emit = defineEmits(['close']);

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" @click.self="emit('close')">
                <div
                    class="w-full rounded-t-3xl bg-white shadow-xl dark:bg-slate-900 sm:rounded-2xl"
                    :class="sizes[size]"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold">{{ title }}</h3>
                        <button class="btn-ghost -mr-2 h-9 w-9 rounded-full p-0 text-xl" @click="emit('close')">&times;</button>
                    </div>
                    <div class="max-h-[75vh] overflow-y-auto px-5 py-4">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
