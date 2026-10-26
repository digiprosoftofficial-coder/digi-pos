<script setup>
defineProps({
    icon: { type: String, default: '📦' },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-14 text-center dark:border-slate-800">
        <div class="mb-3 text-5xl">{{ icon }}</div>
        <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">{{ title }}</h3>
        <p v-if="subtitle" class="mt-1 max-w-xs text-sm text-slate-400">{{ subtitle }}</p>
        <div class="mt-4">
            <slot />
        </div>
    </div>
</template>
