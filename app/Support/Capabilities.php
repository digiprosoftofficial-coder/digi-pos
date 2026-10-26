<?php

namespace App\Support;

/**
 * Registry of toggleable store capabilities (feature flags).
 *
 * A store's effective access to a capability is resolved as:
 *   per-store override (store_capabilities) -> subscription plan features -> DEFAULTS
 */
class Capabilities
{
    /**
     * key => human label (shown to the super admin).
     */
    public const ALL = [
        'pos' => 'Point of Sale',
        'products' => 'Products & Inventory',
        'variants' => 'Product Variants',
        'customers' => 'Customers',
        'reports.basic' => 'Basic Reports',
        'reports.advanced' => 'Advanced Reports & Export',
        'staff' => 'Staff & Roles',
        'purchases' => 'Purchases & Suppliers',
        'returns' => 'Sales Returns',
        'multi_branch' => 'Multi-branch / Outlets',
        'barcode_print' => 'Barcode Label Printing',
        'offline' => 'Offline Mode (PWA)',
    ];

    /**
     * Capabilities every store gets regardless of plan.
     */
    public const DEFAULTS = [
        'pos',
        'products',
        'variants',
        'customers',
        'reports.basic',
        'staff',
    ];

    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::ALL);
    }
}
