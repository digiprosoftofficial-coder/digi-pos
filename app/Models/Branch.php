<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToStore;

    protected $guarded = [];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];
}
