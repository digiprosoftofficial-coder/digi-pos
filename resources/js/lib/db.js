import Dexie from 'dexie';

/**
 * Local offline database for digipro POS.
 *
 * Tables:
 *  - products : cached catalog so the POS can search/scan while offline.
 *  - outbox   : sales created offline, awaiting sync to the server.
 *  - meta     : key/value store (e.g. last catalog sync timestamp).
 *
 * The DB is namespaced per store so switching accounts never mixes data.
 */

let db = null;
let currentStoreId = null;

function build(storeId) {
    const instance = new Dexie(`digipro_pos_${storeId || 'anon'}`);
    instance.version(1).stores({
        // Index the fields we search/scan on.
        products: 'id, name, sku, barcode, is_active',
        outbox: 'client_uuid, status, created_at',
        meta: 'key',
    });
    return instance;
}

/** Open (or reopen) the DB for a given store. Safe to call repeatedly. */
export function openDb(storeId) {
    if (db && currentStoreId === storeId) return db;
    if (db) db.close();
    currentStoreId = storeId;
    db = build(storeId);
    return db;
}

export function getDb() {
    if (!db) return openDb(currentStoreId);
    return db;
}

export async function setMeta(key, value) {
    await getDb().meta.put({ key, value });
}

export async function getMeta(key) {
    const row = await getDb().meta.get(key);
    return row?.value ?? null;
}
