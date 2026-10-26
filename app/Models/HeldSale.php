<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class HeldSale extends Model
{
    use BelongsToStore;

    protected $guarded = [];

    protected $casts = [
        'cart' => 'array',
    ];
}
