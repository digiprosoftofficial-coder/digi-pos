import { defineStore } from 'pinia';

let toastId = 0;

export const useUiStore = defineStore('ui', {
    state: () => ({
        theme: localStorage.getItem('theme') || 'light',
        locale: localStorage.getItem('locale') || 'bn',
        sidebarOpen: false,
        toasts: [],
        online: navigator.onLine,
    }),
    actions: {
        initTheme() {
            this.applyTheme(this.theme);
        },
        applyTheme(theme) {
            this.theme = theme;
            localStorage.setItem('theme', theme);
            document.documentElement.classList.toggle('dark', theme === 'dark');
        },
        toggleTheme() {
            this.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
        },
        setLocale(locale) {
            this.locale = locale;
            localStorage.setItem('locale', locale);
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        toast(message, type = 'success', timeout = 3000) {
            const id = ++toastId;
            this.toasts.push({ id, message, type });
            setTimeout(() => this.dismiss(id), timeout);
        },
        success(msg) {
            this.toast(msg, 'success');
        },
        error(msg) {
            this.toast(msg, 'error', 4500);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
        setOnline(status) {
            this.online = status;
        },
    },
});
