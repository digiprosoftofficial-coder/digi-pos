<?php

namespace App\Support;

/**
 * Central definition of store-level roles and permissions.
 * Roles are global (shared across stores); users belong to a store.
 */
class Permissions
{
    // Roles
    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_INVENTORY = 'inventory';

    /** All store-level permissions. */
    public const ALL = [
        'pos.use',
        'sales.view',
        'sales.create',
        'sales.void',
        'products.view',
        'products.manage',
        'categories.manage',
        'brands.manage',
        'units.manage',
        'customers.view',
        'customers.manage',
        'users.view',
        'users.manage',
        'reports.view',
        'settings.manage',
        'branches.manage',
        'suppliers.manage',
        'purchases.manage',
        'returns.manage',
    ];

    /** Role => permissions map. */
    public const ROLE_MAP = [
        self::ROLE_OWNER => self::ALL,
        self::ROLE_MANAGER => [
            'pos.use', 'sales.view', 'sales.create', 'sales.void',
            'products.view', 'products.manage', 'categories.manage', 'brands.manage', 'units.manage',
            'customers.view', 'customers.manage', 'users.view', 'reports.view',
            'suppliers.manage', 'purchases.manage', 'returns.manage',
        ],
        self::ROLE_CASHIER => [
            'pos.use', 'sales.view', 'sales.create',
            'products.view', 'customers.view', 'customers.manage', 'returns.manage',
        ],
        self::ROLE_INVENTORY => [
            'products.view', 'products.manage', 'categories.manage', 'brands.manage', 'units.manage',
            'suppliers.manage', 'purchases.manage',
        ],
    ];

    public static function roles(): array
    {
        return array_keys(self::ROLE_MAP);
    }
}
