<?php

namespace App\Support;

/**
 * Holds the current tenant (store) id for the request lifecycle.
 *
 * Resolved from the authenticated user by the ResolveStore middleware,
 * but can also be set manually (e.g. for console/queue jobs or offline sync).
 */
class StoreContext
{
    protected static ?int $storeId = null;

    protected static bool $disabled = false;

    public static function set(?int $storeId): void
    {
        static::$storeId = $storeId;
    }

    public static function id(): ?int
    {
        return static::$storeId;
    }

    public static function has(): bool
    {
        return static::$storeId !== null;
    }

    public static function clear(): void
    {
        static::$storeId = null;
    }

    /**
     * Temporarily disable store scoping (super admin / platform-wide queries).
     */
    public static function disable(): void
    {
        static::$disabled = true;
    }

    public static function enable(): void
    {
        static::$disabled = false;
    }

    public static function isDisabled(): bool
    {
        return static::$disabled;
    }

    /**
     * Run a callback without store scoping, then restore state.
     */
    public static function withoutScope(callable $callback): mixed
    {
        $previous = static::$disabled;
        static::$disabled = true;

        try {
            return $callback();
        } finally {
            static::$disabled = $previous;
        }
    }
}
