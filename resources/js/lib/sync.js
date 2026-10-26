import api from '@/lib/api';
import { getDb, setMeta, getMeta } from '@/lib/db';

/** Generate an RFC4122-ish v4 UUID for offline idempotency keys. */
export function uuid() {
    if (crypto?.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/* ------------------------------------------------------------------ *
 * Catalog (products) — pulled down so POS works fully offline.
 * ------------------------------------------------------------------ */

export async function syncCatalog() {
    const { data } = await api.get('/lookup/catalog');
    const products = data.data || [];
    const db = getDb();
    await db.transaction('rw', db.products, db.meta, async () => {
        await db.products.clear();
        await db.products.bulkPut(products);
        await setMeta('catalog_synced_at', new Date().toISOString());
        await setMeta('catalog_count', products.length);
    });
    return products.length;
}

export async function catalogInfo() {
    return {
        syncedAt: await getMeta('catalog_synced_at'),
        count: (await getMeta('catalog_count')) || (await getDb().products.count()),
    };
}

/** Offline partial search over the cached catalog. */
export async function localSearch(q) {
    const term = (q || '').trim().toLowerCase();
    const db = getDb();
    const all = await db.products.filter((p) => p.is_active !== false).toArray();
    const matches = term
        ? all.filter((p) =>
              [p.name, p.sku, p.barcode].some((f) => (f || '').toLowerCase().includes(term))
          )
        : all;
    return matches.slice(0, 25);
}

/** Offline exact lookup by barcode / SKU (scanner or USB input). */
export async function localScan(code) {
    const c = (code || '').trim();
    if (!c) return null;
    const db = getDb();
    const all = await db.products.toArray();

    for (const product of all) {
        if (product.barcode === c || product.sku === c) {
            return { product, variant_id: null };
        }
        const variant = (product.variants || []).find((v) => v.barcode === c || v.sku === c);
        if (variant) {
            return { product, variant_id: variant.id };
        }
    }
    return null;
}

/* ------------------------------------------------------------------ *
 * Outbox — sales recorded offline, flushed when connectivity returns.
 * ------------------------------------------------------------------ */

export async function queueSale(payload) {
    const db = getDb();
    const entry = {
        client_uuid: payload.client_uuid || uuid(),
        payload: { ...payload },
        status: 'pending',
        created_at: new Date().toISOString(),
        error: null,
    };
    entry.payload.client_uuid = entry.client_uuid;
    await db.outbox.put(entry);
    return entry;
}

export async function pendingCount() {
    return getDb().outbox.where('status').equals('pending').count();
}

export async function pendingSales() {
    return getDb().outbox.where('status').equals('pending').toArray();
}

/**
 * Push all pending sales to the server. Idempotent: the API dedupes by
 * client_uuid, so re-sending a sale that already landed is harmless.
 * Returns { synced, failed }.
 */
let flushing = false;

export async function flushOutbox() {
    if (flushing) return { synced: 0, failed: 0, skipped: true };
    flushing = true;
    const db = getDb();
    let synced = 0;
    let failed = 0;

    try {
        const pending = await db.outbox.where('status').equals('pending').toArray();
        for (const entry of pending) {
            try {
                await api.post('/sales', entry.payload);
                await db.outbox.delete(entry.client_uuid);
                synced++;
            } catch (e) {
                const status = e?.response?.status;
                // Validation / business errors won't fix themselves on retry —
                // park them so they stop blocking the queue.
                if (status && status >= 400 && status < 500) {
                    await db.outbox.update(entry.client_uuid, {
                        status: 'failed',
                        error: e.response?.data?.message || 'Rejected by server',
                    });
                    failed++;
                } else {
                    // Network / server error: keep pending, stop (likely offline).
                    break;
                }
            }
        }
    } finally {
        flushing = false;
    }
    return { synced, failed };
}
