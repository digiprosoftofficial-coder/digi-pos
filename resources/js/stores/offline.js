import { defineStore } from 'pinia';
import { openDb } from '@/lib/db';
import {
    syncCatalog,
    catalogInfo,
    flushOutbox,
    queueSale,
    pendingCount,
} from '@/lib/sync';

export const useOfflineStore = defineStore('offline', {
    state: () => ({
        ready: false,
        syncing: false,
        flushing: false,
        catalogCount: 0,
        catalogSyncedAt: null,
        pending: 0,
        storeId: null,
    }),
    getters: {
        hasPending: (state) => state.pending > 0,
        hasCatalog: (state) => state.catalogCount > 0,
    },
    actions: {
        /** Bind the local DB to the current store and load counts. */
        async init(storeId) {
            if (!storeId) return;
            openDb(storeId);
            this.storeId = storeId;
            await this.refresh();
            this.ready = true;
        },

        async refresh() {
            const info = await catalogInfo();
            this.catalogCount = info.count || 0;
            this.catalogSyncedAt = info.syncedAt;
            this.pending = await pendingCount();
        },

        /** Pull the full catalog down for offline use. */
        async syncCatalog() {
            if (this.syncing) return;
            this.syncing = true;
            try {
                this.catalogCount = await syncCatalog();
                const info = await catalogInfo();
                this.catalogSyncedAt = info.syncedAt;
            } finally {
                this.syncing = false;
            }
        },

        /** Push queued offline sales to the server. */
        async flush() {
            if (this.flushing) return { synced: 0, failed: 0 };
            this.flushing = true;
            try {
                const result = await flushOutbox();
                await this.refresh();
                return result;
            } finally {
                this.flushing = false;
            }
        },

        /** Record a sale locally (used when offline or the request fails). */
        async queue(payload) {
            const entry = await queueSale(payload);
            this.pending = await pendingCount();
            return entry;
        },
    },
});
