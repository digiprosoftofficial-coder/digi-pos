<?php

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds automatic multi-tenant scoping by store_id.
 *
 * - Applies a global scope so every query is limited to the current store.
 * - Auto-fills store_id on create from the resolved store context.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model) {
            if (empty($model->store_id) && ($storeId = StoreContext::id())) {
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
