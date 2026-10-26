import { Capacitor } from '@capacitor/core';

export const isNative = Capacitor.isNativePlatform();
export const nativePlatform = Capacitor.getPlatform();

const API_SUFFIX = '/api/v1';

function normalizeBase(url) {
    return url.trim().replace(/\/+$/, '');
}

/** Resolved REST base URL (includes /api/v1). Empty on native until the user configures a server. */
export function getApiBase() {
    const stored = localStorage.getItem('api_base_url');
    if (stored) {
        return normalizeBase(stored);
    }
    const env = import.meta.env.VITE_API_URL;
    if (env) {
        const base = normalizeBase(env);
        return base.endsWith(API_SUFFIX) ? base : `${base}${API_SUFFIX}`;
    }
    if (!isNative && typeof window !== 'undefined') {
        return `${window.location.origin}${API_SUFFIX}`;
    }
    return '';
}

export function hasApiBase() {
    return !!getApiBase();
}

/**
 * Accepts either the site root (https://shop.example.com) or full API URL.
 */
export function setApiBase(input) {
    const raw = normalizeBase(input);
    if (!raw) {
        localStorage.removeItem('api_base_url');
        return '';
    }
    const base = raw.endsWith(API_SUFFIX) ? raw : `${raw}${API_SUFFIX}`;
    localStorage.setItem('api_base_url', base);
    return base;
}

export function clearApiBase() {
    localStorage.removeItem('api_base_url');
}

export function deviceNameForAuth() {
    if (!isNative) {
        return 'web';
    }
    if (nativePlatform === 'ios') {
        return 'ios';
    }
    if (nativePlatform === 'android') {
        return 'android';
    }
    return `native-${nativePlatform}`;
}
