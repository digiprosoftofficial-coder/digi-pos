<?php

namespace App\Http\Concerns;

use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Model;

trait EnsuresStoreOwnership
{
    protected function ensureSameStore(Model $model): void
    {
        abort_unless((int) $model->store_id === (int) StoreContext::id(), 404);
    }
}
