<?php

namespace App\Models\Scopes;

use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (StoreContext::isDisabled()) {
            return;
        }

        if ($storeId = StoreContext::id()) {
            $builder->where($model->getTable().'.store_id', $storeId);
        }
    }
}
