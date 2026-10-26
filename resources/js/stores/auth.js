import { defineStore } from 'pinia';
import api from '@/lib/api';
import { deviceNameForAuth } from '@/lib/platform';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
        loading: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
        isSuperAdmin: (state) => !!state.user?.is_super_admin,
        isImpersonating: () => !!localStorage.getItem('admin_token'),
        store: (state) => state.user?.store || null,
        roles: (state) => state.user?.roles || [],
        permissions: (state) => state.user?.permissions || [],
        capabilities: (state) => state.user?.capabilities || [],
        currency: (state) => state.user?.store?.currency_symbol || '৳',
    },
    actions: {
        can(permission) {
            if (this.user?.is_super_admin) return true;
            return this.permissions.includes(permission);
        },
        hasCapability(key) {
            if (this.user?.is_super_admin) return true;
            return this.capabilities.includes(key);
        },
        startImpersonation(token, user) {
            // Preserve the admin token so we can return later.
            localStorage.setItem('admin_token', this.token);
            this.setSession(token, user);
        },
        async stopImpersonation() {
            const adminToken = localStorage.getItem('admin_token');
            localStorage.removeItem('admin_token');
            if (adminToken) {
                this.token = adminToken;
                localStorage.setItem('token', adminToken);
                await this.fetchUser();
            }
        },
        setSession(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('token', token);
        },
        async register(payload) {
            const { data } = await api.post('/auth/register', {
                ...payload,
                device_name: payload.device_name || deviceNameForAuth(),
            });
            this.setSession(data.data.token, data.data.user);
            return data;
        },
        async login(payload) {
            const { data } = await api.post('/auth/login', {
                ...payload,
                device_name: payload.device_name || deviceNameForAuth(),
            });
            this.setSession(data.data.token, data.data.user);
            return data;
        },
        async fetchUser() {
            const { data } = await api.get('/auth/me');
            this.user = data.data;
            return data.data;
        },
        async logout() {
            try {
                await api.post('/auth/logout');
            } catch (e) {
                // ignore
            }
            this.$reset();
            this.token = null;
            localStorage.removeItem('token');
        },
    },
});
