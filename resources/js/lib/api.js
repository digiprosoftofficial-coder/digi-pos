import axios from 'axios';
import { getApiBase, hasApiBase, isNative } from '@/lib/platform';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

// Attach the bearer token from localStorage on every request.
api.interceptors.request.use((config) => {
    const base = getApiBase();
    if (base) {
        config.baseURL = base;
    } else if (isNative) {
        return Promise.reject(new Error('API server not configured'));
    }

    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
        if (typeof config.headers.delete === 'function') {
            config.headers.delete('Content-Type');
        } else {
            delete config.headers['Content-Type'];
        }
    }

    return config;
});

// Global 401 handling: clear session and bounce to login.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export { hasApiBase };
export default api;
